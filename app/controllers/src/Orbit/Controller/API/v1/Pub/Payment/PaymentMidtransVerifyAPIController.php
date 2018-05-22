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

    public function postPaymentMidtransVerify()
    {
	    $httpCode = 200;
	    try {
	    	$this->checkAuth();
	    	$user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

	        $user_id = $user->user_id;
	        $transaction_id = OrbitInput::post('transaction_id');
	        $amount = OrbitInput::post('amount');
	        $response_status = OrbitInput::post('response_status');

	        $validator = Validator::make(
	            array(
	                'transaction_id'  => $transaction_id,
	                'amount' 		  => $amount,
	                'response_status' => $response_status
	            ),
	            array(
	                'transaction_id'  => 'required',
	                'amount'	      => 'required',
	                'response_status' => 'required|in:success,failed'
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
	        $payment = PaymentTransaction::select('external_payment_transaction_id', 'amount', 'status')
	        							 ->where('payment_transaction_id', '=', $transaction_id)
	        							 ->where('user_id', '=', $user_id)
	        							 ->where('amount', '=', $amount)
	        							 ->where('status', '=', 'pending')
	        							 ->first();

	 		if (empty($payment)) {
	 			$errorMessage = 'Transaction not found';
	 			OrbitShopAPI::throwInvalidArgument($errorMessage);
	 		}

	 		// update payment status
	 		$payment_update = PaymentTransaction::where('payment_transaction_id', '=', $transaction_id)->first();

	 		OrbitInput::post('response_status', function($response_status) use ($payment_update) {
                $payment_update->status = $response_status;
            });

            $payment_update->save();

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