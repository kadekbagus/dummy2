<?php namespace Orbit\Controller\API\v1\Pub\Payment;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for update payment with midtrans
 */

use Carbon\Carbon as Carbon;
use Config;
use DB;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Event;
use Exception;
use Helper\EloquentRecordCounter as RecordCounter;
use IssuedCoupon;
use Log;
use Mall;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Orbit\Helper\Midtrans\API\TransactionCancel;
use Orbit\Helper\Midtrans\API\TransactionStatus;
use Orbit\Notifications\Payment\DeniedPaymentNotification;
use Orbit\Notifications\Payment\SuspiciousPaymentNotification;
use PaymentTransaction;
use Queue;
use User;
use Validator;
use Activity;

class PaymentMidtransUpdateAPIController extends PubControllerAPI
{

    public function postPaymentMidtransUpdate()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            $payment_transaction_id = OrbitInput::post('payment_transaction_id');
            $status = OrbitInput::post('status');
            $mallId = OrbitInput::post('mall_id', null);

            $paymentHelper = PaymentHelper::create();
            $paymentHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'payment_transaction_id'   => $payment_transaction_id,
                    'status'                   => $status,
                ),
                array(
                    'payment_transaction_id'   => 'required|orbit.exist.payment_transaction_id',
                    'status'                   => 'required|in:pending,success,failed,expired,dont-update,denied,suspicious'
                ),
                array(
                    'orbit.exist.payment_transaction_id' => 'payment transaction id not found'
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $paymentSuspicious = false;
            $paymentDenied = false;
            $shouldUpdate = false;

            $payment_update = PaymentTransaction::with(['coupon', 'issued_coupon', 'user'])->findOrFail($payment_transaction_id);

            $oldStatus = $payment_update->status;

            // List of status which considered as final (should not be changed again except some conditions met).
            $finalStatus = [
                PaymentTransaction::STATUS_SUCCESS,
                PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED,
                PaymentTransaction::STATUS_EXPIRED,
                PaymentTransaction::STATUS_FAILED,
                PaymentTransaction::STATUS_DENIED,
            ];

            // Assume status as success if it is success_no_coupon/success_no_coupon_failed, so no need re-check to Midtrans.
            $tmpOldStatus = $oldStatus;
            if (in_array($oldStatus, [PaymentTransaction::STATUS_SUCCESS_NO_COUPON, PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED])) {
                $tmpOldStatus = PaymentTransaction::STATUS_SUCCESS;
            }

            // If old status was marked as final and doesnt match with the new one, then
            // ask Midtrans for the correct one.
            $tmpNewStatus = $status;
            if (in_array($oldStatus, $finalStatus) && $tmpOldStatus !== $status) {
                Log::info("PaidCoupon: Payment {$payment_transaction_id} was marked as FINAL, but there is new request to change status to " . $tmpNewStatus);
                Log::info("PaidCoupon: Getting correct status from Midtrans for payment {$payment_transaction_id}...");

                $transactionStatus = TransactionStatus::create()->getStatus($payment_transaction_id);
                $status = $transactionStatus->mapToInternalStatus();

                // If the new status doesnt match with what midtrans gave us, then
                // we can ignored this request (dont update).
                if ($tmpNewStatus !== $status) {
                    Log::info("PaidCoupon: New status {$tmpNewStatus} for payment {$payment_transaction_id} will be IGNORED since the correct status is {$status}!");
                }
                else {
                    Log::info("PaidCoupon: New status {$status} for payment {$payment_transaction_id} will be set!");
                    $shouldUpdate = true;
                }
            }
            else if (! in_array($oldStatus, $finalStatus)) {
                $shouldUpdate = true;
            }
            else {
                Log::info("PaidCoupon: Payment {$payment_transaction_id} is good. Nothing to do.");
            }

            // If old status is not final, then we should update...
            if ($shouldUpdate) {
                $activity = Activity::mobileci()
                                        ->setActivityType('transaction')
                                        ->setUser($payment_update->user)
                                        ->setActivityName('transaction_status');

                $mall = Mall::where('merchant_id', $mallId)->first();

                // Supicious payment will be treated as pending payment.
                if ($status === PaymentTransaction::STATUS_SUSPICIOUS) {
                    // Flag to send suspicious payment notification.
                    $paymentSuspicious = true;
                    $status = PaymentTransaction::STATUS_PENDING;
                    $payment_update->notes = $payment_update->notes . 'Payment suspicious.' . "\n----\n";
                    Log::info("PaidCoupon: Payment {$payment_transaction_id} is suspicious.");
                }

                if (in_array($oldStatus, [PaymentTransaction::STATUS_SUCCESS, PaymentTransaction::STATUS_SUCCESS_NO_COUPON]) &&
                    $status === PaymentTransaction::STATUS_DENIED) {

                    // Flag to send denied payment.
                    $paymentDenied = true;
                    $payment_update->notes = $payment_update->notes . 'Payment denied.' . "\n----\n";
                    Log::info("PaidCoupon: Payment {$payment_transaction_id} is denied after success.");
                }

                $payment_update->status = $status;

                OrbitInput::post('external_payment_transaction_id', function($external_payment_transaction_id) use ($payment_update) {
                    $payment_update->external_payment_transaction_id = $external_payment_transaction_id;
                });

                OrbitInput::post('provider_response_code', function($provider_response_code) use ($payment_update) {
                    $payment_update->provider_response_code = $provider_response_code;
                });

                OrbitInput::post('provider_response_message', function($provider_response_message) use ($payment_update) {
                    $payment_update->provider_response_message = $provider_response_message;
                });

                OrbitInput::post('payment_midtrans_info', function($payment_midtrans_info) use ($payment_update) {
                    $payment_update->payment_midtrans_info = serialize($payment_midtrans_info);
                });

                $payment_update->responded_at = Carbon::now('UTC');

                // Link this payment to reserved IssuedCoupon.
                if (empty($payment_update->issued_coupon)) {

                    // Link to IssuedCoupon if the payment is not denied/failed/expired.
                    if (! in_array($status, [PaymentTransaction::STATUS_DENIED, PaymentTransaction::STATUS_EXPIRED, PaymentTransaction::STATUS_FAILED])) {
                        $reservedCoupon = IssuedCoupon::where('user_id', $payment_update->user_id)
                                      ->where('promotion_id', $payment_update->object_id)
                                      ->where('status', IssuedCoupon::STATUS_RESERVED)
                                      ->whereNull('transaction_id')
                                      ->first(); // Can update transaction_id directly here, but for now just get the record.

                        if (! empty($reservedCoupon)) {
                            $reservedCoupon->transaction_id = $payment_transaction_id;
                            $reservedCoupon->save();
                        }
                        else {
                            Log::info("PaidCoupon: Can not link coupon, it is being reserved by the same user {$payment_update->user_id}.");
                            $payment_update->status = PaymentTransaction::STATUS_FAILED;
                            $failed = true;
                        }
                    }
                }

                // If payment is success and not with credit card (not realtime) or the payment for Sepulsa voucher,
                // then we assume the status as success_no_coupon (so frontend will show preparing voucher page).
                if ($status === PaymentTransaction::STATUS_SUCCESS) {
                    if (isset($failed)) {
                        $payment_update->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                    }
                    else if ($payment_update->paidWith(['bank_transfer', 'echannel']) || $payment_update->forSepulsa()) {
                        $payment_update->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                    }
                }

                $payment_update->save();

                // Commit the changes ASAP so if there are any other requests that trigger this controller
                // they will use the updated payment data/status.
                $this->commit();

                // Log activity...
                // Should be done before issuing coupon for the sake of activity ordering,
                // or at the end before returning the response??
                $mall = Mall::where('merchant_id', $mallId)->first();
                if ($payment_update->failed() || $payment_update->denied()) {
                    $activity->setActivityNameLong('Transaction is Failed')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setNotes('Transaction is failed from Midtrans/Customer.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment_update->expired()) {
                    $activity->setActivityNameLong('Transaction is Expired')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setNotes('Transaction is expired from Midtrans.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment_update->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON) {
                    $activity->setActivityNameLong('Transaction is Success - Getting Coupon')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setNotes($payment_update->coupon->promotion_type)
                            ->setLocation($mall)
                            ->responseOK()
                            ->save();
                }
                else if ($payment_update->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED) {
                    $activity->setActivityNameLong('Transaction is Success - Failed Getting Coupon')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setNotes('Failed to get coupon. Can not get reserved coupons for this transaction.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }

                Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment_update, $mall]);

                $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

                // If status before is starting and now is pending, we should trigger job transaction status check.
                // The job will be run forever until the transaction status is success, failed, expired or reached the maximum number of check.
                if ($oldStatus === PaymentTransaction::STATUS_STARTING && $status === PaymentTransaction::STATUS_PENDING) {
                    $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 60);
                    $queueData = ['transactionId' => $payment_transaction_id, 'check' => 0];
                    if (! empty($mall)) {
                        $queueData['mall_id'] = $mall->merchant_id;
                    }

                    Queue::later(
                        $delay,
                        'Orbit\\Queue\\Payment\\Midtrans\\CheckTransactionStatusQueue',
                        $queueData
                    );

                    Log::info('PaidCoupon: First time TransactionStatus check for Payment: ' . $payment_transaction_id . ' is scheduled to run after ' . $delay . ' seconds.');

                    $activity->setActivityNameLong('Transaction is Pending')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setNotes($payment_update->coupon->promotion_type)
                            ->setLocation($mall)
                            ->responseOK()
                            ->save();
                }

                // If previous status was success and now is denied, then send notification to admin.
                // Maybe it means the payment was reversed/canceled by customer after paying.
                if ($paymentDenied) {
                    foreach($adminEmails as $email) {
                        $admin         = new User;
                        $admin->email  = $email;
                        $admin->notify(new DeniedPaymentNotification($payment_update), 3);
                    }
                }

                // If Payment is suspicious, then notify admin.
                // @todo should only send this once.
                if ($paymentSuspicious) {
                    foreach($adminEmails as $email) {
                        $admin         = new User;
                        $admin->email  = $email;
                        $admin->notify(new SuspiciousPaymentNotification($payment_update), 3);
                    }
                }
            }

            $this->response->data = $payment_update;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();

        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
            // Rollback the changes
            $this->rollBack();

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }
}
