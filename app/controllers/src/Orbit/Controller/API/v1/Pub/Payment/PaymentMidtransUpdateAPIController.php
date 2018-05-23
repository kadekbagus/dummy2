<?php namespace Orbit\Controller\API\v1\Pub\Payment;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for update payment with midtrans
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use Validator;
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
	                'payment_transaction_id'  => $payment_transaction_id,
	                'status'  => $status,
	            ),
	            array(
	                'payment_transaction_id'  => 'required|orbit.exist.payment_transaction_id',
	                'status'  => 'required|in:pending,success,failed'
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

	        $payment_update = PaymentTransaction::with(['coupon_sepulsa.coupon'])->where('payment_transaction_id', '=', $payment_transaction_id)
												->whereIn('status', ['starting', 'pending'])
	        									->first();

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

	        $payment_update->responded_at = Carbon::now('UTC');
	        $payment_update->save();

            // Issue Coupon if meet the requirement.
            Event::fire('orbit.payment.postupdatepayment.after.save', [$payment_update]);

	        // Commit the changes
            $this->commit();

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