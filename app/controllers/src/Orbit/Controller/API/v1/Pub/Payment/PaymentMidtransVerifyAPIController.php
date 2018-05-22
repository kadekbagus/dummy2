<?php namespace Orbit\Controller\API\v1\Pub\Payment;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for verifying payment with midtrans
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

class PaymentMidtransVerifyAPIController extends PubControllerAPI
{

    public function getPaymentMidtransVerify()
    {
	    $httpCode = 200;
	    try {
	    	$this->checkAuth();
	    	$user = $this->api->user;

	        $external_payment_transaction_id = OrbitInput::get('external_payment_transaction_id');

	        $validator = Validator::make(
	            array(
	                'external_payment_transaction_id'  => $external_payment_transaction_id
	            ),
	            array(
	                'external_payment_transaction_id'  => 'required'
	            )
	        );

	      	// Begin database transaction
            $this->beginTransaction();

	        // Run the validation
	        if ($validator->fails()) {
	            $errorMessage = $validator->messages()->first();
	            OrbitShopAPI::throwInvalidArgument($errorMessage);
	        }

	        // validate payment data
	        $payment = PaymentTransaction::select('payment_transaction_id', 'external_payment_transaction_id', 'amount', 'status')
	        							 ->where('external_payment_transaction_id', '=', $external_payment_transaction_id)
	        							 ->first();

	 		if (empty($payment)) {
	 			$httpCode = 404;
	 			$this->response->data = null;
		        $this->response->code = 404;
		        $this->response->status = 'error';
		        $this->response->message = 'Transaction not found';
	 		} else {
	 			$this->response->data = $payment;
		        $this->response->code = 0;
		        $this->response->status = 'success';
		        $this->response->message = 'Request OK';
	 		}

	    } catch (ACLForbiddenException $e) {
	        $this->response->code = $e->getCode();
	        $this->response->status = 'error';
	        $this->response->message = $e->getMessage();
	        $this->response->data = null;
	        $httpCode = 403;
	    } catch (InvalidArgsException $e) {
	        $this->response->code = $e->getCode();
	        $this->response->status = 'error';
	        $this->response->message = $e->getMessage();
	        $this->response->data = null;
	        $httpCode = 403;
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
	    } catch (Exception $e) {
	        $this->response->code = $this->getNonZeroCode($e->getCode());
	        $this->response->status = 'error';
	        $this->response->message = $e->getMessage();
	        $this->response->data = null;
	        $httpCode = 500;
	    }

	    return $this->render($httpCode);
    }
}