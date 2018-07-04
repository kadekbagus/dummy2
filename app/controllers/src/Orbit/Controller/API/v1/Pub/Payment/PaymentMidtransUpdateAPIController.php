<?php namespace Orbit\Controller\API\v1\Pub\Payment;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for update payment with midtrans
 */

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
use IssuedCoupon;
use User;
use Carbon\Carbon as Carbon;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Event;

use Orbit\Notifications\Payment\SuspiciousPaymentNotification;

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
            $payment_update = PaymentTransaction::with(['coupon', 'issued_coupon'])->findOrFail($payment_transaction_id);

            $oldStatus = $payment_update->status;

            // if payment transaction already success don't update again
            $finalStatus = [
            	PaymentTransaction::STATUS_SUCCESS,
            	PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
            	PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED,
                PaymentTransaction::STATUS_EXPIRED,
                PaymentTransaction::STATUS_FAILED,
                PaymentTransaction::STATUS_DENIED,
            ];

			if ($status == PaymentTransaction::STATUS_DENIED) {
            	if (($key = array_search(PaymentTransaction::STATUS_SUCCESS, $finalStatus)) !== false) {
				    unset($finalStatus[$key]);
				}

				if (($key = array_search(PaymentTransaction::STATUS_SUCCESS_NO_COUPON, $finalStatus)) !== false) {
				    unset($finalStatus[$key]);
				}

				if (($key = array_search(PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED, $finalStatus)) !== false) {
				    unset($finalStatus[$key]);
				}
            }

            if (! in_array($oldStatus, $finalStatus)) {

                // Supicious payment should be treated as pending payment.
                if ($status === PaymentTransaction::STATUS_SUSPICIOUS) {
                    $status = PaymentTransaction::STATUS_PENDING;

                    // Flag to send suspicious payment notification.
                    $paymentSuspicious = true;
                    $payment->notes = $payment->notes . 'Payment suspicious.' . "\n----\n";
                }

                if ($status !== 'dont-update') {
                    $payment_update->status = $status;
                }

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

                // Update transaction_id in the issued coupon record related to this payment.
                if (empty($payment->issued_coupon)) {
                	IssuedCoupon::where('user_id', $payment_update->user_id)
                				  ->where('promotion_id', $payment_update->object_id)
                				  ->where('status', IssuedCoupon::STATUS_RESERVED)
                				  ->update(['transaction_id' => $payment_transaction_id]);
                }

                // If payment is success, then we should assume the status to be success but no coupon.
                if ($status === PaymentTransaction::STATUS_SUCCESS) {
                    $payment_update->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                }

		        $payment_update->save();

		        // Commit the changes
	            $this->commit();

	            Event::fire('orbit.payment.postupdatepayment.after.commit', [$payment_update]);

	            // If status before is starting and now is pending, we should trigger job transaction status check.
	            // The job will be run forever until the transaction status is success, failed, expired or reached the maximum number of check.
	            if ($oldStatus === PaymentTransaction::STATUS_STARTING && $status === PaymentTransaction::STATUS_PENDING) {
	                $delay = Config::get('orbit.partners_api.midtrans.transaction_status_timeout', 60);
	                Queue::later(
	                    $delay,
	                    'Orbit\\Queue\\Payment\\Midtrans\\CheckTransactionStatusQueue',
	                    ['transactionId' => $payment_update->external_payment_transaction_id, 'check' => 0]
	                );

	                Log::info('PaidCoupon: First time TransactionStatus check is scheduled to run in ' . $delay . ' seconds.');
	            }

                // If Payment is suspicious, then notify admin.
                // @todo should only send this once.
                if ($paymentSuspicious) {

                    $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

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
