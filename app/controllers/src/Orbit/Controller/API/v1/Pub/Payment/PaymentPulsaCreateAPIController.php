<?php namespace Orbit\Controller\API\v1\Pub\Payment;

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
use Pulsa;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;
use PaymentMidtrans;
use Mall;
use Activity;
use Country;
use Carbon\Carbon as Carbon;

/**
 * Create payment record for Pulsa purchase.
 *
 * Intentionally uses a different class/handler to prevent breaking
 * current implementation.
 *
 * @author Budi <budi@dominopos.com>
 */
class PaymentPulsaCreateAPIController extends PubControllerAPI
{
    public function postPaymentPulsaCreate()
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

            $this->registerCustomValidation();

            $user_id = $user->user_id;
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone = OrbitInput::post('phone');
            $pulsa_phone = OrbitInput::post('pulsa_phone');
            $country_id = OrbitInput::post('country_id');
            $quantity = OrbitInput::post('quantity');
            $amount = OrbitInput::post('amount');
            $mall_id = OrbitInput::post('mall_id', 'gtm');
            $currency_id = OrbitInput::post('currency_id', '1');
            $currency = OrbitInput::post('currency', 'IDR');
            $post_data = OrbitInput::post('post_data');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $object_name = OrbitInput::post('object_name');
            $user_name = (!empty($last_name) ? $first_name.' '.$last_name : $first_name);
            $mallId = OrbitInput::post('mall_id', null);

            $validator = Validator::make(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'pulsa_phone'      => $pulsa_phone,
                    'amount'     => $amount,
                    'post_data'  => $post_data,
                    'mall_id'    => $mall_id,
                    'object_id'  => $object_id,
                    'object_type'  => $object_type,
                    'quantity'   => $quantity,
                ),
                array(
                    'first_name' => 'required',
                    'last_name'  => 'required',
                    'email'      => 'required',
                    'phone'      => 'required',
                    'pulsa_phone'      => 'required',
                    'amount'     => 'required',
                    'post_data'  => 'required',
                    'mall_id'    => 'required',
                    'object_type'  => 'required',
                    'object_id'  => 'required|orbit.exists.pulsa',
                    'quantity'   => 'required',
                ),
                array(
                    'orbit.allowed.quantity' => 'REQUESTED_QUANTITY_NOT_AVAILABLE',
                    'orbit.exists.pulsa' => 'Pulsa does not exists.',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            // Get coupon detail from DB.
            $pulsa = Pulsa::select('pulsa_item_id', 'price')->findOrFail($object_id);

            // Resolve country_id
            if ($country_id !== '0' || $country_id !== 0) {
                $country = Country::where('name', $country_id)->first();
                if (! empty($country)) {
                    $country_id = $country->country_id;
                }
            }

            // Get mall timezone
            $mallTimeZone = 'Asia/Jakarta';
            $mall = null;
            if ($mall_id !== 'gtm') {
                $mall = Mall::where('merchant_id', $mall_id)->first();
                if (!empty($mall)) {
                    $mallTimeZone = $mall->getTimezone($mall_id);
                    $country_id = $mall->country_id;
                }
            }

            $payment_new = new PaymentTransaction;
            $payment_new->user_email = $email;
            $payment_new->user_name = $user_name;
            $payment_new->user_id = $user_id;
            $payment_new->phone = $phone;
            $payment_new->country_id = $country_id;
            $payment_new->payment_method = 'midtrans';
            $payment_new->amount = $quantity * $pulsa->price;
            $payment_new->currency = $currency;
            $payment_new->status = PaymentTransaction::STATUS_STARTING;
            $payment_new->timezone_name = $mallTimeZone;
            $payment_new->post_data = serialize($post_data);
            $payment_new->extra_data = $this->cleanPhone($pulsa_phone);

            $payment_new->save();

            // Insert detail information
            $paymentDetail = new PaymentTransactionDetail;
            $paymentDetail->payment_transaction_id = $payment_new->payment_transaction_id;
            $paymentDetail->currency = $currency;
            $paymentDetail->price = $pulsa->price;
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

            $paymentDetail->save();

            // Insert normal/paypro details
            $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
            $paymentDetail->normal_paypro_detail()->save($paymentDetailNormalPaypro);

            // Insert midtrans info
            $paymentMidtransDetail = new PaymentMidtrans;
            $payment_new->midtrans()->save($paymentMidtransDetail);

            // Commit the changes
            $this->commit();

            // TODO: Log activity
            $activity = Activity::mobileci()
                    ->setActivityType('transaction')
                    ->setUser($user)
                    ->setActivityName('transaction_status')
                    ->setActivityNameLong('Transaction is Starting')
                    ->setModuleName('Midtrans Transaction')
                    ->setObject($payment_new)
                    ->setNotes('Pulsa')
                    ->setLocation($mall)
                    ->responseOK()
                    ->save();

            $payment_new->quantity = $quantity;

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

    /**
     * Register custom validation.
     *
     * @return [type] [description]
     */
    private function registerCustomValidation()
    {
        $user = $this->getUser();

        // Check if pulsa is exists.
        Validator::extend('orbit.exists.pulsa', function ($attribute, $value, $parameters) {
            $prefix = DB::getTablePrefix();
            $pulsa = Pulsa::where('pulsa_item_id', $value)->where('status', 'active')->first();

            if (empty($pulsa)) {
                return false;
            }

            \App::instance('orbit.instance.pulsa', $pulsa);

            return true;
        });

        /**
         * Check if pulsa still available.
         */
        Validator::extend('orbit.allowed.quantity', function ($attribute, $requestedQuantity, $parameters) use ($user) {

            $pulsaId = OrbitInput::post('object_id');

            // $pulsa = \App::make('orbit.instance.pulsa');

            // Globally issued coupon count regardless of the Customer.
            $issuedPulsa = PaymentTransaction::select(
                    'payment_transactions.payment_transaction_id',
                    'payment_transaction_details.object_id',
                    'payment_transactions.user_id'
                )
                ->join('payment_transaction_details', 'payment_transactions.payment_transaction_id', '=', 'payment_transaction_details.payment_transaction_id')
                ->where('payment_transaction_details.object_type', 'pulsa')
                // ->where('payment_transaction_details.object_id', $pulsaId)
                ->whereIn('payment_transactions.status', [
                    PaymentTransaction::STATUS_SUCCESS,
                    PaymentTransaction::STATUS_SUCCESS_NO_COUPON,
                    PaymentTransaction::STATUS_SUCCESS_NO_PULSA,
                    // PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED,
                ])
                ->get();

            $issuedPulsaForUser = false;
            foreach($issuedPulsa as $issuedPulsaItem) {
                if ($issuedPulsaItem->user_id === $user->user_id) {
                    $issuedPulsaForUser = true;
                }
            }

            return $requestedQuantity <= $issuedPulsa && ! $issuedPulsaForUser;
        });
    }

    /**
     * Clean characters other than number 0-9.
     * Also replace '62' with '0'.
     *
     * @param  string $phoneNumber [description]
     * @return [type]              [description]
     */
    private function cleanPhone($phoneNumber = '')
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        $phoneNumber = preg_replace('/^[6][2]/', '0', $phoneNumber);

        return $phoneNumber;
    }
}
