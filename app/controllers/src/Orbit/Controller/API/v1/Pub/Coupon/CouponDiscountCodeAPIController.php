<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Coupon;
use PromotionRetailer;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use URL;
use Validator;
use Activity;
use Language;
use Lang;
use CouponRetailer;
use MerchantStorePaymentProvider;
use BaseMerchant;
use BaseStore;
use Currency;
use Carbon\Carbon;
use IssuedCoupon;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Helper\Security\Encrypter;
use \Queue;
use \App;
use \Exception;
use \UserVerificationNumber;
use Orbit\Helper\Payment\Payment as PaymentClient;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;

class CouponDiscountCodeAPIController extends PubControllerAPI
{
    /**
     * POST - Coupon Discount Code Quick & Dirty
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string      `cid`                             (required) - Hashed issued coupon ID
     * @param string      `uid`                             (optional) - Hashed user identifier
     * @param string      `merchant_id`                     (required) - ID of the mall
     * @param string      `merchant_verification_number`    (required) - Merchant/Tenant verification number
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCouponDiscountCode()
    {
        $user = NULL;
        $mall = NULL;
        $mall_id = NULL;
        $issuedcoupon = NULL;
        $coupon = NULL;
        $httpCode = 200;

        try {
            $this->checkAuth();
            $user = $this->api->user;
            $user_id = $user->user_id;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $couponHelper = CouponHelper::create();
            $couponHelper->setUser($user);
            $couponHelper->couponCustomValidator();

            $discount_code = strtolower(OrbitInput::post('discount_code'));
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone = OrbitInput::post('phone');
            $quantity = OrbitInput::post('quantity', 1);
            $mall_id = OrbitInput::post('mall_id', 'gtm');
            $currency = OrbitInput::post('currency', 'IDR');
            $country_id = OrbitInput::post('country_id');
            $post_data = OrbitInput::post('post_data', '');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $object_name = OrbitInput::post('object_name');
            $user_name = (!empty($last_name) ? $first_name.' '.$last_name : $first_name);

            $validator = Validator::make(
                array(
                    'object_id'        => $object_id,
                    'first_name'       => $first_name,
                    'last_name'        => $last_name,
                    'email'            => $email,
                    'phone'            => $phone,
                    'quantity'         => $quantity,
                    'discount_code'    => $discount_code,
                    'user_id'          => $user_id,
                ),
                array(
                    'object_id'        => 'required',
                    'first_name'       => 'required',
                    'last_name'        => 'required',
                    'email'            => 'required',
                    'phone'            => 'required',
                    'quantity'         => 'required|orbit.validate.quantity',
                    'discount_code'    => 'required|orbit.exists.promo_code',
                    'user_id'          => 'orbit.validate.user_id',
                ),
                array(
                    'orbit.exists.promo_code' => 'ERROR_DISCOUNT_CODE_USED',
                    'orbit.validate.user_id'  => 'ERROR_USER_ALREADY_USE_CODE',
                    'orbit.validate.quantity' => 'ERROR_MAXIMUM_QUANTITY'
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::select('price_selling', 'promotion_id', 'promotion_type', 'promotion_name')->find($object_id);

            $mallTimeZone = 'Asia/Jakarta';
            $mall = null;
            if ($mall_id !== 'gtm') {
                $mall = Mall::where('merchant_id', $mall_id)->first();
                if (!empty($mall)) {
                    $mallTimeZone = $mall->getTimezone($mall_id);
                }
            }

            $paymentNew = new PaymentTransaction;
            $paymentNew->user_email = $email;
            $paymentNew->user_name = $user_name;
            $paymentNew->user_id = $user_id;
            $paymentNew->phone = $phone;
            $paymentNew->country_id = $country_id;
            $paymentNew->payment_method = 'discount code';
            $paymentNew->amount = $quantity * $coupon->price_selling;
            $paymentNew->currency = $currency;
            $paymentNew->status = PaymentTransaction::STATUS_SUCCESS;
            $paymentNew->timezone_name = $mallTimeZone;
            $paymentNew->post_data = serialize($post_data);
            $paymentNew->save();

            $paymentDetail = new PaymentTransactionDetail;
            $paymentDetail->payment_transaction_id = $paymentNew->payment_transaction_id;
            $paymentDetail->currency = $currency;
            $paymentDetail->price = $coupon->price_selling;
            $paymentDetail->quantity = $quantity;
            $paymentDetail->object_id = $coupon->promotion_id;
            $paymentDetail->object_type = 'coupon';
            $paymentDetail->object_name = $coupon->promotion_name;
            $paymentDetail->save();

            // Insert normal/paypro details
            $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
            $paymentDetail->normal_paypro_detail()->save($paymentDetailNormalPaypro);

            $issuedCoupon = new IssuedCoupon;
            $issuedCoupon->promotion_id   = $coupon->promotion_id;
            $issuedCoupon->transaction_id = $paymentNew->payment_transaction_id;
            $issuedCoupon->user_id        = $user->user_id;
            $issuedCoupon->user_email     = $user->user_email;
            $issuedCoupon->issued_date    = date('Y-m-d H:i:s');
            $issuedCoupon->status         = IssuedCoupon::STATUS_RESERVED;
            $issuedCoupon->record_exists  = 'Y';
            $issuedCoupon->save();

            // Commit the changes
            $this->commit();

            $queueData = ['paymentId' => $paymentNew->payment_transaction_id,
                            'retries' => 0,
                       'discountCode' => $discount_code,
                             'userId' => $user->user_id,
                           'couponId' => $coupon->promotion_id,
                     'issuedCouponId' => $issuedCoupon->issued_coupon_id];

            $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
            $delay = 0;

            Queue::connection('sync')->later(
                $delay, $queue,
                $queueData
            );

            $this->response->data = $paymentNew;
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

        $output = $this->render($httpCode);

        return $output;
    }
}
