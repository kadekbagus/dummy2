<?php namespace Orbit\Controller\API\v1\Pub\Payment;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for create payment with midtrans
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

class PaymentMidtransCreateAPIController extends PubControllerAPI
{

    public function postPaymentMidtransCreate()
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

	        $user_email = $user->user_email;
	        $user_name = $user->user_firstname.' '.$user->user_lastname;
	        $user_id = $user->user_id;
	        $country_id = OrbitInput::post('country_id', '101');
	        $phone = OrbitInput::post('phone');
	        $amount = OrbitInput::post('amount');

	        $validator = Validator::make(
	            array(
	                'phone'  => $phone,
	                'amount' => $amount,
	            ),
	            array(
	                'phone'  => 'required',
	                'amount' => 'required',
	            )
	        );

	        // Begin database transaction
            $this->beginTransaction();

	        // Run the validation
	        if ($validator->fails()) {
	            $errorMessage = $validator->messages()->first();
	            OrbitShopAPI::throwInvalidArgument($errorMessage);
	        }

	        $payment_new = new PaymentTransaction;
	        $payment_new->user_email = $user_email;
	        $payment_new->user_name = $user_name;
	        $payment_new->user_id = $user_id;
	        $payment_new->country_id = $country_id;
	        $payment_new->transaction_date_and_time = Carbon::now('UTC');
	        $payment_new->amount = $amount;
	        $payment_new->payment_method = 'midtrans';
	        $payment_new->currency_id = '1';
	        $payment_new->currency = 'IDR';
	        $payment_new->status = 'pending';
	        $payment_new->timezone_name = 'UTC';
	        $payment_new->save();

	        // Commit the changes
            $this->commit();

	        $this->response->data = $payment_new;
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