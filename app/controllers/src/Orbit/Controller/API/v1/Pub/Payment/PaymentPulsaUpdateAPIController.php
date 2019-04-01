<?php namespace Orbit\Controller\API\v1\Pub\Payment;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DB;
use Validator;
use Queue;
use Log;
use Config;
use Exception;
use PaymentTransaction;
use PaymentTransactionDetail;
use User;
use Pulsa;
use Carbon\Carbon as Carbon;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Event;
use Activity;

use Orbit\Helper\Midtrans\API\TransactionStatus;
use Orbit\Helper\Midtrans\API\TransactionCancel;

use Orbit\Notifications\Payment\SuspiciousPaymentNotification;
use Orbit\Notifications\Payment\DeniedPaymentNotification;
use Orbit\Notifications\Pulsa\PendingPaymentNotification;
use Orbit\Notifications\Pulsa\CanceledPaymentNotification;
use Mall;

/**
 * Controller for update payment with midtrans
 *
 * @author kadek <kadek@dominopos.com>
 * @author  budi <budi@dominopos.com>
 *
 * @todo  Remove unused log commands.
 */
class PaymentPulsaUpdateAPIController extends PubControllerAPI
{

    public function postPaymentPulsaUpdate()
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
                    'status'                   => 'required|in:pending,success,canceled,failed,expired,denied,suspicious,abort'
                ),
                array(
                    'orbit.exists.payment_transaction_id' => 'payment transaction id not found'
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

            $payment_update = PaymentTransaction::with(['details.pulsa', 'midtrans', 'user'])->findOrFail($payment_transaction_id);

            $oldStatus = $payment_update->status;

            // List of status which considered as final (should not be changed again except some conditions met).
            $finalStatus = [
                PaymentTransaction::STATUS_SUCCESS,
                PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED,
                PaymentTransaction::STATUS_EXPIRED,
                PaymentTransaction::STATUS_FAILED,
                PaymentTransaction::STATUS_DENIED,
                PaymentTransaction::STATUS_CANCELED,
                PaymentTransaction::STATUS_ABORTED,
            ];

            // Assume status as success if it is success_no_coupon/success_no_coupon_failed,
            // because Midtrans and landing_page don't send those status. (They only know 'success')
            $tmpOldStatus = $oldStatus;
            if (in_array($oldStatus, [PaymentTransaction::STATUS_SUCCESS_NO_COUPON, PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED])) {
                $tmpOldStatus = PaymentTransaction::STATUS_SUCCESS;
            }

            // If old status was marked as final and doesnt match with the new one, then
            // ask Midtrans for the correct one.
            $tmpNewStatus = $status;
            if (in_array($oldStatus, $finalStatus) && $tmpOldStatus !== $status) {
                Log::info("Pulsa: Payment {$payment_transaction_id} was marked as FINAL, but there is new request to change status to " . $tmpNewStatus);
                Log::info("Pulsa: Getting correct status from Midtrans for payment {$payment_transaction_id}...");

                $transactionStatus = TransactionStatus::create()->getStatus($payment_transaction_id);
                $status = $transactionStatus->mapToInternalStatus();

                // If the new status doesnt match with what midtrans gave us, then
                // we can ignored this request (dont update).
                if ($tmpNewStatus !== $status) {
                    Log::info("Pulsa: New status {$tmpNewStatus} for payment {$payment_transaction_id} will be IGNORED since the correct status is {$status}!");
                }
                else {
                    Log::info("Pulsa: New status {$status} for payment {$payment_transaction_id} will be set!");
                    $shouldUpdate = true;
                }
            }
            else if (! in_array($oldStatus, $finalStatus)) {
                if ($status === PaymentTransaction::STATUS_ABORTED) {
                    // If status is aborted, then check if the transaction is in pending (exists) or not in Midtrans.
                    // If doesn't exist, assume the payment is starting and we can abort it.
                    // Otherwise, assume it is pending and we should not update the statusad.
                    Log::info("Pulsa: Request to abort payment {$payment_transaction_id}...");
                    Log::info("Pulsa: Checking transaction {$payment_transaction_id} status in Midtrans...");
                    $transactionStatus = TransactionStatus::create()->getStatus($payment_transaction_id);
                    if ($transactionStatus->notFound()) {
                        Log::info("Pulsa: Transaction {$payment_transaction_id} not found! Aborting payment...");
                        $shouldUpdate = true;
                    }
                    else {
                        Log::info("Pulsa: Transaction {$payment_transaction_id} found! Payment can not be aborted/canceled.");
                    }
                }
                else {
                    $shouldUpdate = true;
                }
            }
            else {
                Log::info("Pulsa: Payment {$payment_transaction_id} is good. Nothing to do.");
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
                    Log::info("Pulsa: Payment {$payment_transaction_id} is suspicious.");
                }

                if ($payment_update->completed() && $status === PaymentTransaction::STATUS_DENIED) {
                    // Flag to send denied payment.
                    $paymentDenied = true;
                    $payment_update->notes = $payment_update->notes . 'Payment denied.' . "\n----\n";
                    Log::info("Pulsa: Payment {$payment_transaction_id} is denied after paid.");
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
                    $payment_update->midtrans->payment_midtrans_info = serialize($payment_midtrans_info);
                    $payment_update->midtrans->save();
                });

                $payment_update->responded_at = Carbon::now('UTC');

                // If payment is success and not with credit card (not realtime) or the payment for Sepulsa voucher,
                // then we assume the status as success_no_coupon (so frontend will show preparing voucher page).
                if ($status === PaymentTransaction::STATUS_SUCCESS) {
                    if (isset($failed)) {
                        $payment_update->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                    }
                    else if ($payment_update->paidWith(['bank_transfer', 'echannel']) || $payment_update->forPulsa()) {
                        $payment_update->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                    }
                }

                $payment_update->save();

                $pulsaName = $payment_update->details->first()->pulsa->pulsa_display_name;

                // Commit the changes ASAP so if there are any other requests that trigger this controller
                // they will use the updated payment data/status.
                // Try not doing any expensive operation above.
                $this->commit();

                // Log activity...
                // Should be done before issuing coupon for the sake of activity ordering,
                // or at the end before returning the response??
                if ($payment_update->failed() || $payment_update->denied()) {
                    $activity->setActivityNameLong('Transaction is Failed')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setObjectDisplayName($pulsaName)
                            ->setNotes('Transaction is failed from Midtrans/Customer.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment_update->expired()) {
                    $activity->setActivityNameLong('Transaction is Expired')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setObjectDisplayName($pulsaName)
                            ->setNotes('Transaction is expired from Midtrans.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment_update->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON) {
                    $activity->setActivityNameLong('Transaction is Success - Getting Pulsa')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setObjectDisplayName($pulsaName)
                            ->setNotes($pulsaName)
                            ->setLocation($mall)
                            ->responseOK()
                            ->save();
                }
                else if ($payment_update->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED) {
                    $activity->setActivityNameLong('Transaction is Success - Failed Getting Pulsa')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setObjectDisplayName($pulsaName)
                            ->setNotes('Failed to get pulsa. Can not get buy pulsa for this transaction.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }

                Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment_update, $mall]);

                $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

                // If previous status was starting and now is pending, we should trigger job transaction status check.
                // The job will be run forever until the transaction status is success, failed, expired or reached the maximum number of allowed check.
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

                    Log::info('Pulsa: First time TransactionStatus check for Payment: ' . $payment_transaction_id . ' is scheduled to run after ' . $delay . ' seconds.');

                    $activity->setActivityNameLong('Transaction is Pending')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setObjectDisplayName($pulsaName)
                            ->setNotes($pulsaName)
                            ->setLocation($mall)
                            ->responseOK()
                            ->save();

                    // Notify customer for pending payment (to complete the payment).
                    // Send email to address that being used on checkout (can be different with user's email)
                    if ($payment_update->paidWith(['bank_transfer', 'echannel'])) {
                        $paymentUser = new User;
                        $paymentUser->email = $payment_update->user_email;
                        $paymentUser->notify(new PendingPaymentNotification($payment_update), 30);
                    }
                }

                // Send notification if the purchase was canceled.
                // Only send if previous status was pending.
                if ($oldStatus === PaymentTransaction::STATUS_PENDING && $status === PaymentTransaction::STATUS_CANCELED) {
                    $activity->setActivityNameLong('Transaction Canceled')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment_update)
                            ->setObjectDisplayName($pulsaName)
                            ->setNotes($pulsaName)
                            ->setLocation($mall)
                            ->responseOK()
                            ->save();

                    $payment_update->user->notify(new CanceledPaymentNotification($payment_update));
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
