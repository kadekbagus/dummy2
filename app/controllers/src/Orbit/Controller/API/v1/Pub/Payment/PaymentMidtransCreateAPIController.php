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
use Coupon;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;
use PaymentMidtrans;
use IssuedCoupon;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Controller\API\v1\Pub\Payment\PaymentHelper;
use Mall;
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

            CouponHelper::create()->couponCustomValidator();
            PaymentHelper::create()->registerCustomValidation();

            $user_id = $user->user_id;
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone = OrbitInput::post('phone');
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

            $validator = Validator::make(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'quantity'   => $quantity,
                    'amount'     => $amount,
                    'post_data'  => $post_data,
                    'mall_id'    => $mall_id,
                    'object_id'  => $object_id,
                ),
                array(
                    'first_name' => 'required',
                    'last_name'  => 'required',
                    'email'      => 'required',
                    'phone'      => 'required',
                    'quantity'   => 'required|orbit.allowed.quantity',
                    'amount'     => 'required',
                    'post_data'  => 'required',
                    'mall_id'    => 'required',
                    'object_id'  => 'required|orbit.exists.coupon',
                ),
                array(
                    'orbit.allowed.quantity' => 'Requested quantity is not available.',
                    'orbit.exists.coupon' => 'Coupon does not exists.',
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
            $coupon = Coupon::select('price_selling')->find($object_id);

            // Get mall timezone
            $mallTimeZone = 'Asia/Jakarta';
            if ($mall_id !== 'gtm') {
                $mall = Mall::where('merchant_id', $mall_id)->first();
                if (!empty($mall)) {
                    $mallTimeZone = $mall->getTimezone($mall_id);
                }
            }

            $payment_new = new PaymentTransaction;
            $payment_new->user_email = $email;
            $payment_new->user_name = $user_name;
            $payment_new->user_id = $user_id;
            $payment_new->phone = $phone;
            $payment_new->country_id = $country_id;
            $payment_new->payment_method = 'midtrans';
            $payment_new->amount = $quantity * $coupon->price_selling;
            $payment_new->currency = $currency;
            $payment_new->status = PaymentTransaction::STATUS_STARTING;
            $payment_new->timezone_name = $mallTimeZone;
            $payment_new->post_data = serialize($post_data);

            $payment_new->save();

            // Insert detail information
            $paymentDetail = new PaymentTransactionDetail;
            $paymentDetail->payment_transaction_id = $payment_new->payment_transaction_id;
            $paymentDetail->currency = $currency;
            $paymentDetail->price = $coupon->price_selling;
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

            // Link this payment to reserved coupons according to requested quantity.
            IssuedCoupon::where('user_id', $user_id)
                ->where('promotion_id', $object_id)
                ->where('transaction_id', NULL)
                ->where('status', IssuedCoupon::STATUS_RESERVED)
                ->skip(0)->take($quantity)
                ->update(['transaction_id' => $payment_new->payment_transaction_id]);

            // Commit the changes
            $this->commit();

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

}
