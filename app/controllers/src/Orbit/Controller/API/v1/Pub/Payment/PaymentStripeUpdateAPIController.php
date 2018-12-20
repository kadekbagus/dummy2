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
use IssuedCoupon;
use User;
use Carbon\Carbon as Carbon;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Event;
use Activity;

use Orbit\Helper\Midtrans\API\TransactionStatus;
use Orbit\Helper\Midtrans\API\TransactionCancel;

use Orbit\Notifications\Payment\SuspiciousPaymentNotification;
use Orbit\Notifications\Payment\DeniedPaymentNotification;
use Orbit\Notifications\Payment\PendingPaymentNotification;
use Orbit\Notifications\Payment\CanceledPaymentNotification;
use Mall;

/**
 * Controller for update payment with midtrans
 *
 * @author kadek <kadek@dominopos.com>
 * @author  budi <budi@dominopos.com>
 *
 * @todo  Remove unused log commands.
 */
class PaymentStripeUpdateAPIController extends PubControllerAPI
{

    public function postPaymentStripeUpdate()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            Log::info("PaidCoupon: Stripe Payment Update is starting...");

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

            $payment_update = PaymentTransaction::with(['details.coupon', 'issued_coupons', 'user'])->findOrFail($payment_transaction_id);

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

            if (! in_array($oldStatus, $finalStatus)) {
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

                // Denied payment

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

                $payment_update->responded_at = Carbon::now('UTC');

                // Link this payment to reserved IssuedCoupon.
                if ($payment_update->issued_coupons->count() === 0) {
                    Log::info("PaidCoupon: Can not link issued coupons to this payment! Must be removed by CheckReservedQueue.");
                    $payment_update->status = PaymentTransaction::STATUS_FAILED;
                    $failed = true;
                }

                // If payment is success and not with credit card (not realtime) or the payment for Sepulsa voucher,
                // then we assume the status as success_no_coupon (so frontend will show preparing voucher page).
                if ($status === PaymentTransaction::STATUS_SUCCESS) {
                    if (isset($failed)) {
                        $payment_update->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                    }
                }

                $payment_update->save();

                // Commit the changes ASAP so if there are any other requests that trigger this controller
                // they will use the updated payment data/status.
                // Try not doing any expensive operation above.
                $this->commit();

                // Log activity...
                // Should be done before issuing coupon for the sake of activity ordering,
                // or at the end before returning the response??
                if ($payment_update->failed() || $payment_update->denied()) {
                    $activity->setActivityNameLong('Transaction is Failed')
                            ->setModuleName('Stripe Transaction')
                            ->setObject($payment_update)
                            ->setNotes('Transaction is failed from Stripe/Customer.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment_update->expired()) {
                    $activity->setActivityNameLong('Transaction is Expired')
                            ->setModuleName('Stripe Transaction')
                            ->setObject($payment_update)
                            ->setNotes('Transaction is expired from Stripe.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }
                else if ($payment_update->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON) {
                    $activity->setActivityNameLong('Transaction is Success - Getting Coupon')
                            ->setModuleName('Stripe Transaction')
                            ->setNotes($payment_update->details->first()->coupon->promotion_type)
                            ->setObject($payment_update)
                            ->setLocation($mall)
                            ->responseOK()
                            ->save();
                }
                else if ($payment_update->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED) {
                    $activity->setActivityNameLong('Transaction is Success - Failed Getting Coupon')
                            ->setModuleName('Stripe Transaction')
                            ->setObject($payment_update)
                            ->setNotes('Failed to get coupon. Can not get reserved coupons for this transaction.')
                            ->setLocation($mall)
                            ->responseFailed()
                            ->save();
                }

                Event::fire('orbit.payment-stripe.postupdatepayment.after.commit', [$payment_update, $mall]);

                $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

                // If previous status was starting and now is pending, we should trigger job transaction status check.
                // The job will be run forever until the transaction status is success, failed, expired or reached the maximum number of allowed check.

                // Send notification if the purchase was canceled.
                // Only send if previous status was pending.

                // If previous status was success and now is denied, then send notification to admin.
                // Maybe it means the payment was reversed/canceled by customer after paying.

                // If Payment is suspicious, then notify admin.
                // @todo should only send this once.
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
