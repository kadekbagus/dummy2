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
            $user = $this->getUser();
            $user_id = $user->user_id;
            $user_email = $user->user_email;
            $couponHelper = CouponHelper::create();
            $couponHelper->setUser($user);
            $couponHelper->couponCustomValidator();

            $discount_code = OrbitInput::post('discount_code');
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
                    'discount_code'    => $discount_code,
                    'user_id'          => $user_id,
                ),
                array(
                    'object_id'        => 'required',
                    'first_name'       => 'required',
                    'last_name'        => 'required',
                    'email'            => 'required',
                    'phone'            => 'required',
                    'discount_code'    => 'required|orbit.exists.promo_code',
                    'user_id'          => 'orbit.validate.user_id',
                ),
                array(
                    'orbit.exists.promo_code' => 'discount code already used',
                    'orbit.validate.user_id'  => 'user already use discount code'
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::select('price_selling', 'promotion_id', 'promotion_type')->find($object_id);

            $mallTimeZone = 'Asia/Jakarta';
            $mall = null;
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
            $payment_new->payment_method = 'discount code';
            $payment_new->amount = $quantity * $coupon->price_selling;
            $payment_new->currency = $currency;
            $payment_new->status = PaymentTransaction::STATUS_SUCCESS;
            $payment_new->timezone_name = $mallTimeZone;
            $payment_new->post_data = serialize($post_data);
            $payment_new->save();



            //$queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';

            /*
            $newIssuedCoupon = new IssuedCoupon();
            $newIssuedCoupon->promotion_id = $coupon_id;
            $newIssuedCoupon->issued_coupon_code = '';
            $newIssuedCoupon->user_id = $user_id;
            $newIssuedCoupon->user_email = $user_email;
            $newIssuedCoupon->issued_date = date('Y-m-d H:i:s');
            $newIssuedCoupon->status = IssuedCoupon::STATUS_ISSUED;
            $newIssuedCoupon->save();

            $newPromoCode = new TmpPromoCode();
            $newPromoCode->promo_code = $discount_code;
            $newPromoCode->coupon_id = $coupon_id;
            $newPromoCode->user_id = $user_id;
            $newPromoCode->issued_coupon_id = $newIssuedCoupon->issued_coupon_id;
            $newPromoCode->save();
            */

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

        $output = $this->render($httpCode);

        return $output;
    }
}
