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
use Carbon\Carbon as Carbon;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Event;

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
	                'status'                   => 'required|in:pending,success,failed,expired'
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

	        $payment_update = PaymentTransaction::with(['coupon', 'coupon_sepulsa', 'issued_coupon', 'user'])
	        									->where('payment_transaction_id', '=', $payment_transaction_id)
	        									->first();

            $oldStatus = $payment_update->status;

            // if payment transaction already success don't update again
            $successStatus = [
            	PaymentTransaction::STATUS_SUCCESS, 
            	PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
            	PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED,
            ];

            if (! in_array($oldStatus, $successStatus)) {

		        OrbitInput::post('status', function($status) use ($payment_update) {
	                $payment_update->status = $status;
	            });

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
		        $payment_update->save();

	            Event::fire('orbit.payment.postupdatepayment.after.save', [$payment_update]);

		        // Commit the changes
	            $this->commit();

	            $payment_update->load('issued_coupon');

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

	                Log::info('First time TransactionStatus check is scheduled to run in ' . $delay . ' seconds.');
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
