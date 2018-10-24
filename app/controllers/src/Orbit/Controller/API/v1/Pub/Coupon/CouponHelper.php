<?php namespace Orbit\Controller\API\v1\Pub\Coupon;
/**
 * Helpers for specific Coupon Namespace
 *
 */
use Validator;
use Language;
use Coupon;
use DB;
use CouponRetailer;
use Carbon\Carbon;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use IssuedCoupon;
use Mall;
use App;
use Config;
use Orbit\Helper\Security\Encrypter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Lang;
use OrbitShop\API\v1\OrbitShopAPI;
use TmpPromoCode;

class CouponHelper
{
    protected $valid_language = NULL;
    protected $session = NULL;
    protected $user = NULL;

    public function __construct($session = NULL)
    {
        $this->session = $session;
    }

    /**
     * Static method to instantiate the class.
     */
    public static function create($session = NULL)
    {
        return new static($session);
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Pub\Coupon namespace
     *
     */
    public function couponCustomValidator() {
        // Check the existance of issued coupon id
        Validator::extend('orbit.empty.issuedcoupon', function ($attribute, $value, $parameters) {

            // decrypt hashed issued coupon id
            try {
                $encryptionKey = Config::get('orbit.security.encryption_key');
                $encryptionDriver = Config::get('orbit.security.encryption_driver');
                $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

                $value = $encrypter->decrypt($value);
            } catch (Exception $e) {
                $errorMessage = 'Invalid cid';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $now = date('Y-m-d H:i:s');
            $number = OrbitInput::post('merchant_verification_number');
            $mall_id = OrbitInput::post('mall_id');

            $prefix = DB::getTablePrefix();

            $issuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                        ->where('issued_coupons.issued_coupon_id', $value)
                        ->with('coupon')
                        ->whereHas('coupon', function($q) use($now) {
                            $q->where('promotions.status', 'active');
                            $q->where('promotions.coupon_validity_in_date', '>=', $now);
                        })
                        ->first();

            if (empty($issuedCoupon)) {
                $errorMessage = 'Issued coupon ID is not found.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            //Checking verification number in cs and tenant verification number
            //Checking in tenant verification number first
            if ($issuedCoupon->coupon->is_all_retailer === 'Y') {
                $checkIssuedCoupon = Tenant::where('parent_id','=', $mall_id)
                            ->where('status', 'active')
                            ->where('masterbox_number', $number)
                            ->first();
            } elseif ($issuedCoupon->coupon->is_all_retailer === 'N') {
                $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                            ->join('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where('issued_coupons.issued_coupon_id', $value)
                            ->whereHas('coupon', function($q) use($now) {
                                $q->where('promotions.status', 'active');
                                $q->where('promotions.coupon_validity_in_date', '>=', $now);
                            })
                            ->where('merchants.masterbox_number', $number)
                            ->first();
            }

            // Continue checking to tenant verification number
            if (empty($checkIssuedCoupon)) {
                // Checking cs verification number
                if ($issuedCoupon->coupon->is_all_employee === 'Y') {
                    $checkIssuedCoupon = UserVerificationNumber::join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('status', 'active')
                                ->where('merchant_id', $mall_id)
                                ->where('verification_number', $number)
                                ->first();
                } elseif ($issuedCoupon->coupon->is_all_employee === 'N') {
                    $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                                ->join('promotion_employee', 'promotion_employee.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->join('user_verification_numbers', 'user_verification_numbers.user_id', '=', 'promotion_employee.user_id')
                                ->join('employees', 'employees.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('employees.status', 'active')
                                ->where('issued_coupons.issued_coupon_id', $value)
                                ->whereHas('coupon', function($q) use($now) {
                                    $q->where('promotions.status', 'active');
                                    $q->where('promotions.coupon_validity_in_date', '>=', $now);
                                })
                                ->where('user_verification_numbers.verification_number', $number)
                                ->first();
                }
            }

            if (! isset($checkIssuedCoupon) || empty($checkIssuedCoupon)) {
                $errorMessage = Lang::get('mobileci.coupon.wrong_verification_number');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($checkIssuedCoupon)) {
                App::instance('orbit.empty.issuedcoupon', $issuedCoupon);
            }

            return TRUE;
        });

        // Check issued_coupon_code if exists in SMS coupon
        Validator::extend('orbit.exists.issued_coupon_code_sms', function ($attribute, $value, $parameters) {
            $promotionId = $parameters[0];

            $issuedCoupon = IssuedCoupon::
                join('promotions', 'promotions.promotion_id', '=', 'issued_coupons.promotion_id')
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id')
                ->where('issued_coupons.status', 'available')
                ->where('promotion_rules.rule_type', 'blast_via_sms')
                ->where('issued_coupons.promotion_id', $promotionId)
                ->where('issued_coupon_code', $value)
                ->first();

            if (empty($issuedCoupon)) {
                return FALSE;
            }

            return true;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::with('timezone')->excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check coupon, it should exists
        Validator::extend(
            'orbit.exists.coupon',
            function ($attribute, $value, $parameters) {
                $prefix = DB::getTablePrefix();
                // use nearest mall to check the eligibility
                $nearestMallByTimezoneOffset = CouponRetailer::selectRaw("
                        CASE WHEN {$prefix}promotion_retailer.object_type = 'tenant' THEN mall.merchant_id ELSE {$prefix}merchants.merchant_id END as id,
                        CASE WHEN {$prefix}promotion_retailer.object_type = 'tenant' THEN mall.name ELSE {$prefix}merchants.name END as name,
                        mall.timezone_id,
                        {$prefix}promotion_retailer.object_type,
                        {$prefix}timezones.timezone_name,
                        TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), CONVERT_TZ(UTC_TIMESTAMP(), 'Etc/UTC', {$prefix}timezones.timezone_name)) as offset
                    ")
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                    ->leftJoin(DB::raw("{$prefix}merchants as mall"), DB::raw('mall.merchant_id'), '=', 'merchants.parent_id')
                    ->leftJoin('timezones', DB::raw("CASE WHEN {$prefix}promotion_retailer.object_type = 'tenant' THEN mall.timezone_id ELSE {$prefix}merchants.timezone_id END"), '=', 'timezones.timezone_id')
                    ->where('promotion_id', $value)
                    ->orderBy('offset')
                    ->first();

                $mallTime = Carbon::now($nearestMallByTimezoneOffset->timezone_name);
                $coupon = Coupon::active()
                                ->where('promotion_id', $value)
                                ->where('begin_date', "<=", $mallTime)
                                ->where('end_date', '>=', $mallTime)
                                ->where('coupon_validity_in_date', '>=', $mallTime)
                                ->first();

                if (! is_object($coupon)) {
                    return false;
                }

                \App::instance('orbit.validation.coupon', $coupon);

                return true;
            }
        );

        // Check coupon, it should not exists in user wallet
        Validator::extend(
            'orbit.notexists.couponwallet',
            function ($attribute, $value, $parameters) {
                // check if coupon already add to wallet
                $user = UserGetter::getLoggedInUserOrGuest($this->session);

                $wallet = IssuedCoupon::where('promotion_id', '=', $value)
                                      ->where('user_id', '=', $user->user_id)
                                      ->where('status', '=', 'issued')
                                      ->first();

                if (is_object($wallet)) {
                    return false;
                }

                return true;
            }
        );

        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });

        // Check promo code already used or not
        Validator::extend('orbit.exists.promo_code', function ($attribute, $value, $parameters) {

            //check if promo code already used
            $code_used = TmpPromoCode::where('promo_code', '=', $value)->first();

            if ($code_used) {
                return FALSE;
            }

            return TRUE;
        });


        // Validate user, 1 user can only use 1 promo code
        Validator::extend('orbit.validate.user_id', function ($attribute, $value, $parameters) {

            //check if user already use discount code
            $user_exist = TmpPromoCode::where('user_id', '=', $this->user->user_id)->first();

            if ($user_exist) {
                return FALSE;
            }

            return TRUE;
        });

        // Quantity must be 1
        Validator::extend('orbit.validate.quantity', function ($attribute, $value, $parameters) {

            if ($value == 1) {
                return TRUE;
            }

            return FALSE;
        });
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Modify user object property
     */
    public function customizeUserProps($user, $email)
    {
        $role = $user->role->role_name;
        if (strtolower($role) === 'consumer') {
            // change first name and last name to full name + (user_email)
            $user->user_firstname = $user->getFullName();
            $user->user_lastname = sprintf("(%s)", $user->user_email);
        }

        // change user email to email provided by query string
        $user->user_email = $email;

        return $user;
    }
}
