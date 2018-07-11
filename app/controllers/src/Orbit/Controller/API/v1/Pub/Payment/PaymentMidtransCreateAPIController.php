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
use PaymentTransactionDetail;
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

            $user_id = $user->user_id;
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone = OrbitInput::post('phone');
            $country_id = OrbitInput::post('country_id');
            $quantity = OrbitInput::post('quantity', 1);
            $amount = OrbitInput::post('amount');
            $currency_id = OrbitInput::post('currency_id', '1');
            $currency = OrbitInput::post('currency', 'IDR');
            $post_data = OrbitInput::post('post_data');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $object_name = OrbitInput::post('object_name');
            $user_name = (!empty($last_name) ? $first_name.' '.$last_name : $first_name);

            $validator = Validator::make(
                array(
                    'first_name'  => $first_name,
                    'last_name'   => $last_name,
                    'email'       => $email,
                    'phone'       => $phone,
                    'amount'      => $amount,
                    'post_data'   => $post_data,
                ),
                array(
                    'first_name'  => 'required',
                    'last_name'   => 'required',
                    'email'       => 'required',
                    'phone'       => 'required',
                    'amount'      => 'required',
                    'post_data'   => 'required',
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
            $payment_new->user_email = $email;
            $payment_new->user_name = $user_name;
            $payment_new->user_id = $user_id;
            $payment_new->phone = $phone;
            $payment_new->country_id = $country_id;
            // $payment_new->payment_provider_id = $payment_provider_id;
            $payment_new->payment_method = 'midtrans';
            $payment_new->amount = $amount;
            // $payment_new->currency_id = $currency_id;
            $payment_new->currency = $currency;
            $payment_new->status = 'starting';
            $payment_new->timezone_name = 'UTC';
            // $payment_new->transaction_date_and_time = Carbon::now('UTC');
            $payment_new->post_data = serialize($post_data);

            $payment_new->save();

            // Insert detail information
            $paymentDetail = new PaymentTransactionDetail;
            $paymentDetail->currency = $currency;
            $paymentDetail->price = $amount;
            $paymentDetail->quantity = $quantity;

            OrbitInput::post('object_id', function($object_id) use ($paymentDetail) {
                $paymentDetail->object_id = $object_id;
            });

            OrbitInput::post('object_type', function($object_type) use ($paymentDetail) {
                $paymentDetail->object_type = $object_type;
            });

            OrbitInput::post('object_name', function($object_name) use ($paymentDetail) {
                $paymentDetail->object_name = $object_name;
            });

            $payment_new->details()->save($paymentDetail);

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