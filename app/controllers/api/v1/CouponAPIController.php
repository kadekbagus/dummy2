<?php
/**
 * An API controller for managing Coupon.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;
use \Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\Payment\Payment as PaymentClient;
use Orbit\Helper\GoogleMeasurementProtocol\Client as GMP;

class CouponAPIController extends ControllerAPI
{
     /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $couponViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $couponModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    protected $couponModifiyRolesWithConsumer = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign admin', 'consumer'];

    protected $pmpAccountDefaultLanguage = NULL;

    /**
     * POST - Create New Coupon
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                       (required) - Mall ID
     * @param string     `promotion_name`                    (required) - Coupon name
     * @param string     `promotion_type`                    (required) - Coupon type. Valid value: mall, tenant.
     * @param string     `status`                            (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`                       (optional) - Description
     * @param string     `long_description`                  (optional) - Long description
     * @param datetime   `begin_date`                        (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`                          (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`                      (optional) - Is permanent. Valid value: Y, N.
     * @param string     `is_all_employee`                   (optional) - Is all cs coupon redeem. Valid value: Y, N.
     * @param string     `is_all_retailer`                   (optional) - Is all retailer coupon redeem. Valid value: Y, N.
     * @param file       `image`                             (optional) - Coupon image
     * @param string     `maximum_issued_coupon_type`        (optional) - Maximum issued coupon type. Valid value: period, days.
     * @param integer    `maximum_issued_coupon`             (optional) - Maximum issued coupon
     * @param integer    `coupon_validity_in_days`           (optional) - Coupon validity in days
     * @param datetime   `coupon_validity_in_date`           (optional) - Coupon validity in date
     * @param string     `coupon_notification`               (optional) - Coupon notification. Valid value: Y, N.
     * @param string     `rule_type`                         (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`                        (optional) - Rule value
     * @param string     `rule_object_type`                  (optional) - Rule object type. Valid value: .
     * @param integer    `rule_object_id1`                   (optional) - Rule object ID1 ( or ).
     * @param integer    `rule_object_id2`                   (optional) - Rule object ID2 ().
     * @param integer    `rule_object_id3`                   (optional) - Rule object ID3 ().
     * @param integer    `rule_object_id4`                   (optional) - Rule object ID4 ().
     * @param integer    `rule_object_id5`                   (optional) - Rule object ID5 ().
     * @param string     `discount_object_type`              (optional) - Discount object type. Valid value: .
     * @param integer    `discount_object_id1`               (optional) - Discount object ID1 ( or ).
     * @param integer    `discount_object_id2`               (optional) - Discount object ID2 ().
     * @param integer    `discount_object_id3`               (optional) - Discount object ID3 ().
     * @param integer    `discount_object_id4`               (optional) - Discount object ID4 ().
     * @param integer    `discount_object_id5`               (optional) - Discount object ID5 ().
     * @param decimal    `discount_value`                    (optional) - Discount value
     * @param string     `is_cumulative_with_coupons`        (optional) - Cumulative with other coupons. Valid value: Y, N.
     * @param string     `is_cumulative_with_promotions`     (optional) - Cumulative with other promotions. Valid value: Y, N.
     * @param decimal    `coupon_redeem_rule_value`          (optional) - Coupon redeem rule value
     * @param array      `retailer_ids`                      (optional) - Tenant IDs
     * @param array      `employee_user_ids`                 (optional) - User IDs of Employee
     * @param array      `id_language_default`               (required) - ID language default
     * @param string     `is_all_gender`                     (optional) - Is all gender. Valid value: Y, N.
     * @param string     `is_all_age`                        (optional) - Is all retailer age group. Valid value: Y, N.
     * @param string     `gender_ids`                        (optional) - for Male, Female. Unknown. Valid value: M, F, U.
     * @param string     `age_range_ids`                     (optional) - Age Range IDs
     * @param string     `translations`                      (optional) - For Translations
     * @param string     `sticky_order`                      (required) - For set premium content, Default : 0
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewCoupon()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newcoupon = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.postnewcoupon.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.coupon.postnewcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postnewcoupon.before.authz', array($this, $user));


            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'You have to log in to continue';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.postnewcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type','mall');
            $campaignStatus = OrbitInput::post('campaign_status');
            $description = OrbitInput::post('description');
            $long_description = OrbitInput::post('long_description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $is_all_retailer = OrbitInput::post('is_all_retailer');
            $is_all_employee = OrbitInput::post('is_all_employee');
            $maximum_issued_coupon_type = OrbitInput::post('maximum_issued_coupon_type');
            $coupon_validity_in_days = OrbitInput::post('coupon_validity_in_days');
            $coupon_validity_in_date = OrbitInput::post('coupon_validity_in_date');
            $coupon_notification = OrbitInput::post('coupon_notification');
            $rule_type = OrbitInput::post('rule_type');
            $rule_value = OrbitInput::post('rule_value');
            $rule_object_type = OrbitInput::post('rule_object_type');
            $rule_object_id1 = OrbitInput::post('rule_object_id1');
            $rule_object_id2 = OrbitInput::post('rule_object_id2');
            $rule_object_id3 = OrbitInput::post('rule_object_id3');
            $rule_object_id4 = OrbitInput::post('rule_object_id4');
            $rule_object_id5 = OrbitInput::post('rule_object_id5');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $discount_value = OrbitInput::post('discount_value');
            $is_cumulative_with_coupons = OrbitInput::post('is_cumulative_with_coupons');
            $is_cumulative_with_promotions = OrbitInput::post('is_cumulative_with_promotions');
            $coupon_redeem_rule_value = OrbitInput::post('coupon_redeem_rule_value');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $employee_user_ids = OrbitInput::post('employee_user_ids');
            $employee_user_ids = (array) $employee_user_ids;
            $id_language_default = OrbitInput::post('id_language_default');
            $is_popup = OrbitInput::post('is_popup', 'N');
            // $rule_begin_date = OrbitInput::post('rule_begin_date');
            // $rule_end_date = OrbitInput::post('rule_end_date');
            $keywords = OrbitInput::post('keywords');
            $translations = OrbitInput::post('translations');
            $keywords = (array) $keywords;
            $productTags = OrbitInput::post('product_tags');
            $productTags = (array) $productTags;
            $linkToTenantIds = OrbitInput::post('link_to_tenant_ids');
            $linkToTenantIds = (array) $linkToTenantIds;
            $sticky_order = OrbitInput::post('sticky_order');
            $couponCodes = OrbitInput::post('coupon_codes');
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $is_sponsored = OrbitInput::post('is_sponsored', 'N');
            $sponsor_ids = OrbitInput::post('sponsor_ids');
            $gender = OrbitInput::post('gender', 'Y');

            $is3rdPartyPromotion = OrbitInput::post('is_3rd_party_promotion', 'N');
            $promotionValue = OrbitInput::post('promotion_value', NULL);
            $currency = OrbitInput::post('currency', NULL);
            $offerType = OrbitInput::post('offer_type', NULL);
            $offerValue = OrbitInput::post('offer_value', NULL);
            $originalPrice = OrbitInput::post('original_price', NULL);
            $redemptionVerificationCode = OrbitInput::post('redemption_verification_code', NULL);
            $shortDescription = OrbitInput::post('short_description', NULL);
            $isVisible = OrbitInput::post('is_hidden', 'N') === 'Y' ? 'N' : 'Y';
            $thirdPartyName = OrbitInput::post('third_party_name', NULL);
            $maximumRedeem = OrbitInput::post('maximum_redeem', NULL);
            $maximumIssuedCoupon = OrbitInput::post('maximum_issued_coupon', NULL);
            $maxQuantityPerPurchase = OrbitInput::post('max_quantity_per_purchase', NULL);
            $maxQuantityPerUser = OrbitInput::post('max_quantity_per_user', NULL);

            $payByWallet = OrbitInput::post('pay_by_wallet', 'N');
            $payByNormal = OrbitInput::post('pay_by_normal', 'N');
            $paymentProviders = OrbitInput::post('payment_provider_ids', null);
            $amountCommission = OrbitInput::post('amount_commission', null);
            $fixedAmountCommission = OrbitInput::post('fixed_amount_commission', null);

            // hot deals
            $price_old = OrbitInput::post('price_old', 0);
            $merchant_commision = OrbitInput::post('merchant_commision', 0);
            $price_selling = OrbitInput::post('price_selling', 0);

            // discounts
            $discounts = OrbitInput::post('discounts', []);

            // new fields for 4.27a
            $redemptionLink = OrbitInput::post('redemption_link');
            $howToBuyAndRedeem = OrbitInput::post('how_to_buy_and_redeem');
            $termsAndCondition = OrbitInput::post('terms_and_condition');
            $priceToGtm = OrbitInput::post('price_to_gtm', 0);
            $couponCodeType = OrbitInput::post('coupon_code_type', 'code');

            if ($payByNormal === 'N') {
                $fixedAmountCommission = 0;
            }

            if ($payByWallet === 'N') {
                $amountCommission = 0;
                $paymentProviders = null;
            }

            if (empty($campaignStatus)) {
                $campaignStatus = 'not started';
            }

            $status = 'inactive';
            if ($campaignStatus === 'ongoing') {
                $status = 'active';
            }

            $validator_value = [
                'promotion_name'          => $promotion_name,
                'promotion_type'          => $promotion_type,
                'begin_date'              => $begin_date,
                'end_date'                => $end_date,
                'rule_type'               => $rule_type,
                'status'                  => $status,
                'coupon_validity_in_date' => $coupon_validity_in_date,
                'rule_value'              => $rule_value,
                'discount_value'          => $discount_value,
                'is_all_retailer'         => $is_all_retailer,
                'is_all_employee'         => $is_all_employee,
                'id_language_default'     => $id_language_default,
                // 'rule_begin_date'         => $rule_begin_date,
                // 'rule_end_date'           => $rule_end_date,
                'sticky_order'            => $sticky_order,
                'is_popup'                => $is_popup,
                'coupon_codes'            => $couponCodes,
                'is_visible'              => $isVisible,
                'is_3rd_party_promotion'  => $is3rdPartyPromotion,
                'maximum_redeem'          => $maximumRedeem,
                'max_quantity_per_purchase' => $maxQuantityPerPurchase,
            ];
            $validator_validation = [
                'promotion_name'          => 'required|max:255',
                'promotion_type'          => 'required|in:mall,tenant,hot_deals',
                'begin_date'              => 'required|date_format:Y-m-d H:i:s',
                'end_date'                => 'required|date_format:Y-m-d H:i:s',
                'rule_type'               => 'orbit.empty.coupon_rule_type',
                'status'                  => 'required|orbit.empty.coupon_status',
                'coupon_validity_in_date' => 'date_format:Y-m-d H:i:s',
                'rule_value'              => 'required|numeric|min:0',
                'discount_value'          => 'required|numeric|min:0',
                'is_all_retailer'         => 'orbit.empty.status_link_to',
                'is_all_employee'         => 'orbit.empty.status_link_to',
                'id_language_default'     => 'required|orbit.empty.language_default',
                // 'rule_begin_date'         => 'date_format:Y-m-d H:i:s',
                // 'rule_end_date'           => 'date_format:Y-m-d H:i:s',
                'sticky_order'            => 'in:0,1',
                'is_popup'                => 'in:Y,N',
                'coupon_codes'            => 'required',
                'is_visible'              => 'required|in:Y,N',
                'is_3rd_party_promotion'  => 'required|in:Y,N',
                'maximum_redeem'          => 'numeric',
                'max_quantity_per_purchase' => 'required|numeric',
            ];
            $validator_message = [
                'rule_value.required'     => 'The amount to obtain is required',
                'rule_value.numeric'      => 'The amount to obtain must be a number',
                'rule_value.min'          => 'The amount to obtain must be greater than zero',
                'discount_value.required' => 'The coupon value is required',
                'discount_value.numeric'  => 'The coupon value must be a number',
                'discount_value.min'      => 'The coupon value must be greater than zero',
                'sticky_order.in'         => 'The sticky order value must 0 or 1',
                'is_popup.in'             => 'is popup must Y or N',
            ];

            if (! empty($is_exclusive) && ! empty($partner_ids)) {
                $validator_value['partner_exclusive']               = $is_exclusive;
                $validator_validation['partner_exclusive']          = 'in:Y,N|orbit.empty.exclusive_partner';
                $validator_message['orbit.empty.exclusive_partner'] = 'Partner is not exclusive / inactive';
            }

            $validator = Validator::make(
                $validator_value,
                $validator_validation,
                $validator_message
            );

            // validation for hot deals
            if ($promotion_type === 'hot_deals') {
                $hotDealsValue = [
                    // 'price_old' => $price_old,
                    // 'merchant_commision' => $merchant_commision,
                    'price_selling' => $price_selling,
                ];
                $hotDealsValidation = [
                    // 'price_old' => 'required',
                    // 'merchant_commision' => 'required',
                    'price_selling' => 'required',
                ];
                $thirdValidator = Validator::make(
                    $hotDealsValue,
                    $hotDealsValidation,
                    []
                );
            }

            $sponsorIds = array();
            if ($is_sponsored === 'Y') {
                $sponsorIds = @json_decode($sponsor_ids);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON sponsor is not valid');
                }
            }

            Event::fire('orbit.coupon.postnewcoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check the end date should be less than validity in date
            $int_before_end_date = strtotime(date('Y-m-d', strtotime($end_date)));
            $int_validity_date = strtotime($coupon_validity_in_date);
            if ($int_validity_date <= $int_before_end_date) {
                $errorMessage = 'The validity redeem date should be greater than the end date.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // if ($payByWallet === 'N' && $payByNormal === 'N') {
            //     $errorMessage = 'Select one payment method.';
            //     OrbitShopAPI::throwInvalidArgument($errorMessage);
            // } elseif ($payByWallet === 'Y' && $payByNormal === 'N') {
            //     $dataPayment = @json_decode($paymentProviders);
            //     if (count($dataPayment) != count($retailer_ids)) {
            //         $errorMessage = 'Not all redemption place support wallet payment method';
            //         OrbitShopAPI::throwInvalidArgument($errorMessage);
            //     }
            // }

            // if ($payByNormal === 'Y') {
            //     $validator = Validator::make(
            //         array(
            //             'amount_commission'       => $amountCommission,
            //             'fixed_amount_commission' => $fixedAmountCommission,

            //         ),
            //         array(
            //             'amount_commission'       => 'required',
            //             'fixed_amount_commission' => 'required',
            //         )
            //     );

            //     if ($validator->fails()) {
            //         $errorMessage = $validator->messages()->first();
            //         OrbitShopAPI::throwInvalidArgument($errorMessage);
            //     }
            // }

            if ($payByWallet === 'Y') {
                $dataPayment = @json_decode($paymentProviders);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON payment_providers is not valid');
                }
            }

            // validating employee_user_ids.
            foreach ($employee_user_ids as $employee_user_id_check) {
                $validator = Validator::make(
                    array(
                        'employee_user_id'   => $employee_user_id_check,

                    ),
                    array(
                        'employee_user_id'   => 'orbit.empty.employee',
                    )
                );

                Event::fire('orbit.coupon.postnewcoupon.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.coupon.postnewcoupon.after.retailervalidation', array($this, $validator));
            }

            $arrayCouponCode = [];

            // validate coupon codes
            if (! empty($couponCodes)) {
                $dupes = array();
                // trim and explode coupon codes to array
                $arrayCouponCode = array_map('trim', explode("\n", $couponCodes));
                // delete empty array and reorder it
                $arrayCouponCode = array_values(array_filter($arrayCouponCode));
                // find the dupes
                foreach(array_count_values($arrayCouponCode) as $val => $frequency) {
                    if ($frequency > 1) $dupes[] = $val;
                }

                if (! empty($dupes)) {
                    $stringDupes = implode(',', $dupes);
                    $errorMessage = 'The coupon codes you supplied have duplicates: %s';
                    OrbitShopAPI::throwInvalidArgument(sprintf($errorMessage, $stringDupes));
                }
            }

            // maximum issued coupon validation
            if (! empty($maximumIssuedCoupon)) {
                if ($maximumIssuedCoupon > count($arrayCouponCode)) {
                    $errorMessage = 'Maximum Issued Coupons should not great than total of coupon codes';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                if ($maximumIssuedCoupon < 1) {
                    $errorMessage = 'Minimum amount of maximum issued coupon is 1';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            } else {
                $maximumIssuedCoupon = count($arrayCouponCode);
            }

            // maximum redeem validation
            if (! empty($maximumRedeem)) {
                if ($maximumRedeem > count($arrayCouponCode)) {
                    $errorMessage = 'Maximum Redeemed Coupons should not great than total of coupon codes';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                if ($maximumRedeem > $maximumIssuedCoupon) {
                    $errorMessage = 'Maximum Redeemed Coupons should not great than Maximum Issued Coupons';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                if ($maximumRedeem < 1) {
                    $errorMessage = 'Minimum amount of maximum redeemed coupon is 1';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            } else {
                $maximumRedeem = count($arrayCouponCode);
            }

            // maximum purchase per transaction validation
            if ($promotion_type === 'hot_deals') {

                if (! empty($maxQuantityPerPurchase)) {

                    if ($maxQuantityPerPurchase > count($arrayCouponCode)) {
                        $errorMessage = 'The Maximum Purchase per Transaction should not great than the total of coupon codes';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    if ($maxQuantityPerPurchase < 1) {
                        $errorMessage = 'Minimum amount of maximum purchase per transaction is 1';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                } else {
                    $maxQuantityPerPurchase = count($arrayCouponCode);
                }

            }

            // validate redeem to stores/malls
            if (count($linkToTenantIds) !== 0) {
                $availableLinkToTenant = [];
                foreach ($linkToTenantIds as $link_to_tenant_json) {
                    $data = @json_decode($link_to_tenant_json);
                    $availableLinkToTenant[] = $data->tenant_id;
                }

                foreach ($retailer_ids as $retailer_id) {
                    $data = @json_decode($retailer_id);
                    $tenant_id = $data->tenant_id;
                    if (! in_array($tenant_id, $availableLinkToTenant)) {
                        $errorMessage = 'Redeem to stores/malls does not match with Link to stores/malls';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }
            }

            // A means all gender
            if ($gender === 'A') {
                $gender = 'Y';
            }

            Event::fire('orbit.coupon.postnewcoupon.after.validation', array($this, $validator));

            // save Coupon.
            $idStatus = CampaignStatus::select('campaign_status_id','campaign_status_name')->where('campaign_status_name', $campaignStatus)->first();

            $newcoupon = new Coupon();
            $newcoupon->merchant_id = $merchant_id;
            $newcoupon->promotion_name = $promotion_name;
            $newcoupon->description = $description;
            $newcoupon->promotion_type = $promotion_type;
            $newcoupon->status = $status;
            $newcoupon->campaign_status_id = $idStatus->campaign_status_id;
            $newcoupon->begin_date = $begin_date;
            $newcoupon->end_date = $end_date;
            $newcoupon->is_permanent = $is_permanent;
            $newcoupon->is_all_retailer = $is_all_retailer;
            $newcoupon->is_all_employee = $is_all_employee;
            $newcoupon->maximum_issued_coupon_type = $maximum_issued_coupon_type;
            $newcoupon->coupon_validity_in_days = $coupon_validity_in_days;
            $newcoupon->coupon_validity_in_date = $coupon_validity_in_date;
            $newcoupon->coupon_notification = $coupon_notification;
            $newcoupon->created_by = $this->api->user->user_id;
            $newcoupon->is_all_age = 'Y';
            $newcoupon->is_all_gender = $gender;
            $newcoupon->is_popup = $is_popup;
            $newcoupon->sticky_order = $sticky_order;
            $newcoupon->is_exclusive = $is_exclusive;
            $newcoupon->is_visible = $isVisible;
            $newcoupon->maximum_issued_coupon = $maximumIssuedCoupon;
            $newcoupon->maximum_redeem = $maximumRedeem;
            $newcoupon->is_payable_by_wallet = $payByWallet;
            $newcoupon->is_payable_by_normal = $payByNormal;
            $newcoupon->transaction_amount_commission = $amountCommission;
            $newcoupon->fixed_amount_commission = $fixedAmountCommission;
            $newcoupon->is_sponsored = $is_sponsored;
            $newcoupon->price_old = $price_old;
            $newcoupon->merchant_commision = $merchant_commision;
            $newcoupon->price_selling = $price_selling;
            $newcoupon->max_quantity_per_purchase = $maxQuantityPerPurchase;
            $newcoupon->max_quantity_per_user = $maxQuantityPerUser;
            $newcoupon->long_description = $termsAndCondition;
            $newcoupon->price_to_gtm = $priceToGtm;
            $newcoupon->redemption_link = $redemptionLink;
            $newcoupon->coupon_code_type = $couponCodeType;

            // save 3rd party coupon fields
            if ($is3rdPartyPromotion === 'Y') {
                $newcoupon->is_3rd_party_promotion = $is3rdPartyPromotion;
                $newcoupon->is_3rd_party_field_complete = 'Y';
                $newcoupon->how_to_buy_and_redeem = $howToBuyAndRedeem;
            }

            if ($rule_type === 'unique_coupon_per_user') {
                $newcoupon->is_unique_redeem = 'Y';

                // Make sure to force max quantity for purchase and
                // max quantity per user to 1 if coupon is unique.
                $newcoupon->max_quantity_per_purchase = 1;
                $newcoupon->max_quantity_per_user = 1;
            }

            Event::fire('orbit.coupon.postnewcoupon.before.save', array($this, $newcoupon));

            $newcoupon->save();

            // Return campaign_status_name
            $newcoupon->campaign_status = $idStatus->campaign_status_name;

            // save CouponRule.
            $couponrule = new CouponRule();
            $couponrule->rule_type = $rule_type;
            $couponrule->rule_value = $rule_value;
            $couponrule->rule_object_type = $rule_object_type;

            // rule_object_id1
            if (trim($rule_object_id1) === '') {
                $couponrule->rule_object_id1 = NULL;
            } else {
                $couponrule->rule_object_id1 = $rule_object_id1;
            }

            // rule_object_id2
            if (trim($rule_object_id2) === '') {
                $couponrule->rule_object_id2 = NULL;
            } else {
                $couponrule->rule_object_id2 = $rule_object_id2;
            }

            // rule_object_id3
            if (trim($rule_object_id3) === '') {
                $couponrule->rule_object_id3 = NULL;
            } else {
                $couponrule->rule_object_id3 = $rule_object_id3;
            }

            // rule_object_id4
            if (trim($rule_object_id4) === '') {
                $couponrule->rule_object_id4 = NULL;
            } else {
                $couponrule->rule_object_id4 = $rule_object_id4;
            }

            // rule_object_id5
            if (trim($rule_object_id5) === '') {
                $couponrule->rule_object_id5 = NULL;
            } else {
                $couponrule->rule_object_id5 = $rule_object_id5;
            }

            $couponrule->discount_object_type = $discount_object_type;

            // discount_object_id1
            if (trim($discount_object_id1) === '') {
                $couponrule->discount_object_id1 = NULL;
            } else {
                $couponrule->discount_object_id1 = $discount_object_id1;
            }

            // discount_object_id2
            if (trim($discount_object_id2) === '') {
                $couponrule->discount_object_id2 = NULL;
            } else {
                $couponrule->discount_object_id2 = $discount_object_id2;
            }

            // discount_object_id3
            if (trim($discount_object_id3) === '') {
                $couponrule->discount_object_id3 = NULL;
            } else {
                $couponrule->discount_object_id3 = $discount_object_id3;
            }

            // discount_object_id4
            if (trim($discount_object_id4) === '') {
                $couponrule->discount_object_id4 = NULL;
            } else {
                $couponrule->discount_object_id4 = $discount_object_id4;
            }

            // discount_object_id5
            if (trim($discount_object_id5) === '') {
                $couponrule->discount_object_id5 = NULL;
            } else {
                $couponrule->discount_object_id5 = $discount_object_id5;
            }

            $couponrule->discount_value = $discount_value;
            $couponrule->is_cumulative_with_coupons = $is_cumulative_with_coupons;
            $couponrule->is_cumulative_with_promotions = $is_cumulative_with_promotions;
            $couponrule->coupon_redeem_rule_value = $coupon_redeem_rule_value;
            // $couponrule->rule_begin_date = $rule_begin_date;
            // $couponrule->rule_end_date = $rule_end_date;
            $couponrule = $newcoupon->couponRule()->save($couponrule);
            $newcoupon->coupon_rule = $couponrule;

            // save CouponRetailerRedeem
            $retailers = array();
            $isMall = 'tenant';
            $mallid = array();
            foreach ($retailer_ids as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                if ($tenant_id === $mall_id) {
                    $isMall = 'mall';
                } else {
                    $isMall = 'tenant';
                }

                $retailer = new CouponRetailerRedeem();
                $retailer->promotion_id = $newcoupon->promotion_id;
                $retailer->retailer_id = $tenant_id;
                $retailer->object_type = $isMall;
                $retailer->save();
                $retailers[] = $retailer;

                $retailerRedeemId = $retailer->promotion_retailer_redeem_id;

                // save coupon payment provider
                if ($payByWallet === 'Y') {
                    $dataPayment = @json_decode($paymentProviders);
                    foreach ($dataPayment as $data) {
                        foreach ((array) $data as $key => $value) {
                            if ($key === $tenant_id) {
                                foreach ($value as $provider) {
                                    $couponPayment = new CouponPaymentProvider();
                                    $couponPayment->payment_provider_id = $provider;
                                    $couponPayment->promotion_retailer_redeem_id = $retailerRedeemId;
                                    $couponPayment->save();
                                }
                            }
                        }
                    }
                }
            }

            $newcoupon->tenants = $retailers;

            $employees = array();
            foreach ($employee_user_ids as $employee_user_id) {
                $employee = new CouponEmployee();
                $employee->promotion_id = $newcoupon->promotion_id;
                $employee->user_id = $employee_user_id;
                $employee->save();
                $employees[] = $employee;
            }

            $newcoupon->employees = $employees;

            // save CouponRetailer
            $retailers = array();
            $isMall = 'tenant';
            $mallid = array();

            foreach ($linkToTenantIds as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                if ($tenant_id === $mall_id) {
                    $isMall = 'mall';
                } else {
                    $isMall = 'tenant';
                }

                $couponretailer = new CouponRetailer();
                $couponretailer->retailer_id = $tenant_id;
                $couponretailer->promotion_id = $newcoupon->promotion_id;
                $couponretailer->object_type = $isMall;
                $couponretailer->save();
                $couponretailers[] = $couponretailer;
            }

            $newcoupon->link_to_tenants = $couponretailers;

            //save to user campaign
            $usercampaign = new UserCampaign();
            $usercampaign->user_id = $user->user_id;
            $usercampaign->campaign_id = $newcoupon->promotion_id;
            $usercampaign->campaign_type = 'coupon';
            $usercampaign->save();

            // save Keyword
            $couponKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                ->where('keyword', '=', $keyword)
                ->where('merchant_id', '=', 0)
                ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = 0;
                    $newKeyword->keyword = $keyword;
                    $newKeyword->status = 'active';
                    $newKeyword->created_by = $this->api->user->user_id;
                    $newKeyword->modified_by = $this->api->user->user_id;
                    $newKeyword->save();

                    $keyword_id = $newKeyword->keyword_id;
                    $couponKeywords[] = $newKeyword;
                } else {
                    $keyword_id = $existKeyword->keyword_id;
                    $couponKeywords[] = $existKeyword;
                }

                $newKeywordObject = new KeywordObject();
                $newKeywordObject->keyword_id = $keyword_id;
                $newKeywordObject->object_id = $newcoupon->promotion_id;
                $newKeywordObject->object_type = 'coupon';
                $newKeywordObject->save();
            }
            $newcoupon->keywords = $couponKeywords;

            // Save product tags
            $couponProductTags = array();
            foreach ($productTags as $productTag) {
                $product_tag_id = null;

                $existProductTag = ProductTag::excludeDeleted()
                    ->where('product_tag', '=', $productTag)
                    ->where('merchant_id', '=', 0)
                    ->first();

                if (empty($existProductTag)) {
                    $newProductTag = new ProductTag();
                    $newProductTag->merchant_id = 0;
                    $newProductTag->product_tag = $productTag;
                    $newProductTag->status = 'active';
                    $newProductTag->created_by = $this->api->user->user_id;
                    $newProductTag->modified_by = $this->api->user->user_id;
                    $newProductTag->save();

                    $product_tag_id = $newProductTag->product_tag_id;
                    $couponProductTags[] = $newProductTag;
                } else {
                    $product_tag_id = $existProductTag->product_tag_id;
                    $couponProductTags[] = $existProductTag;
                }

                $newProductTagObject = new ProductTagObject();
                $newProductTagObject->product_tag_id = $product_tag_id;
                $newProductTagObject->object_id = $newcoupon->promotion_id;
                $newProductTagObject->object_type = 'coupon';
                $newProductTagObject->save();
            }
            $newcoupon->product_tags = $couponProductTags;

            // save ObjectPartner (link to partner)
            $objectPartners = array();
            foreach ($partner_ids as $partner_id) {
                $objectPartner = new ObjectPartner();
                $objectPartner->object_id = $newcoupon->promotion_id;
                $objectPartner->object_type = 'coupon';
                $objectPartner->partner_id = $partner_id;
                $objectPartner->save();
                $objectPartners[] = $objectPartner;
            }
            $newcoupon->partners = $objectPartners;

            Event::fire('orbit.coupon.postnewcoupon.after.save', array($this, $newcoupon));

            OrbitInput::post('translations', function($translation_json_string) use ($newcoupon, $mallid, $is3rdPartyPromotion) {
                $isThirdParty = $is3rdPartyPromotion === 'Y' ? TRUE : FALSE;
                $this->validateAndSaveTranslations($newcoupon, $translation_json_string, 'create', $isThirdParty);
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
            $isAvailable = CouponTranslation::where('promotion_id', '=', $newcoupon->promotion_id)
                                        ->whereRaw("
                                            {$prefix}coupon_translations.merchant_language_id = (
                                                SELECT language_id
                                                FROM {$prefix}languages
                                                WHERE name = (SELECT mobile_default_language FROM {$prefix}campaign_account WHERE user_id = {$this->quote($this->api->user->user_id)})
                                            )
                                        ")
                                        ->where(function($query) {
                                            $query->where('promotion_name', '=', '')
                                                  ->orWhere('description', '=', '')
                                                  ->orWhereNull('promotion_name')
                                                  ->orWhereNull('description');
                                          })
                                        ->first();

            $required_name = false;
            $required_desc = false;

            if (is_object($isAvailable)) {
                if ($isAvailable->promotion_name === '' || empty($isAvailable->promotion_name)) {
                    $required_name = true;
                }
                if ($isAvailable->description === '' || empty($isAvailable->description)) {
                    $required_desc = true;
                }
            }

            if ($required_name === true && $required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_both', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_name) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_name', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_desc) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_desc', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($is_sponsored === 'Y' && (! empty($sponsorIds))) {
                $uniqueSponsor = array();
                foreach ($sponsorIds as $sponsorData) {
                    foreach ((array) $sponsorData as $key => $value) {
                        if (in_array($key, $uniqueSponsor)) {
                            $errorMessage = "Duplicate Sponsor (bank or e-wallet)";
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        $uniqueSponsor[] = $key;

                        //credit card must be filled
                        if ((count($value) == 0) || ($value === '')) {
                            $sponsorProvider = SponsorProvider::where('sponsor_provider_id', $key)->first();

                            if ($sponsorProvider->object_type === 'bank') {
                                $errorMessage = "Credit card is required";
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }

                        $objectSponsor = new ObjectSponsor();
                        $objectSponsor->sponsor_provider_id = $key;
                        $objectSponsor->object_id = $newcoupon->promotion_id;
                        $objectSponsor->object_type = 'coupon';

                        $allCreditCard = 'N';
                        if ($value === 'all_credit_card') {
                            $allCreditCard = 'Y';
                        }
                        $objectSponsor->is_all_credit_card = $allCreditCard;
                        $objectSponsor->save();

                        if (($allCreditCard === 'N') && (count($value) > 0)) {
                            if (is_array($value)) {
                                foreach ($value as $creditCardId) {
                                    $objectSponsorCreditCard = new ObjectSponsorCreditCard();
                                    $objectSponsorCreditCard->object_sponsor_id = $objectSponsor->object_sponsor_id;
                                    $objectSponsorCreditCard->sponsor_credit_card_id = $creditCardId;
                                    $objectSponsorCreditCard->save();
                                }
                            }
                        }
                    }
                }
            }

            // Sync discounts...
            $newcoupon->discounts()->sync($this->mapDiscounts($discounts));

            $this->response->data = $newcoupon;

            // issue coupon if coupon code is supplied
            if (! empty($arrayCouponCode)) {

                if ($is3rdPartyPromotion === 'Y') {
                    if ($couponCodeType === 'url') {
                        // shortlink
                        IssuedCoupon::bulkIssueGiftN($arrayCouponCode, $newcoupon->promotion_id, $newcoupon->coupon_validity_in_date, $user, 'shortlink');
                    } else {
                        // codes
                        IssuedCoupon::bulkIssue($arrayCouponCode, $newcoupon->promotion_id, $newcoupon->coupon_validity_in_date, $user);
                    }
                } else {
                    // codes
                    IssuedCoupon::bulkIssue($arrayCouponCode, $newcoupon->promotion_id, $newcoupon->coupon_validity_in_date, $user);
                }
            }

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Coupon Created: %s', $newcoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon OK')
                    ->setObject($newcoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postnewcoupon.after.commit', array($this, $newcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postnewcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postnewcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postnewcoupon.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            Event::fire('orbit.coupon.postnewcoupon.custom.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();

        } catch (Exception $e) {
            Event::fire('orbit.coupon.postnewcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage().' on Line: '.$e->getLine();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Coupon
     *
     * @author <Tian> <tian@dominopos.com>
     * @author <Firmansyah> <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                      (required) - Coupon ID
     * @param integer    `merchant_id`                       (optional) - Mall ID
     * @param string     `promotion_name`                    (optional) - Coupon name
     * @param string     `promotion_type`                    (optional) - Coupon type. Valid value: mall, tenant.
     * @param string     `status`                            (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`                       (optional) - Description
     * @param string     `long_description`                  (optional) - Long description
     * @param datetime   `begin_date`                        (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`                          (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`                      (optional) - Is permanent. Valid value: Y, N.
     * @param string     `is_all_employee`                   (optional) - Is all cs coupon redeem. Valid value: Y, N.
     * @param string     `is_all_retailer`                   (optional) - Is all retailer coupon redeem. Valid value: Y, N.* @param file       `images`                            (optional) - Coupon image
     * @param integer    `maximum_issued_coupon_type`        (optional) - Maximum issued coupon type. Valid value: period, days.
     * @param integer    `maximum_issued_coupon`             (optional) - Maximum issued coupon
     * @param integer    `coupon_validity_in_days`           (optional) - Coupon validity in days
     * @param integer    `coupon_validity_in_date`           (optional) - Coupon validity in date
     * @param string     `coupon_notification`               (optional) - Coupon notification. Valid value: Y, N.
     * @param string     `rule_type`                         (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`                        (optional) - Rule value
     * @param string     `rule_object_type`                  (optional) - Rule object type. Valid value: .
     * @param integer    `rule_object_id1`                   (optional) - Rule object ID1 ( or ).
     * @param integer    `rule_object_id2`                   (optional) - Rule object ID2 ().
     * @param integer    `rule_object_id3`                   (optional) - Rule object ID3 ().
     * @param integer    `rule_object_id4`                   (optional) - Rule object ID4 ().
     * @param integer    `rule_object_id5`                   (optional) - Rule object ID5 ().
     * @param string     `discount_object_type`              (optional) - Discount object type. Valid value: , .
     * @param integer    `discount_object_id1`               (optional) - Discount object ID1 ( or ).
     * @param integer    `discount_object_id2`               (optional) - Discount object ID2 ().
     * @param integer    `discount_object_id3`               (optional) - Discount object ID3 ().
     * @param integer    `discount_object_id4`               (optional) - Discount object ID4 ().
     * @param integer    `discount_object_id5`               (optional) - Discount object ID5 ().
     * @param decimal    `discount_value`                    (optional) - Discount value
     * @param string     `is_cumulative_with_coupons`        (optional) - Cumulative with other coupons. Valid value: Y, N.
     * @param string     `is_cumulative_with_promotions`     (optional) - Cumulative with other promotions. Valid value: Y, N.
     * @param decimal    `coupon_redeem_rule_value`          (optional) - Coupon redeem rule value
     * @param array      `retailer_ids`                      (optional) - Retailer IDs
     * @param array      `employee_user_ids`                 (optional) - User IDs of Employee
     * @param string     `no_retailer`                       (optional) - Flag to delete all retailer links. Valid value: Y.
     * @param string     `no_employee`                       (optional) - Flag to delete all cs links. Valid value: Y.
     * @param array      `id_language_default`               (required) - ID language default
     * @param string     `is_all_gender`                     (optional) - Is all gender. Valid value: Y, N.
     * @param string     `is_all_age`                        (optional) - Is all retailer age group. Valid value: Y, N.
     * @param string     `gender_ids`                        (optional) - for Male, Female. Unknown. Valid value: M, F, U.
     * @param string     `age_range_ids`                     (optional) - Age Range IDs
     * @param string     `translations`                      (optional) - For translations
     * @param string     `sticky_order`                      (required) - For set premium content, Default : 0
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateCoupon()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedcoupon = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.coupon.postupdatecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.postupdatecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postupdatecoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.postupdatecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();


            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_id = OrbitInput::post('current_mall');
            $promotion_type = OrbitInput::post('promotion_type','mall');
            $campaignStatus = OrbitInput::post('campaign_status');
            $rule_type = OrbitInput::post('rule_type');
            $rule_object_type = OrbitInput::post('rule_object_type');
            $rule_object_id1 = OrbitInput::post('rule_object_id1');
            $rule_object_id2 = OrbitInput::post('rule_object_id2');
            $rule_object_id3 = OrbitInput::post('rule_object_id3');
            $rule_object_id4 = OrbitInput::post('rule_object_id4');
            $rule_object_id5 = OrbitInput::post('rule_object_id5');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $is_all_retailer = OrbitInput::post('is_all_retailer');
            $is_all_employee = OrbitInput::post('is_all_employee');
            $maximum_issued_coupon_type = OrbitInput::post('maximum_issued_coupon_type');
            $coupon_validity_in_days = OrbitInput::post('coupon_validity_in_days');
            $coupon_validity_in_date = OrbitInput::post('coupon_validity_in_date');
            $discount_value = OrbitInput::post('discount_value');
            $rule_value = OrbitInput::post('rule_value');
            $id_language_default = OrbitInput::post('id_language_default');
            // $rule_begin_date = OrbitInput::post('rule_begin_date');
            // $rule_end_date = OrbitInput::post('rule_end_date');
            $translations = OrbitInput::post('translations');
            $coupon_codes = OrbitInput::post('coupon_codes');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $linkToTenantIds = OrbitInput::post('link_to_tenant_ids');
            $linkToTenantIds = (array) $linkToTenantIds;
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $is_visible = OrbitInput::post('is_hidden', 'N') === 'Y' ? 'N' : 'Y';

            $is_3rd_party_promotion = OrbitInput::post('is_3rd_party_promotion', 'N');
            $promotion_value = OrbitInput::post('promotion_value', NULL);
            $currency = OrbitInput::post('currency', NULL);
            $offer_type = OrbitInput::post('offer_type', NULL);
            $offer_value = OrbitInput::post('offer_value', NULL);
            $original_price = OrbitInput::post('original_price', NULL);
            $redemption_verification_code = OrbitInput::post('redemption_verification_code', NULL);
            $short_description = OrbitInput::post('short_description', NULL);
            $third_party_name = OrbitInput::post('third_party_name', NULL) === '' ? 'grab' : OrbitInput::post('third_party_name');
            $maximumRedeem = OrbitInput::post('maximum_redeem', NULL);

            $payByWallet = OrbitInput::post('pay_by_wallet', 'N');
            $payByNormal = OrbitInput::post('pay_by_normal', 'N');
            $paymentProviders = OrbitInput::post('payment_provider_ids', null);
            $amountCommission = OrbitInput::post('amount_commission', null);
            $fixedAmountCommission = OrbitInput::post('fixed_amount_commission', null);

            // hot deals
            $price_old = OrbitInput::post('price_old');
            $merchant_commision = OrbitInput::post('merchant_commision');
            $price_selling = OrbitInput::post('price_selling');

            $is_sponsored = OrbitInput::post('is_sponsored', 'N');
            $sponsor_ids = OrbitInput::post('sponsor_ids');

            // discount
            $discounts = OrbitInput::post('discounts', []);

            $idStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', $campaignStatus)->first();
            $status = 'inactive';
            if ($campaignStatus === 'ongoing') {
                $status = 'active';
            }

            $data = array(
                'promotion_id'            => $promotion_id,
                'promotion_type'          => $promotion_type,
                'status'                  => $status,
                'begin_date'              => $begin_date,
                'end_date'                => $end_date,
                'rule_type'               => $rule_type,
                'coupon_validity_in_date' => $coupon_validity_in_date,
                'rule_value'              => $rule_value,
                'discount_value'          => $discount_value,
                'is_all_retailer'         => $is_all_retailer,
                'is_all_employee'         => $is_all_employee,
                'id_language_default'     => $id_language_default,
                // 'rule_begin_date'         => $rule_begin_date,
                // 'rule_end_date'           => $rule_end_date,
                'partner_exclusive'       => $is_exclusive,
                'is_visible'              => $is_visible,
                'is_3rd_party_promotion'  => $is_3rd_party_promotion,
                'maximum_redeem'          => $maximumRedeem,
                'campaign_status'         => $campaignStatus,
            );

            // Validate promotion_name only if exists in POST.
            OrbitInput::post('promotion_name', function($promotion_name) use (&$data) {
                $data['promotion_name'] = $promotion_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'promotion_id'            => 'required|orbit.update.coupon',
                    'promotion_name'          => 'sometimes|required|max:255',
                    'promotion_type'          => 'required|in:mall,tenant,hot_deals',
                    'status'                  => 'orbit.empty.coupon_status',
                    'begin_date'              => 'date_format:Y-m-d H:i:s',
                    'end_date'                => 'date_format:Y-m-d H:i:s',
                    'rule_type'               => 'orbit.empty.coupon_rule_type',
                    'coupon_validity_in_date' => 'date_format:Y-m-d H:i:s',
                    'rule_value'              => 'numeric|min:0',
                    'discount_value'          => 'numeric|min:0',
                    'is_all_retailer'         => 'orbit.empty.status_link_to',
                    'is_all_employee'         => 'orbit.empty.status_link_to',
                    'id_language_default'     => 'required|orbit.empty.language_default',
                    // 'rule_begin_date'         => 'date_format:Y-m-d H:i:s',
                    // 'rule_end_date'           => 'date_format:Y-m-d H:i:s',
                    'partner_exclusive'       => 'in:Y,N|orbit.empty.exclusive_partner',
                    'is_visible'              => 'in:Y,N',
                    'is_3rd_party_promotion'  => 'in:Y,N',
                    'maximum_redeem'          => 'numeric',
                    'campaign_status'         => 'orbit.check.issued_coupon',
                ),
                array(
                    'rule_value.required'       => 'The amount to obtain is required',
                    'rule_value.numeric'        => 'The amount to obtain must be a number',
                    'rule_value.min'            => 'The amount to obtain must be greater than zero',
                    'discount_value.required'   => 'The coupon value is required',
                    'discount_value.numeric'    => 'The coupon value must be a number',
                    'discount_value.min'        => 'The coupon value must be greater than zero',
                    'orbit.update.coupon'       => 'Cannot update campaign with status ' . $campaignStatus,
                    'orbit.empty.exclusive_partner' => 'Partner is not exclusive / inactive',
                    'orbit.check.issued_coupon' => 'There is one or more coupon unredeemed',
                )
            );

            Event::fire('orbit.coupon.postupdatecoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Remove all key in Redis when campaign is stopped
            if ($status == 'inactive') {
                if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                    $redis = Cache::getRedis();
                    $keyName = array('coupon','home');
                    foreach ($keyName as $value) {
                        $keys = $redis->keys("*$value*");
                        if (! empty($keys)) {
                            foreach ($keys as $key) {
                                $redis->del($key);
                            }
                        }
                    }
                }
            }

            // if ($payByWallet === 'N' && $payByNormal === 'N') {
            //     $errorMessage = 'Select one payment method.';
            //     OrbitShopAPI::throwInvalidArgument($errorMessage);
            // } elseif ($payByWallet === 'Y' && $payByNormal === 'N') {
            //     $dataPayment = @json_decode($paymentProviders);
            //     if (count($dataPayment) != count($retailer_ids)) {
            //         $errorMessage = 'Not all redemption place support wallet payment method';
            //         OrbitShopAPI::throwInvalidArgument($errorMessage);
            //     }
            // }

            // if ($payByNormal === 'Y') {
            //     $validator = Validator::make(
            //         array(
            //             'amount_commission'       => $amountCommission,
            //             'fixed_amount_commission' => $fixedAmountCommission,

            //         ),
            //         array(
            //             'amount_commission'       => 'required',
            //             'fixed_amount_commission' => 'required',
            //         )
            //     );

            //     if ($validator->fails()) {
            //         $errorMessage = $validator->messages()->first();
            //         OrbitShopAPI::throwInvalidArgument($errorMessage);
            //     }
            // }

            if ($payByWallet === 'Y') {
                $dataPayment = @json_decode($paymentProviders);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON payment_providers is not valid');
                }
            }


            if ($promotion_type === 'hot_deals') {
                // validation for hot deals
                $hotDealsValue = [
                    // 'price_old' => $price_old,
                    // 'merchant_commision' => $merchant_commision,
                    'price_selling' => $price_selling,
                ];
                $hotDealsValidation = [
                    // 'price_old' => 'required',
                    // 'merchant_commision' => 'required',
                    'price_selling' => 'required',
                ];
                $thirdValidator = Validator::make(
                    $hotDealsValue,
                    $hotDealsValidation,
                    []
                );

                if ($thirdValidator->fails()) {
                    $errorMessage = $thirdValidator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

            }


            Event::fire('orbit.coupon.postupdatecoupon.after.validation', array($this, $validator));

            // Redeem to tenant
            $linktotenantnew = array();
            $mallid = array();
            foreach ($linkToTenantIds as $linktotenant) {
                $data = @json_decode($linktotenant);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                $linktotenantnew[] = $tenant_id;
            }

            $updatedcoupon = Coupon::where('promotion_id', $promotion_id)->first();

            $prefix = DB::getTablePrefix();
            // this is for send email to marketing, before and after list
            // $beforeUpdatedCoupon = Coupon::with([
            //                                 'translations.language',
            //                                 'translations.media',
            //                                 'ages.ageRange',
            //                                 'keywords',
            //                                 'product_tags',
            //                                 'campaign_status',
            //                                 'tenants' => function($q) use($prefix) {
            //                                     $q->addSelect(DB::raw("CONCAT ({$prefix}merchants.name, ' at ', malls.name) as name"));
            //                                     $q->join(DB::raw("{$prefix}merchants malls"), DB::raw("malls.merchant_id"), '=', 'merchants.parent_id');
            //                                 },
            //                                 'employee',
            //                                 'couponRule' => function($q) use($prefix) {
            //                                     $q->select('promotion_rule_id', 'promotion_id', DB::raw("DATE_FORMAT({$prefix}promotion_rules.rule_end_date, '%d/%m/%Y %H:%i') as rule_end_date"));
            //                                 }
            //                             ])
            //                             ->selectRaw("{$prefix}promotions.*,
            //                                 DATE_FORMAT({$prefix}promotions.end_date, '%d/%m/%Y %H:%i') as end_date,
            //                                 DATE_FORMAT({$prefix}promotions.coupon_validity_in_date, '%d/%m/%Y %H:%i') as coupon_validity_in_date,
            //                                 IF({$prefix}promotions.maximum_issued_coupon = 0, 'Unlimited', {$prefix}promotions.maximum_issued_coupon) as maximum_issued_coupon
            //                             ")
            //                             ->excludeDeleted()
            //                             ->where('promotion_id', $promotion_id)
            //                             ->first();

            // $statusdb = $updatedcoupon->status;
            // $enddatedb = $updatedcoupon->end_date;

            $currentCampaignStatus = $this->getCampaignStatus($promotion_id);

            // save Coupon
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedcoupon) {
                $updatedcoupon->merchant_id = $merchant_id;
            });

            if ($promotion_type === 'hot_deals') {
                $updatedcoupon->price_old = $price_old;
                $updatedcoupon->merchant_commision = $merchant_commision;
                $updatedcoupon->price_selling = $price_selling;
            }

            OrbitInput::post('promotion_type', function($promotion_type) use ($updatedcoupon) {
                $updatedcoupon->promotion_type = $promotion_type;
            });

            OrbitInput::post('campaign_status', function($campaignStatus) use ($updatedcoupon, $idStatus, $status) {
                $updatedcoupon->status = $status;
                $updatedcoupon->campaign_status_id = $idStatus->campaign_status_id;
            });

            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedcoupon) {
                $updatedcoupon->promotion_name = $promotion_name;
            });

            OrbitInput::post('description', function($description) use ($updatedcoupon) {
                $updatedcoupon->description = $description;
            });

            OrbitInput::post('terms_and_condition', function($terms_and_condition) use ($updatedcoupon) {
                $updatedcoupon->long_description = $terms_and_condition;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedcoupon) {
                $updatedcoupon->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedcoupon) {
                $updatedcoupon->end_date = $end_date;
            });

            OrbitInput::post('is_permanent', function($is_permanent) use ($updatedcoupon) {
                $updatedcoupon->is_permanent = $is_permanent;
            });

            OrbitInput::post('is_all_retailer', function($is_all_retailer) use ($updatedcoupon) {
                $updatedcoupon->is_all_retailer = $is_all_retailer;
                if ($is_all_retailer == 'Y') {
                    $deleted_retailer_ids = CouponRetailerRedeem::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->tenants()->detach($deleted_retailer_ids);
                    $updatedcoupon->load('tenants');
                }
            });

            OrbitInput::post('is_all_employee', function($is_all_employee) use ($updatedcoupon) {
                $updatedcoupon->is_all_employee = $is_all_employee;
                if ($is_all_employee == 'Y') {
                    $deleted_employee_user_ids = CouponEmployee::where('promotion_id', $updatedcoupon->promotion_id)->get(array('user_id'))->toArray();
                    $updatedcoupon->employee()->detach($deleted_employee_user_ids);
                    $updatedcoupon->load('employee');
                }
            });

            OrbitInput::post('is_popup', function($is_popup) use ($updatedcoupon) {
                $updatedcoupon->is_popup = $is_popup;
            });

            OrbitInput::post('sticky_order', function($sticky_order) use ($updatedcoupon) {
                $updatedcoupon->sticky_order = $sticky_order;
            });

            OrbitInput::post('maximum_issued_coupon_type', function($maximum_issued_coupon_type) use ($updatedcoupon) {
                $updatedcoupon->maximum_issued_coupon_type = $maximum_issued_coupon_type;
            });

            OrbitInput::post('coupon_validity_in_days', function($coupon_validity_in_days) use ($updatedcoupon) {
                $updatedcoupon->coupon_validity_in_days = $coupon_validity_in_days;
            });

            OrbitInput::post('is_exclusive', function($is_exclusive) use ($updatedcoupon) {
                $updatedcoupon->is_exclusive = $is_exclusive;
            });

            OrbitInput::post('gender', function($gender) use ($updatedcoupon, $promotion_id) {
                if ($gender === 'A') {
                    $gender = 'Y';
                }

                $updatedcoupon->is_all_gender = $gender;
            });

            OrbitInput::post('is_sponsored', function($is_sponsored) use ($updatedcoupon, $promotion_id) {
                $updatedcoupon->is_sponsored = $is_sponsored;

                if ($is_sponsored === 'N') {
                    // delete before insert new
                    $objectSponsor = ObjectSponsor::where('object_id', $promotion_id)
                                                  ->where('object_type', 'coupon');

                    $objectSponsorIds = $objectSponsor->lists('object_sponsor_id');

                    // delete ObjectSponsorCreditCard
                    if (! empty($objectSponsorIds)) {
                        $objectSponsorCreditCard = ObjectSponsorCreditCard::whereIn('object_sponsor_id', $objectSponsorIds)->delete();
                        $objectSponsor->delete();
                    }
                }
            });

            OrbitInput::post('sponsor_ids', function($sponsor_ids) use ($updatedcoupon, $promotion_id) {
                $sponsorIds = @json_decode($sponsor_ids);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON sponsor is not valid');
                }

                // delete before insert new
                $objectSponsor = ObjectSponsor::where('object_id', $promotion_id)
                                              ->where('object_type', 'coupon');

                $objectSponsorIds = $objectSponsor->lists('object_sponsor_id');

                // delete ObjectSponsorCreditCard
                if (! empty($objectSponsorIds)) {
                    $objectSponsorCreditCard = ObjectSponsorCreditCard::whereIn('object_sponsor_id', $objectSponsorIds)->delete();
                    $objectSponsor->delete();
                }

                $uniqueSponsor = array();
                foreach ($sponsorIds as $sponsorData) {
                    foreach ((array) $sponsorData as $key => $value) {
                        if (in_array($key, $uniqueSponsor)) {
                            $errorMessage = "Duplicate Sponsor (bank or e-wallet)";
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        $uniqueSponsor[] = $key;

                        //credit card must be filled
                        if ((count($value) == 0) || ($value === '')) {
                            $sponsorProvider = SponsorProvider::where('sponsor_provider_id', $key)->first();

                            if ($sponsorProvider->object_type === 'bank') {
                                $errorMessage = "Credit card is required";
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }

                        $objectSponsor = new ObjectSponsor();
                        $objectSponsor->sponsor_provider_id = $key;
                        $objectSponsor->object_id = $promotion_id;
                        $objectSponsor->object_type = 'coupon';

                        $allCreditCard = 'N';
                        if ($value === 'all_credit_card') {
                            $allCreditCard = 'Y';
                        }
                        $objectSponsor->is_all_credit_card = $allCreditCard;
                        $objectSponsor->save();

                        if (($allCreditCard === 'N') && (count($value) > 0)) {
                            if (is_array($value)) {
                                foreach ($value as $creditCardId) {
                                    $objectSponsorCreditCard = new ObjectSponsorCreditCard();
                                    $objectSponsorCreditCard->object_sponsor_id = $objectSponsor->object_sponsor_id;
                                    $objectSponsorCreditCard->sponsor_credit_card_id = $creditCardId;
                                    $objectSponsorCreditCard->save();
                                }
                            }
                        }
                    }
                }
            });

            OrbitInput::post('coupon_validity_in_date', function($coupon_validity_in_date) use ($updatedcoupon, $end_date) {
                // Check the end date should be less than validity in date
                $int_before_end_date = strtotime(date('Y-m-d', strtotime($end_date)));
                $int_validity_date = strtotime($coupon_validity_in_date);
                if ($int_validity_date <= $int_before_end_date) {
                    $errorMessage = 'The validity redeem date should be greater than the end date.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $updatedcoupon->coupon_validity_in_date = $coupon_validity_in_date;
            });

            OrbitInput::post('coupon_notification', function($coupon_notification) use ($updatedcoupon) {
                $updatedcoupon->coupon_notification = $coupon_notification;
            });

            OrbitInput::post('is_3rd_party_promotion', function($is_3rd_party_promotion) use ($updatedcoupon) {
                $updatedcoupon->is_3rd_party_promotion = $is_3rd_party_promotion;
            });

            OrbitInput::post('pay_by_wallet', function($pay_by_wallet) use ($updatedcoupon) {
                $updatedcoupon->is_payable_by_wallet = $pay_by_wallet;
            });

            OrbitInput::post('pay_by_normal', function($pay_by_normal) use ($updatedcoupon) {
                $updatedcoupon->is_payable_by_normal = $pay_by_normal;
            });

            OrbitInput::post('how_to_buy_and_redeem', function($howToBuyAndRedeem) use ($updatedcoupon) {
                $updatedcoupon->how_to_buy_and_redeem = $howToBuyAndRedeem;
            });

            OrbitInput::post('price_to_gtm', function($priceToGtm) use ($updatedcoupon) {
                $updatedcoupon->price_to_gtm = $priceToGtm;
            });

            OrbitInput::post('redemption_link', function($redemptionLink) use ($updatedcoupon) {
                $updatedcoupon->redemption_link = $redemptionLink;
            });

            OrbitInput::post('coupon_code_type', function($couponCodeType) use ($updatedcoupon) {
                $updatedcoupon->coupon_code_type = $couponCodeType;
            });

            OrbitInput::post('amount_commission', function($amount_commission) use ($updatedcoupon, $payByWallet) {
                if ($payByWallet === 'N') {
                    $amount_commission = 0;
                }

                $updatedcoupon->transaction_amount_commission = $amount_commission;
            });

            OrbitInput::post('fixed_amount_commission', function($fixed_amount_commission) use ($updatedcoupon, $payByNormal) {
                if ($payByNormal === 'N') {
                    $fixed_amount_commission = 0;
                }

                $updatedcoupon->fixed_amount_commission = $fixed_amount_commission;
            });


            $updatedcoupon->is_visible = $is_visible;

            OrbitInput::post('maximum_redeem', function($maximumRedeem) use ($updatedcoupon) {
                if (! empty($maximumRedeem)) {
                    if ($maximumRedeem < 1) {
                        $errorMessage = 'Minimum amount of maximum redeemed coupon is 1';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    // maximum redeem validation
                    $couponCode = IssuedCoupon::where('promotion_id', $updatedcoupon->promotion_id)->count();

                    if ($maximumRedeem > $couponCode) {
                        $errorMessage = 'The total maximum redeemed coupon can not be more than amount of coupon code';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $redeemed = IssuedCoupon::where('status', '=', 'redeemed')
                                                ->where('promotion_id', $updatedcoupon->promotion_id)
                                                ->count();

                    if ($maximumRedeem < $redeemed) {
                        $errorMessage = 'The total maximum redeemed coupon can not be less than amount of redeemed coupon';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $updatedcoupon->maximum_redeem = $maximumRedeem;
                }
            });

            if (empty($maximumRedeem)) {
               $updatedcoupon->maximum_redeem = 0;
            }

            if ($rule_type === 'unique_coupon_per_user') {
                $updatedcoupon->is_unique_redeem = 'Y';
            }

            $updatedcoupon->modified_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postupdatecoupon.before.save', array($this, $updatedcoupon));

            $updatedcoupon->setUpdatedAt($updatedcoupon->freshTimestamp());
            $updatedcoupon->save();

            // save CouponRule.
            $couponrule = CouponRule::where('promotion_id', '=', $promotion_id)->first();
            OrbitInput::post('rule_type', function($rule_type) use ($couponrule) {
                if (trim($rule_type) === '') {
                    $rule_type = NULL;
                }
                $couponrule->rule_type = $rule_type;
            });

            OrbitInput::post('rule_value', function($rule_value) use ($couponrule) {
                $couponrule->rule_value = $rule_value;
            });

            OrbitInput::post('rule_object_type', function($rule_object_type) use ($couponrule) {
                if (trim($rule_object_type) === '') {
                    $rule_object_type = NULL;
                }
                $couponrule->rule_object_type = $rule_object_type;
            });

            OrbitInput::post('rule_object_id1', function($rule_object_id1) use ($couponrule) {
                if (trim($rule_object_id1) === '') {
                    $rule_object_id1 = NULL;
                }
                $couponrule->rule_object_id1 = $rule_object_id1;
            });

            OrbitInput::post('rule_object_id2', function($rule_object_id2) use ($couponrule) {
                if (trim($rule_object_id2) === '') {
                    $rule_object_id2 = NULL;
                }
                $couponrule->rule_object_id2 = $rule_object_id2;
            });

            OrbitInput::post('rule_object_id3', function($rule_object_id3) use ($couponrule) {
                if (trim($rule_object_id3) === '') {
                    $rule_object_id3 = NULL;
                }
                $couponrule->rule_object_id3 = $rule_object_id3;
            });

            OrbitInput::post('rule_object_id4', function($rule_object_id4) use ($couponrule) {
                if (trim($rule_object_id4) === '') {
                    $rule_object_id4 = NULL;
                }
                $couponrule->rule_object_id4 = $rule_object_id4;
            });

            OrbitInput::post('rule_object_id5', function($rule_object_id5) use ($couponrule) {
                if (trim($rule_object_id5) === '') {
                    $rule_object_id5 = NULL;
                }
                $couponrule->rule_object_id5 = $rule_object_id5;
            });

            OrbitInput::post('discount_object_type', function($discount_object_type) use ($couponrule) {
                if (trim($discount_object_type) === '') {
                    $discount_object_type = NULL;
                }
                $couponrule->discount_object_type = $discount_object_type;
            });

            OrbitInput::post('discount_object_id1', function($discount_object_id1) use ($couponrule) {
                if (trim($discount_object_id1) === '') {
                    $discount_object_id1 = NULL;
                }
                $couponrule->discount_object_id1 = $discount_object_id1;
            });

            OrbitInput::post('discount_object_id2', function($discount_object_id2) use ($couponrule) {
                if (trim($discount_object_id2) === '') {
                    $discount_object_id2 = NULL;
                }
                $couponrule->discount_object_id2 = $discount_object_id2;
            });

            OrbitInput::post('discount_object_id3', function($discount_object_id3) use ($couponrule) {
                if (trim($discount_object_id3) === '') {
                    $discount_object_id3 = NULL;
                }
                $couponrule->discount_object_id3 = $discount_object_id3;
            });

            OrbitInput::post('discount_object_id4', function($discount_object_id4) use ($couponrule) {
                if (trim($discount_object_id4) === '') {
                    $discount_object_id4 = NULL;
                }
                $couponrule->discount_object_id4 = $discount_object_id4;
            });

            OrbitInput::post('discount_object_id5', function($discount_object_id5) use ($couponrule) {
                if (trim($discount_object_id5) === '') {
                    $discount_object_id5 = NULL;
                }
                $couponrule->discount_object_id5 = $discount_object_id5;
            });

            OrbitInput::post('discount_value', function($discount_value) use ($couponrule) {
                $couponrule->discount_value = $discount_value;
            });

            OrbitInput::post('is_cumulative_with_coupons', function($is_cumulative_with_coupons) use ($couponrule) {
                $couponrule->is_cumulative_with_coupons = $is_cumulative_with_coupons;
            });

            OrbitInput::post('is_cumulative_with_promotions', function($is_cumulative_with_promotions) use ($couponrule) {
                $couponrule->is_cumulative_with_promotions = $is_cumulative_with_promotions;
            });

            OrbitInput::post('coupon_redeem_rule_value', function($coupon_redeem_rule_value) use ($couponrule) {
                $couponrule->coupon_redeem_rule_value = $coupon_redeem_rule_value;
            });

            // OrbitInput::post('rule_begin_date', function($rule_begin_date) use ($couponrule) {
            //     $couponrule->rule_begin_date = $rule_begin_date;
            // });

            // OrbitInput::post('rule_end_date', function($rule_end_date) use ($couponrule) {
            //     $couponrule->rule_end_date = $rule_end_date;
            // });

            $couponrule->save();
            $updatedcoupon->setRelation('couponRule', $couponrule);
            $updatedcoupon->coupon_rule = $couponrule;

            // save CouponRetailerRedeem
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedcoupon) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = CouponRetailerRedeem::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->tenants()->detach($deleted_retailer_ids);
                    $updatedcoupon->load('tenants');
                }
            });

            OrbitInput::post('no_employee', function($no_employee) use ($updatedcoupon) {
                if ($no_employee == 'Y') {
                    $deleted_employee = CouponEmployee::where('promotion_id', $updatedcoupon->promotion_id)->get(array('user_id'))->toArray();
                    $updatedcoupon->employee()->detach($deleted_employee);
                    $updatedcoupon->load('employee');
                }
            });

            // save CouponRetailer
            OrbitInput::post('no_link_to_tenant', function($no_retailer) use ($updatedcoupon) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = CouponRetailer::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->linkToTenants()->detach($deleted_retailer_ids);
                    $updatedcoupon->load('linkToTenants');
                }
            });

            // Link to stores/malls
            OrbitInput::post('link_to_tenant_ids', function($retailer_ids) use ($promotion_id, $paymentProviders, $payByWallet, $currentCampaignStatus) {
                if ($currentCampaignStatus !== 'ongoing')
                {
                    // validating retailer_ids.
                    foreach ($retailer_ids as $retailer_id_json) {
                        $data = @json_decode($retailer_id_json);
                        $tenant_id = $data->tenant_id;
                        $mall_id = $data->mall_id;

                        $validatorRule = $tenant_id === $mall_id ? 'orbit.empty.merchant' : 'orbit.empty.tenant';

                        $validator = Validator::make(
                            array(
                                'retailer_id'   => $tenant_id,

                            ),
                            array(
                                'retailer_id'   => $validatorRule,
                            )
                        );

                        Event::fire('orbit.coupon.postupdatecoupon.before.retailervalidation', array($this, $validator));

                        // Run the validation
                        if ($validator->fails()) {
                            $errorMessage = $validator->messages()->first();
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        Event::fire('orbit.coupon.postupdatecoupon.after.retailervalidation', array($this, $validator));
                    }

                    $mallid = array();
                    // $existRetailer = CouponRetailerRedeem::where('promotion_id', '=', $promotion_id)->lists('promotion_retailer_redeem_id');
                    // if (! empty($existRetailer)) {
                    //     $delete_provider = CouponPaymentProvider::whereIn('promotion_retailer_redeem_id', $existRetailer)->delete();
                    // }

                    // // Delete old data
                    // $delete_retailer = CouponRetailerRedeem::where('promotion_id', '=', $promotion_id);
                    // $delete_retailer->delete();

                    // // Insert new data
                    // $retailers = array();
                    // $isMall = 'tenant';
                    // $mallid = array();
                    // foreach ($retailer_ids as $retailer_id) {
                    //     $data = @json_decode($retailer_id);
                    //     $tenant_id = $data->tenant_id;
                    //     $mall_id = $data->mall_id;

                    //     if(! in_array($mall_id, $mallid)) {
                    //         $mallid[] = $mall_id;
                    //     }

                    //     if ($tenant_id === $mall_id) {
                    //         $isMall = 'mall';
                    //     } else {
                    //         $isMall = 'tenant';
                    //     }


                    //     $retailer = new CouponRetailerRedeem();
                    //     $retailer->promotion_id = $promotion_id;
                    //     $retailer->retailer_id = $tenant_id;
                    //     $retailer->object_type = $isMall;
                    //     $retailer->save();

                    //     $retailerRedeemId = $retailer->promotion_retailer_redeem_id;

                    //     // save coupon payment provider
                    //     if ($payByWallet === 'Y') {
                    //         $dataPayment = @json_decode($paymentProviders);
                    //         foreach ($dataPayment as $data) {
                    //             foreach ((array) $data as $key => $value) {
                    //                 if ($key === $tenant_id) {
                    //                     foreach ($value as $provider) {
                    //                         $couponPayment = new CouponPaymentProvider();
                    //                         $couponPayment->payment_provider_id = $provider;
                    //                         $couponPayment->promotion_retailer_redeem_id = $retailerRedeemId;
                    //                         $couponPayment->save();
                    //                     }
                    //                 }
                    //             }
                    //         }
                    //     }
                    // }

                    // Insert to promotion retailer, with delete first old data
                    $delete_coupon_retailer = CouponRetailer::where('promotion_id', '=', $promotion_id);
                    $delete_coupon_retailer->delete();

                    foreach ($retailer_ids as $retailer_id) {
                        $data = @json_decode($retailer_id);
                        $tenant_id = $data->tenant_id;
                        $mall_id = $data->mall_id;

                        if(! in_array($mall_id, $mallid)) {
                            $mallid[] = $mall_id;
                        }

                        if ($tenant_id === $mall_id) {
                            $isMall = 'mall';
                        } else {
                            $isMall = 'tenant';
                        }
                        // Insert new coupon retailer
                        $couponretailer = new CouponRetailer();
                        $couponretailer->retailer_id = $tenant_id;
                        $couponretailer->promotion_id = $promotion_id;
                        $couponretailer->object_type = $isMall;
                        $couponretailer->save();
                    }

                }
            });

            // Redeem to stores/malls
            OrbitInput::post('retailer_ids', function($retailer_ids) use ($promotion_id, $paymentProviders, $payByWallet, $currentCampaignStatus, $linkToTenantIds) {
                if ($currentCampaignStatus !== 'ongoing')
                {
                    // validate link to tenants/malls
                    if (count($linkToTenantIds) !== 0) {
                        $availableLinkToTenant = [];
                        foreach ($linkToTenantIds as $link_to_tenant_json) {
                            $data = @json_decode($link_to_tenant_json);
                            $availableLinkToTenant[] = $data->tenant_id;
                        }

                        foreach ($retailer_ids as $retailer_id) {
                            $data = @json_decode($retailer_id);
                            $tenant_id = $data->tenant_id;
                            if (! in_array($tenant_id, $availableLinkToTenant)) {
                                $errorMessage = 'Redeem to stores/malls does not match with Link to stores/malls';
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }
                    }

                    $existRetailer = CouponRetailerRedeem::where('promotion_id', '=', $promotion_id)->lists('promotion_retailer_redeem_id');
                    if (! empty($existRetailer)) {
                        $delete_provider = CouponPaymentProvider::whereIn('promotion_retailer_redeem_id', $existRetailer)->delete();
                    }

                    // Delete old data
                    $delete_retailer = CouponRetailerRedeem::where('promotion_id', '=', $promotion_id);
                    $delete_retailer->delete();

                    // Insert new data
                    $retailers = array();
                    $isMall = 'tenant';
                    $mallid = array();
                    foreach ($retailer_ids as $retailer_id) {
                        $data = @json_decode($retailer_id);
                        $tenant_id = $data->tenant_id;
                        $mall_id = $data->mall_id;

                        if(! in_array($mall_id, $mallid)) {
                            $mallid[] = $mall_id;
                        }

                        if ($tenant_id === $mall_id) {
                            $isMall = 'mall';
                        } else {
                            $isMall = 'tenant';
                        }


                        $retailer = new CouponRetailerRedeem();
                        $retailer->promotion_id = $promotion_id;
                        $retailer->retailer_id = $tenant_id;
                        $retailer->object_type = $isMall;
                        $retailer->save();

                        $retailerRedeemId = $retailer->promotion_retailer_redeem_id;

                        // save coupon payment provider
                        if ($payByWallet === 'Y') {
                            $dataPayment = @json_decode($paymentProviders);
                            foreach ($dataPayment as $data) {
                                foreach ((array) $data as $key => $value) {
                                    if ($key === $tenant_id) {
                                        foreach ($value as $provider) {
                                            $couponPayment = new CouponPaymentProvider();
                                            $couponPayment->payment_provider_id = $provider;
                                            $couponPayment->promotion_retailer_redeem_id = $retailerRedeemId;
                                            $couponPayment->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            });

            OrbitInput::post('employee_user_ids', function($employee_user_ids) use ($updatedcoupon) {
                // validate employee_user_ids
                $employee_user_ids = (array) $employee_user_ids;
                foreach ($employee_user_ids as $employee_user_id_check) {
                    $validator = Validator::make(
                        array(
                            'employee_user_id'   => $employee_user_id_check,
                        ),
                        array(
                            'employee_user_id'   => 'orbit.empty.employee',
                        )
                    );

                    Event::fire('orbit.coupon.postupdatecoupon.before.employeevalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.coupon.postupdatecoupon.after.employeevalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedcoupon->employee()->sync($employee_user_ids);

                // reload tenants relation
                $updatedcoupon->load('employee');
            });

            OrbitInput::post('partner_ids', function($partner_ids) use ($updatedcoupon, $promotion_id) {
                // validate retailer_ids
                $partner_ids = (array) $partner_ids;

                // Delete old data
                $delete_object_partner = ObjectPartner::where('object_id', '=', $promotion_id);
                $delete_object_partner->delete();

                $objectPartners = array();
                // Insert new data
                if(array_filter($partner_ids)) {
                    foreach ($partner_ids as $partner_id) {
                        $objectPartner = new ObjectPartner();
                        $objectPartner->object_id = $promotion_id;
                        $objectPartner->object_type = 'coupon';
                        $objectPartner->partner_id = $partner_id;
                        $objectPartner->save();
                        $objectPartners[] = $objectPartner;
                    }
                }
                $updatedcoupon->partners = $objectPartners;
            });

            OrbitInput::post('paument_provider_ids', function($paymentProvider) use ($updatedcoupon, $promotion_id) {
                // Delete exsisting data
                $deleteCouponPayment = CouponPaymentProvider::where('coupon_id', '=', $promotion_id);
                $deleteCouponPayment->delete();

                foreach ($paymentProvider as $provider) {
                    $couponPayment = new CouponPaymentProvider();
                    $couponPayment->payment_provider_id = $provider;
                    $couponPayment->coupon_id = $promotion_id;
                    $couponPayment->save();
                }
            });

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $promotion_id)
                                                    ->where('object_type', '=', 'coupon');
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatedcoupon, $merchant_id, $user, $promotion_id, $mallid) {
                // Insert new data
                $couponKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', 0)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = 0;
                        $newKeyword->keyword = $keyword;
                        $newKeyword->status = 'active';
                        $newKeyword->created_by = $user->user_id;
                        $newKeyword->modified_by = $user->user_id;
                        $newKeyword->save();

                        $keyword_id = $newKeyword->keyword_id;
                        $couponKeywords[] = $newKeyword;
                    } else {
                        $keyword_id = $existKeyword->keyword_id;
                        $couponKeywords[] = $existKeyword;
                    }


                    $newKeywordObject = new KeywordObject();
                    $newKeywordObject->keyword_id = $keyword_id;
                    $newKeywordObject->object_id = $promotion_id;
                    $newKeywordObject->object_type = 'coupon';
                    $newKeywordObject->save();
                }
                $updatedcoupon->keywords = $couponKeywords;
            });


            // Update product tags
            $deleted_product_tags_object = ProductTagObject::where('object_id', '=', $promotion_id)
                                                    ->where('object_type', '=', 'coupon');
            $deleted_product_tags_object->delete();

            OrbitInput::post('product_tags', function($productTags) use ($updatedcoupon, $merchant_id, $user, $promotion_id, $mallid) {
                // Insert new data
                $couponProductTags = array();
                foreach ($productTags as $productTag) {
                    $product_tag_id = null;

                    $existProductTag = ProductTag::excludeDeleted()
                        ->where('product_tag', '=', $productTag)
                        ->where('merchant_id', '=', 0)
                        ->first();

                    if (empty($existProductTag)) {
                        $newProductTag = new ProductTag();
                        $newProductTag->merchant_id = 0;
                        $newProductTag->product_tag = $productTag;
                        $newProductTag->status = 'active';
                        $newProductTag->created_by = $user->user_id;
                        $newProductTag->modified_by = $user->user_id;
                        $newProductTag->save();

                        $product_tag_id = $newProductTag->product_tag_id;
                        $couponProductTags[] = $newProductTag;
                    } else {
                        $product_tag_id = $existProductTag->product_tag_id;
                        $couponProductTags[] = $existProductTag;
                    }

                    $newProductTagObject = new ProductTagObject();
                    $newProductTagObject->product_tag_id = $product_tag_id;
                    $newProductTagObject->object_id = $promotion_id;
                    $newProductTagObject->object_type = 'coupon';
                    $newProductTagObject->save();
                }
                $updatedcoupon->product_tags = $couponProductTags;
            });

            Event::fire('orbit.coupon.postupdatecoupon.after.save', array($this, $updatedcoupon));

            OrbitInput::post('translations', function($translation_json_string) use ($updatedcoupon, $mallid, $is_3rd_party_promotion) {
                $is_third_party = $is_3rd_party_promotion === 'Y' ? TRUE : FALSE;
                $this->validateAndSaveTranslations($updatedcoupon, $translation_json_string, 'create', $is_third_party);
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
                        $isAvailable = CouponTranslation::where('promotion_id', '=', $promotion_id)
                                        ->whereRaw("
                                            {$prefix}coupon_translations.merchant_language_id = (
                                                SELECT language_id
                                                FROM {$prefix}languages
                                                WHERE name = (SELECT mobile_default_language FROM {$prefix}campaign_account WHERE user_id = {$this->quote($this->api->user->user_id)})
                                            )
                                        ")
                                        ->where(function($query) {
                                            $query->where('promotion_name', '=', '')
                                                  ->orWhere('description', '=', '')
                                                  ->orWhereNull('promotion_name')
                                                  ->orWhereNull('description');
                                          })
                                        ->first();

            $required_name = false;
            $required_desc = false;

            if (is_object($isAvailable)) {
                if ($val->promotion_name === '' || empty($val->promotion_name)) {
                    $required_name = true;
                }
                if ($val->description === '' || empty($val->description)) {
                    $required_desc = true;
                }
            }

            if ($required_name === true && $required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_both', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_name) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_name', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_desc) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_desc', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // update coupon advert
            if (! empty($campaignStatus) || $campaignStatus !== '') {
                $couponAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatedcoupon->promotion_id)
                                    ->update(['status'     => $updatedcoupon->status]);
            }

            if (! empty($end_date) || $end_date !== '') {
                $couponAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatedcoupon->promotion_id)
                                    ->where('end_date', '>', $updatedcoupon->end_date)
                                    ->update(['end_date' => $updatedcoupon->end_date]);
            }

            // Sync discounts...
            $updatedcoupon->discounts()->sync($this->mapDiscounts($discounts));

            $this->response->data = $updatedcoupon;

            // Commit the changes
            $this->commit();


            // Push notification
            $queueName = Config::get('queue.connections.gtm_notification.queue', 'gtm_notification');

            Queue::push('Orbit\\Queue\\Notification\\CouponMallNotificationQueue', [
                'coupon_id' => $updatedcoupon->promotion_id,
            ], $queueName);
            Queue::push('Orbit\\Queue\\Notification\\CouponStoreNotificationQueue', [
                'coupon_id' => $updatedcoupon->promotion_id,
            ], $queueName);

            // Successfull Update
            $activityNotes = sprintf('Coupon updated: %s', $updatedcoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon OK')
                    ->setObject($updatedcoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postupdatecoupon.after.commit', array($this, $updatedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage()." Line:".$e->getLine();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            Event::fire('orbit.coupon.postnewcoupon.custom.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();

        } catch (Exception $e) {
            Event::fire('orbit.coupon.postupdatecoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `promotion_id`                 (required) - ID of the coupon
     * @param string    `merchant_id`                  (required) - ID of the mall
     * @param string    `password`                     (required) - Password for deletion
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteCoupon()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletecoupon = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.postdeletecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.postdeletecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postdeletecoupon.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_coupon')) {
                Event::fire('orbit.coupon.postdeletecoupon.authz.notallowed', array($this, $user));
                $deleteCouponLang = Lang::get('validation.orbit.actionlist.delete_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.postdeletecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');
            $promotion_id = OrbitInput::post('promotion_id');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'current_mall'  => $merchant_id,
                    'promotion_id'  => $promotion_id,
                    'password'      => $password
                ),
                array(
                    'current_mall'  => 'required|orbit.empty.merchant',
                    'promotion_id'  => 'required|orbit.empty.coupon|orbit.issuedcoupon.exists',
                    'password'      => [
                        'required',
                        ['orbit.masterpassword.delete', $merchant_id]
                    ],
                )
            );

            Event::fire('orbit.coupon.postdeletecoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.postdeletecoupon.after.validation', array($this, $validator));

            $deletecoupon = Coupon::excludeDeleted()->where('promotion_id', $promotion_id)->first();
            $deletecoupon->status = 'deleted';
            $deletecoupon->modified_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postdeletecoupon.before.save', array($this, $deletecoupon));

            // hard delete retailer.
            $deleteretailers = CouponRetailerRedeem::where('promotion_id', $deletecoupon->promotion_id)->get();
            foreach ($deleteretailers as $deleteretailer) {
                $deleteretailer->delete();
            }

            foreach ($deletecoupon->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            $deletecoupon->save();

            Event::fire('orbit.coupon.postdeletecoupon.after.save', array($this, $deletecoupon));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.coupon');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Coupon Deleted: %s', $deletecoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon OK')
                    ->setObject($deletecoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postdeletecoupon.after.commit', array($this, $deletecoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postdeletecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postdeletecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postdeletecoupon.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.coupon.postdeletecoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Coupon
     *
     * @author Tian <tian@dominopos.com>
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: mall, tenants.
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, promotion_name, promotion_type, description, begin_date, end_date, status.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     * @param integer  `promotion_id`          (optional) - Coupon ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Coupon name
     * @param string   `promotion_name_like`   (optional) - Coupon name like
     * @param string   `promotion_type`        (optional) - Coupon type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param string   `long_description`      (optional) - Long description
     * @param string   `long_description_like` (optional) - Long description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `coupon_notification`   (optional) - Coupon notification. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param string   `rule_object_type`      (optional) - Rule object type. Valid value: product, family.
     * @param integer  `rule_object_id1`       (optional) - Rule object ID1 (product_id or category_id1).
     * @param integer  `rule_object_id2`       (optional) - Rule object ID2 (category_id2).
     * @param integer  `rule_object_id3`       (optional) - Rule object ID3 (category_id3).
     * @param integer  `rule_object_id4`       (optional) - Rule object ID4 (category_id4).
     * @param integer  `rule_object_id5`       (optional) - Rule object ID5 (category_id5).
     * @param string   `discount_object_type`  (optional) - Discount object type. Valid value: product, family, cash_rebate.
     * @param integer  `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer  `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer  `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer  `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer  `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param integer  `retailer_name`           (optional) - Retailer name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getsearchcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getsearchcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getsearchcoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.getsearchcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $keywords = OrbitInput::get('keywords');
            $currentmall = OrbitInput::get('current_mall');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'keywords' => $keywords,
                    'current_mall' => $currentmall
                ),
                array(
                    'sort_by' => 'in:registered_date,promotion_name,promotion_type,total_location,description,begin_date,end_date,status,is_permanent,rule_type,tenant_name,is_auto_issuance,display_discount_value,updated_at,coupon_status',
                    'keywords' => 'min:3',
                    'current_mall' => 'orbit.empty.merchant'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.coupon_sortby'),
                )
            );

            Event::fire('orbit.coupon.getsearchcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getsearchcoupon.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $table_prefix = DB::getTablePrefix();

            // optimize orb_media query greatly when coupon_id is present
            $mediaJoin = "";
            $mediaOptimize = " AND (object_name = 'coupon_translation') ";
            $mediaObjectIds = (array) OrbitInput::get('promotion_id', []);
            if (! empty ($mediaObjectIds)) {
                $mediaObjectIds = "'" . implode("', '", $mediaObjectIds) . "'";
                $mediaJoin = " LEFT JOIN {$table_prefix}coupon_translations mont ON mont.coupon_translation_id = {$table_prefix}media.object_id ";
                $mediaOptimize = " AND object_name = 'coupon_translation' AND mont.promotion_id IN ({$mediaObjectIds}) ";
            }

            $filterName = OrbitInput::get('promotion_name_like', '');

            // Builder object
            // Addition select case and join for sorting by discount_value.
            $coupons = Coupon::allowedForPMPUser($user, 'coupon')
                // ->with(['couponRule', 'discounts'])
                ->select(
                    DB::raw("
                        {$table_prefix}promotions.promotion_id,
                        {$table_prefix}promotions.promotion_name,
                        {$table_prefix}promotions.begin_date,
                        {$table_prefix}promotions.end_date,
                        {$table_prefix}promotions.status,
                        {$table_prefix}promotions.updated_at,
                        {$table_prefix}promotions.promotion_type,
                        {$table_prefix}promotions.promotion_id as campaign_id,
                        'coupon' as campaign_type,
                        {$table_prefix}coupon_translations.promotion_name AS display_name,
                        media.path as image_path,
                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 'expired' ELSE {$table_prefix}campaign_status.campaign_status_name END) END AS campaign_status,

                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.order ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 5 ELSE {$table_prefix}campaign_status.order END) END AS campaign_status_order"),
                    // DB::raw("(select GROUP_CONCAT(IF({$table_prefix}merchants.object_type = 'tenant', CONCAT({$table_prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$table_prefix}merchants.name)) separator ', ') from {$table_prefix}promotion_retailer
                    //                 inner join {$table_prefix}merchants on {$table_prefix}merchants.merchant_id = {$table_prefix}promotion_retailer.retailer_id
                    //                 inner join {$table_prefix}merchants pm on {$table_prefix}merchants.parent_id = pm.merchant_id
                    //                 where {$table_prefix}promotion_retailer.promotion_id = {$table_prefix}promotions.promotion_id) as campaign_location_names"),
                    // DB::raw("CASE {$table_prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END as 'is_auto_issue_on_signup'"),
                    DB::raw("CASE WHEN {$table_prefix}promotions.end_date IS NOT NULL THEN
                        CASE WHEN
                            DATE_FORMAT({$table_prefix}promotions.end_date, '%Y-%m-%d %H:%i:%s') = '0000-00-00 00:00:00' THEN {$table_prefix}promotions.status
                        WHEN
                            {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                    FROM {$table_prefix}merchants om
                                                                    LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                    WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                        THEN 'expired'
                        ELSE
                            {$table_prefix}promotions.status
                        END
                    ELSE
                        {$table_prefix}promotions.status
                    END as 'coupon_status'"),
                    DB::raw("COUNT(DISTINCT {$table_prefix}promotion_retailer.promotion_retailer_id) as total_location")
                    // DB::raw("(SELECT GROUP_CONCAT(issued_coupon_code separator '\n')
                    //     FROM {$table_prefix}issued_coupons ic
                    //     WHERE ic.promotion_id = {$table_prefix}promotions.promotion_id
                    //         ) as coupon_codes"),
                    // DB::raw("CASE
                    //             WHEN is_3rd_party_promotion = 'Y' AND is_3rd_party_field_complete = 'N' THEN 'not_available'
                    //             WHEN is_3rd_party_promotion = 'Y' AND {$table_prefix}pre_exports.object_id IS NOT NULL AND {$table_prefix}pre_exports.object_type = 'coupon' THEN 'in_progress'
                    //             WHEN is_3rd_party_promotion = 'Y' AND {$table_prefix}pre_exports.object_id IS NULL THEN 'available'
                    //             WHEN is_3rd_party_promotion = 'N' THEN 'not_available'
                    //         END AS export_status
                    //     ")
                    // ,
                    // DB::raw("IF({$table_prefix}promotions.is_all_gender = 'Y', 'A', {$table_prefix}promotions.is_all_gender) as gender"),
                    // DB::raw("{$table_prefix}promotions.max_quantity_per_purchase as max_qty_per_purchase"),
                    // DB::raw("{$table_prefix}promotions.max_quantity_per_user as max_qty_per_user")
                )
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                // Join for get export status
                // ->leftJoin('pre_exports', function ($join) {
                //          $join->on('promotions.promotion_id', '=', 'pre_exports.object_id')
                //               ->where('pre_exports.object_type', '=', 'coupon');
                //   })
                ->leftJoin(DB::raw("(
                        SELECT {$table_prefix}media.* FROM {$table_prefix}media
                        {$mediaJoin}
                        WHERE media_name_long = 'coupon_translation_image_resized_default'
                        {$mediaOptimize} ) as media
                    "), DB::raw('media.object_id'), '=', 'coupon_translations.coupon_translation_id')
                ->joinPromotionRules()
                ->groupBy('promotions.promotion_id');

            if($filterName === '') {
                // handle role campaign admin cause not join with campaign account
                if ($role->role_name === 'Campaign Admin' ) {
                    $coupons->where('languages.name', '=', DB::raw("(select ca.mobile_default_language from {$table_prefix}campaign_account ca where ca.user_id = {$this->quote($user->user_id)})"));
                } else {
                    $coupons->where('languages.name', '=', DB::raw("ca.mobile_default_language"));
                }
            }

            if (strtolower($user->role->role_name) === 'mall customer service') {
                $now = date('Y-m-d H:i:s');
                if (! empty($currentmall)) {
                    $mall = App::make('orbit.empty.merchant');
                    $now = Carbon::now($mall->timezone->timezone_name);
                }
                $prefix = DB::getTablePrefix();
                $coupons->whereRaw("('$now' >= {$prefix}promotions.begin_date and '$now' <= {$prefix}promotions.end_date)");
                $coupons->whereRaw("(((select count({$prefix}issued_coupons.promotion_id) from {$prefix}issued_coupons
                                        where {$prefix}issued_coupons.promotion_id={$prefix}promotions.promotion_id
                                        and status!='deleted') < {$prefix}promotions.maximum_issued_coupon) or
                                    ({$prefix}promotions.maximum_issued_coupon = 0 or {$prefix}promotions.maximum_issued_coupon is null))");
                $coupons->active('promotions');
            } else {
                $coupons->excludeDeleted('promotions');
            }

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_id', (array)$promotionIds);
            });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', '=', $promotionName);
            });

            // Filter coupon by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('coupon_translations.promotion_name', 'like', "%$promotionName%");
            });

            // Filter coupon by keywords for advert link to
            OrbitInput::get('keywords', function($keywords) use ($coupons)
            {
                $coupons->where('coupon_translations.promotion_name', 'like', "$keywords%");
            });

            // Filter coupon by description
            OrbitInput::get('description', function($description) use ($coupons)
            {
                $coupons->whereIn('promotions.description', $description);
            });

            // Filter coupon by matching description pattern
            OrbitInput::get('description_like', function($description) use ($coupons)
            {
                $coupons->where('promotions.description', 'like', "%$description%");
            });

            // Filter coupon by long description
            OrbitInput::get('long_description', function($long_description) use ($coupons)
            {
                $coupons->whereIn('promotions.long_description', $long_description);
            });

            // Filter coupon by matching long_description pattern
            OrbitInput::get('long_description_like', function($long_description) use ($coupons)
            {
                $coupons->where('promotions.long_description', 'like', "%$long_description%");
            });

            // Filter coupon by date
            OrbitInput::get('end_date', function($endDate) use ($coupons)
            {
                $coupons->where('promotions.begin_date', '<=', $endDate);
            });

            // Filter coupon by date
            OrbitInput::get('begin_date', function($begindate) use ($coupons)
            {
                $coupons->where('promotions.end_date', '>=', $begindate);
            });

            // Filter coupon by is permanent
            OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
                $coupons->whereIn('promotions.is_permanent', $isPermanent);
            });

            // Filter coupon by coupon notification
            OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
                $coupons->whereIn('promotions.coupon_notification', $couponNotification);
            });

            // Filter coupons by status
            OrbitInput::get('campaign_status', function ($statuses) use ($coupons, $table_prefix) {
                $coupons->whereIn(DB::raw("CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$table_prefix}merchants om
                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 'expired' ELSE {$table_prefix}campaign_status.campaign_status_name END) END"), $statuses);
            });

            // Filter coupon rule by rule type
            OrbitInput::get('rule_type', function ($ruleTypes) use ($coupons) {
                if (is_array($ruleTypes)) {
                    $coupons->whereHas('couponrule', function($q) use ($ruleTypes) {
                        $q->whereIn('rule_type', $ruleTypes);
                    });
                } else {
                    $coupons->whereHas('couponrule', function($q) use ($ruleTypes) {
                        $q->where(function($q) use ($ruleTypes) {
                            $q->where('rule_type', $ruleTypes);
                            $q->orWhereNull('rule_type');
                        });
                    });
                }
            });

            // Filter coupon merchants by retailer(tenant) name
            OrbitInput::get('tenant_name_like', function ($tenant_name_like) use ($coupons) {
                $coupons->whereHas('linkToTenants', function($q) use ($tenant_name_like) {
                    $q->where('merchants.name', 'like', "%$tenant_name_like%");
                });
            });

            // Filter coupon merchants by mall name
            // There is laravel bug regarding nested whereHas on the same table like in this case
            // news->tenant->mall : whereHas('tenant', function($q) { $q->whereHas('mall' ...)}) this is not gonna work
            OrbitInput::get('mall_name_like', function ($mall_name_like) use ($coupons, $table_prefix, $user) {
                $user_id = $user->user_id;
                $quote = function($arg)
                {
                    return DB::connection()->getPdo()->quote($arg);
                };
                $mall_name_like = "%" . $mall_name_like . "%";
                $mall_name_like = $quote($mall_name_like);
                $coupons->whereRaw(DB::raw("
                ((
                    (select count(mtenant.merchant_id) from {$table_prefix}merchants mtenant
                    inner join {$table_prefix}promotion_retailer opr on mtenant.merchant_id = opr.retailer_id
                    where mtenant.object_type = 'tenant' and opr.promotion_id = {$table_prefix}promotions.promotion_id and (
                        select count(mtenant.merchant_id) from {$table_prefix}merchants mmall
                        where mmall.object_type = 'mall' and
                        mtenant.parent_id = mmall.merchant_id and
                        mmall.name like {$mall_name_like} and
                        mmall.object_type = 'mall'
                    ) >= 1 and
                    mtenant.object_type = 'tenant' and
                    mtenant.is_mall = 'no' and
                    opr.object_type = 'tenant') >= 1
                )
                OR
                (
                    select count(mmallx.merchant_id) from {$table_prefix}merchants mmallx
                    inner join {$table_prefix}promotion_retailer oprx on mmallx.merchant_id = oprx.retailer_id
                    inner join {$table_prefix}user_campaign ucp on ucp.campaign_id = oprx.promotion_id
                    where mmallx.object_type = 'mall' and
                    ucp.user_id = '{$user_id}' and
                    mmallx.name like {$mall_name_like} and
                    mmallx.object_type = 'mall'
                ))
                "));
            });

             // Filter coupon rule by rule object type
            OrbitInput::get('rule_object_type', function ($ruleObjectTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectTypes) {
                    $q->whereIn('rule_object_type', $ruleObjectTypes);
                });
            });

            // Filter coupon rule by rule object id1
            OrbitInput::get('rule_object_id1', function ($ruleObjectId1s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId1s) {
                    $q->whereIn('rule_object_id1', $ruleObjectId1s);
                });
            });

            // Filter coupon rule by rule object id2
            OrbitInput::get('rule_object_id2', function ($ruleObjectId2s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId2s) {
                    $q->whereIn('rule_object_id2', $ruleObjectId2s);
                });
            });

            // Filter coupon rule by rule object id3
            OrbitInput::get('rule_object_id3', function ($ruleObjectId3s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId3s) {
                    $q->whereIn('rule_object_id3', $ruleObjectId3s);
                });
            });

            // Filter coupon rule by rule object id4
            OrbitInput::get('rule_object_id4', function ($ruleObjectId4s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId4s) {
                    $q->whereIn('rule_object_id4', $ruleObjectId4s);
                });
            });

            // Filter coupon rule by rule object id5
            OrbitInput::get('rule_object_id5', function ($ruleObjectId5s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId5s) {
                    $q->whereIn('rule_object_id5', $ruleObjectId5s);
                });
            });

            // Filter coupon rule by discount object type
            OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectTypes) {
                    $q->whereIn('discount_object_type', $discountObjectTypes);
                });
            });

            // Filter coupon rule by discount object id1
            OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId1s) {
                    $q->whereIn('discount_object_id1', $discountObjectId1s);
                });
            });

            // Filter coupon rule by discount object id2
            OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId2s) {
                    $q->whereIn('discount_object_id2', $discountObjectId2s);
                });
            });

            // Filter coupon rule by discount object id3
            OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId3s) {
                    $q->whereIn('discount_object_id3', $discountObjectId3s);
                });
            });

            // Filter coupon rule by discount object id4
            OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId4s) {
                    $q->whereIn('discount_object_id4', $discountObjectId4s);
                });
            });

            // Filter coupon rule by discount object id5
            OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId5s) {
                    $q->whereIn('discount_object_id5', $discountObjectId5s);
                });
            });

            // Filter coupon by retailer id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($coupons) {
                $coupons->whereHas('tenants', function($q) use ($retailerIds) {
                    $q->whereIn('retailer_id', $retailerIds);
                });
            });

            // Filter
            OrbitInput::get('retailer_name', function ($name) use ($coupons) {
                $coupons->where('merchants.merchant_name', 'like', "%$name%");
            });

            // Filter coupon by rule begin date
            OrbitInput::get('rule_begin_date', function ($beginDate) use ($coupons)
            {
                $coupons->whereHas('couponrule', function ($q) use ($beginDate) {
                    $q->where('rule_begin_date', '<=', $beginDate);
                });
            });

            // Filter coupon by end date
            OrbitInput::get('rule_end_date', function ($endDate) use ($coupons)
            {
                $coupons->whereHas('couponrule', function ($q) use ($endDate) {
                    $q->where('rule_end_date', '>=', $endDate);
                });
            });

            $from_cs = OrbitInput::get('from_cs', 'no');

            // Add new relation based on request
            // OrbitInput::get('with', function ($with) use ($coupons, $from_cs) {
            //     $with = (array) $with;

            //     foreach ($with as $relation) {
            //         if ($relation === 'mall') {
            //             $coupons->with('mall');
            //         } elseif ($relation === 'tenants') {
            //             if ($from_cs === 'yes') {
            //                 $coupons->with(array('tenants' => function($q) {
            //                     $q->where('merchants.status', 'active');
            //                 }));
            //             } else {
            //                 $coupons->with('tenants');
            //             }
            //         } elseif ($relation === 'tenants.mall') {
            //             if ($from_cs === 'yes') {
            //                 $coupons->with(array('tenants' => function($q) {
            //                     $q->where('merchants.status', 'active');
            //                     $q->with('mall');
            //                 }));
            //             } else {
            //                 $coupons->with('tenants.mall');
            //             }
            //         } elseif ($relation === 'translations') {
            //             $coupons->with('translations');
            //         } elseif ($relation === 'translations.media') {
            //             $coupons->with('translations.media');
            //         } elseif ($relation === 'employee') {
            //             $coupons->with('employee.employee.retailers');
            //         } elseif ($relation === 'link_to_tenants') {
            //             $coupons->with('linkToTenants');
            //         } elseif ($relation === 'link_to_tenants.mall') {
            //             if ($from_cs === 'yes') {
            //                 $coupons->with(array('linkToTenants' => function($q) {
            //                     $q->where('merchants.status', 'active');
            //                     $q->with('mall');
            //                 }));
            //             } else {
            //                 $coupons->with('linkToTenants.mall');
            //             }
            //         } elseif ($relation === 'campaignLocations') {
            //             $coupons->with('campaignLocations');
            //         } elseif ($relation === 'campaignLocations.mall') {
            //             $coupons->with('campaignLocations.mall');
            //         } elseif ($relation === 'ages') {
            //             $coupons->with('ages');
            //         } elseif ($relation === 'keywords') {
            //             $coupons->with('keywords');
            //         } elseif ($relation === 'product_tags') {
            //             $coupons->with('product_tags');
            //         } elseif ($relation === 'campaignObjectPartners') {
            //             $coupons->with('campaignObjectPartners');
            //         }
            //     }
            // });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;

            if (! $this->returnBuilder) {
                // Get the take args
                $take = $perPage;
                OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;

                    if ((int)$take <= 0) {
                        $take = $maxRecord;
                    }
                });
                $coupons->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $coupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'coupon_translations.promotion_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'        => 'promotions.created_at',
                    'promotion_name'         => 'coupon_translations.promotion_name',
                    'promotion_type'         => 'promotions.promotion_type',
                    'description'            => 'promotions.description',
                    'begin_date'             => 'promotions.begin_date',
                    'end_date'               => 'promotions.end_date',
                    'updated_at'             => 'promotions.updated_at',
                    'is_permanent'           => 'promotions.is_permanent',
                    'status'                 => 'campaign_status_order',
                    'rule_type'              => 'rule_type',
                    'total_location'         => 'total_location',
                    'tenant_name'            => 'tenant_name',
                    'is_auto_issuance'       => 'is_auto_issue_on_signup',
                    'display_discount_value' => 'display_discount_value',
                    'coupon_status'          => 'coupon_status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'coupon_translations.promotion_name') {
                if ($sortBy === 'campaign_status_order') {
                    $coupons->orderBy('promotions.updated_at', 'desc');
                }
                else {
                    $coupons->orderBy('coupon_translations.promotion_name', 'asc');
                }
            }

            $totalCoupons = RecordCounter::create($_coupons)->count();
            $listOfCoupons = $coupons->get();
            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $coupons, 'count' => $totalCoupons];
            }

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.coupon.getsearchcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getsearchcoupon.before.render', array($this, &$output));

        return $output;
    }

    public function getDetailCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getdetailcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getdetailcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getdetailcoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.getdetailcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::get('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                ),
                array(
                    'promotion_id' => 'required|orbit.exist.coupon'
                ),
                array(
                    'orbit.exist.coupon' => 'coupon not found',
                )
            );

            Event::fire('orbit.coupon.getdetailcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getdetailcoupon.after.validation', array($this, $validator));

            $table_prefix = DB::getTablePrefix();

            // optimize orb_media query greatly when coupon_id is present
            $mediaJoin = "";
            $mediaOptimize = " AND (object_name = 'coupon_translation') ";
            $mediaObjectIds = (array) OrbitInput::get('promotion_id', []);
            if (! empty ($mediaObjectIds)) {
                $mediaObjectIds = "'" . implode("', '", $mediaObjectIds) . "'";
                $mediaJoin = " LEFT JOIN {$table_prefix}coupon_translations mont ON mont.coupon_translation_id = {$table_prefix}media.object_id ";
                $mediaOptimize = " AND object_name = 'coupon_translation' AND mont.promotion_id IN ({$mediaObjectIds}) ";
            }

            $filterName = OrbitInput::get('promotion_name_like', '');

            // Builder object
            // Addition select case and join for sorting by discount_value.
            $coupons = Coupon::allowedForPMPUser($user, 'coupon')
                ->with(['couponRule', 'discounts', 'translations', 'translations.media', 'genders', 'ages', 'keywords', 'campaignObjectPartners', 'product_tags'])
                ->select(
                    DB::raw("{$table_prefix}promotions.*, {$table_prefix}promotions.promotion_id as campaign_id, 'coupon' as campaign_type, {$table_prefix}coupon_translations.promotion_name AS display_name, media.path as image_path,
                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 'expired' ELSE {$table_prefix}campaign_status.campaign_status_name END) END AS campaign_status,

                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.order ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 5 ELSE {$table_prefix}campaign_status.order END) END AS campaign_status_order,

                    {$table_prefix}campaign_status.order,
                    CASE rule_type
                        WHEN 'cart_discount_by_percentage' THEN 'percentage'
                        WHEN 'product_discount_by_percentage' THEN 'percentage'
                        WHEN 'cart_discount_by_value' THEN 'value'
                        WHEN 'product_discount_by_value' THEN 'value'
                        ELSE NULL
                    END AS 'display_discount_type',
                    CASE rule_type
                        WHEN 'cart_discount_by_percentage' THEN discount_value * 100
                        WHEN 'product_discount_by_percentage' THEN discount_value * 100
                        ELSE discount_value
                    END AS 'display_discount_value'
                    "),
                    DB::raw("(select GROUP_CONCAT(IF({$table_prefix}merchants.object_type = 'tenant', CONCAT({$table_prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$table_prefix}merchants.name)) separator ', ') from {$table_prefix}promotion_retailer
                                    inner join {$table_prefix}merchants on {$table_prefix}merchants.merchant_id = {$table_prefix}promotion_retailer.retailer_id
                                    inner join {$table_prefix}merchants pm on {$table_prefix}merchants.parent_id = pm.merchant_id
                                    where {$table_prefix}promotion_retailer.promotion_id = {$table_prefix}promotions.promotion_id) as campaign_location_names"),
                    DB::raw("CASE {$table_prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END as 'is_auto_issue_on_signup'"),
                    DB::raw("CASE WHEN {$table_prefix}promotions.end_date IS NOT NULL THEN
                        CASE WHEN
                            DATE_FORMAT({$table_prefix}promotions.end_date, '%Y-%m-%d %H:%i:%s') = '0000-00-00 00:00:00' THEN {$table_prefix}promotions.status
                        WHEN
                            {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                    FROM {$table_prefix}merchants om
                                                                    LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                    WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                        THEN 'expired'
                        ELSE
                            {$table_prefix}promotions.status
                        END
                    ELSE
                        {$table_prefix}promotions.status
                    END as 'coupon_status'"),
                    DB::raw("COUNT(DISTINCT {$table_prefix}promotion_retailer.promotion_retailer_id) as total_location"),
                    // DB::raw("(SELECT GROUP_CONCAT((CASE WHEN ic.issued_coupon_code = 'shortlink' THEN ic.url ELSE ic.issued_coupon_code END) separator '\n')
                    //     FROM {$table_prefix}issued_coupons ic
                    //     WHERE ic.promotion_id = {$table_prefix}promotions.promotion_id
                    //         ) as coupon_codes"),
                    DB::raw("CASE
                                WHEN is_3rd_party_promotion = 'Y' AND is_3rd_party_field_complete = 'N' THEN 'not_available'
                                WHEN is_3rd_party_promotion = 'Y' AND {$table_prefix}pre_exports.object_id IS NOT NULL AND {$table_prefix}pre_exports.object_type = 'coupon' THEN 'in_progress'
                                WHEN is_3rd_party_promotion = 'Y' AND {$table_prefix}pre_exports.object_id IS NULL THEN 'available'
                                WHEN is_3rd_party_promotion = 'N' THEN 'not_available'
                            END AS export_status
                        "),
                    DB::raw("IF({$table_prefix}promotions.is_all_gender = 'Y', 'A', {$table_prefix}promotions.is_all_gender) as gender"),
                    DB::raw("{$table_prefix}promotions.max_quantity_per_purchase as max_qty_per_purchase"),
                    DB::raw("{$table_prefix}promotions.max_quantity_per_user as max_qty_per_user"),
                    'promotions.long_description as terms_and_condition'
                )
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                // Join for get export status
                ->leftJoin('pre_exports', function ($join) {
                         $join->on('promotions.promotion_id', '=', 'pre_exports.object_id')
                              ->where('pre_exports.object_type', '=', 'coupon');
                  })
                ->leftJoin(DB::raw("(
                        SELECT {$table_prefix}media.* FROM {$table_prefix}media
                        {$mediaJoin}
                        WHERE media_name_long = 'coupon_translation_image_resized_default'
                        {$mediaOptimize} ) as media
                    "), DB::raw('media.object_id'), '=', 'coupon_translations.coupon_translation_id')
                ->joinPromotionRules()
                ->where('promotions.promotion_id', '=', $promotion_id)
                ->first();

            $this->response->data = $coupons;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getdetailcoupon.access.forbidden', array($this, $e));
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getdetailcoupon.invalid.arguments', array($this, $e));
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getdetailcoupon.query.error', array($this, $e));
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
            Event::fire('orbit.coupon.getdetailcoupon.general.exception', array($this, $e));
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getdetailcoupon.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Redeem Coupon for retailer/tenant
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string      `promotion_id`                    (required) - ID of the coupon
     * @param string      `merchant_id`                     (required) - ID of the mall
     * @param string      `merchant_verification_number`    (required) - Merchant/Tenant verification number
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postRedeemCoupon()
    {
        $activity = Activity::mobileci()
                          ->setActivityType('coupon');

        $user = NULL;
        $mall = NULL;
        $mall_id = NULL;
        $issuedcoupon = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.redeemcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.redeemcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            Event::fire('orbit.coupon.redeemcoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponModifiyRolesWithConsumer;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.redeemcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::post('current_mall');
            $storeId = OrbitInput::post('store_id');
            $language = OrbitInput::get('language', 'id');

            // set language
            App::setLocale($language);

            $issuedCouponId = OrbitInput::post('issued_coupon_id');
            $verificationNumber = OrbitInput::post('merchant_verification_number', null);
            $paymentProvider = OrbitInput::post('provider_id', 0);
            $phone = OrbitInput::post('phone', null);
            $amount = OrbitInput::post('amount', 0);
            $currency = OrbitInput::post('currency', 'IDR');

            $validator = Validator::make(
                array(
                    'store_id' => $storeId,
                    'current_mall' => $mall_id,
                ),
                array(
                    'store_id' => 'required',
                    'current_mall' => 'required|orbit.empty.merchant',
                )
            );
            $validator2 = Validator::make(
                array(
                    'issued_coupon_id' => $issuedCouponId,
                ),
                array(
                    'issued_coupon_id' => 'required',
                )
            );
            Event::fire('orbit.coupon.redeemcoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            // Run the validation
            if ($validator2->fails()) {
                $errorMessage = $validator2->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.postissuedcoupon.after.validation', array($this, $validator));

            $currencies = Currency::where('currency_code', $currency)->first();
            if (empty($currencies)) {
                $errorMessage = 'Currency not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $redeem_retailer_id = NULL;
            $redeem_user_id = NULL;
            $providerName = 'normal';
            $paymentType = 'normal';

            $issuedCoupon = IssuedCoupon::with(['payment.details.normal_paypro_detail'])
                                        ->where('issued_coupon_id', $issuedCouponId)
                                        ->where('status', 'issued')
                                        ->first();

            if (empty($issuedCoupon)) {
                OrbitShopAPI::throwInvalidArgument('Issued coupon ID is not found');
            }

            $coupon = Coupon::where('promotion_id', $issuedCoupon->promotion_id)->first();

            if ($mall_id !== $storeId) {
                $redeemPlace = BaseStore::select('base_stores.base_store_id', 'base_stores.merchant_id', 'base_stores.phone', 'base_stores.base_merchant_id', 'base_merchants.name as store_name', 'merchants.country_id', 'timezones.timezone_name', 'merchants.name as mall_name')
                      ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                      ->join('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                      ->leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
                      ->where('base_stores.base_store_id', $storeId)
                      ->where('base_stores.merchant_id', $mall_id)
                      ->first();
            } else {
                // redeem at mall cs
                $redeemPlace = Mall::leftJoin('timezones', 'timezones.timezone_id', '=', 'merchants.timezone_id')
                    ->excludeDeleted()
                    ->where('merchant_id', $mall_id)
                    ->first();

                $redeemPlace->base_merchant_id = null;
                $redeemPlace->store_name = 'Mall CS';
                $redeemPlace->base_store_id = null;
                $redeemPlace->mall_name = $redeemPlace->name;
            }

            $body = [
                'user_email'             => $user->user_email,
                'user_name'              => $user->user_firstname . ' ' . $user->user_lastname,
                'user_id'                => $user->user_id,
                'country_id'             => $redeemPlace->country_id,
                'payment_type'           => $paymentType,
                'merchant_id'            => $redeemPlace->base_merchant_id,
                'merchant_name'          => $redeemPlace->store_name,
                'store_id'               => $redeemPlace->base_store_id,
                'store_name'             => $redeemPlace->store_name,
                'timezone_name'          => $redeemPlace->timezone_name,
                'building_id'            => $redeemPlace->merchant_id,
                'building_name'          => $redeemPlace->mall_name,
                'object_id'              => $issuedCoupon->promotion_id,
                'object_type'            => 'coupon',
                'object_name'            => $coupon->promotion_name,
                'coupon_redemption_code' => $issuedCoupon->issued_coupon_code,
                'payment_provider_id'    => $paymentProvider,
                'payment_method'         => $providerName,
                'currency_id'            => $currencies->currency_id,
                'currency'               => $currency,
                'issued_coupon_id'       => $issuedCouponId,
            ];

            // Maual redeem
            if ($paymentProvider === '0') {
                if (empty($verificationNumber)) {
                    $errorMessage = 'Verification number is empty';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $tenant = Tenant::active()
                    ->where('merchant_id', $storeId)
                    ->where('masterbox_number', $verificationNumber)
                    ->first();

                $csVerificationNumber = UserVerificationNumber::
                    where('merchant_id', $mall_id)
                    ->where('verification_number', $verificationNumber)
                    ->first();

                if (! is_object($tenant) && ! is_object($csVerificationNumber)) {
                    // @Todo replace with language
                    $message = Lang::get('validation.orbit.formaterror.verification_code');
                    ACL::throwAccessForbidden($message);
                } else {
                    if (is_object($tenant)) {
                        $redeem_retailer_id = $tenant->merchant_id;
                    }
                    if (is_object($csVerificationNumber)) {
                        $redeem_user_id = $csVerificationNumber->user_id;
                        $redeem_retailer_id = $mall_id;
                    }
                }

                $body['commission_fixed_amount'] = $coupon->fixed_amount_commission;
            } else {
                // Redeem using paypro etc
                $redeem_retailer_id = $storeId;

                $paymentType = 'wallet';

                $provider = MerchantStorePaymentProvider::select('payment_providers.payment_provider_id', 'payment_providers.payment_name', 'merchant_store_payment_provider.mdr', 'payment_providers.mdr as default_mdr', 'payment_providers.mdr_commission', 'merchant_store_payment_provider.phone_number_for_sms')
                                                        ->join('payment_providers', 'payment_providers.payment_provider_id', '=', 'merchant_store_payment_provider.payment_provider_id')
                                                        ->where('merchant_store_payment_provider.payment_provider_id', $paymentProvider)
                                                        ->where('merchant_store_payment_provider.object_type', 'store')
                                                        ->where('merchant_store_payment_provider.object_id', $storeId)
                                                        ->where('payment_providers.status', 'active')
                                                        ->first();

                if (empty($provider)) {
                    $errorMessage = 'Payment profider not found';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $merchantBank = ObjectBank::select('banks.bank_id', 'banks.bank_name', 'banks.description', 'object_banks.account_name', 'object_banks.account_number', 'object_banks.swift_code', 'object_banks.bank_address')
                                ->join('banks', 'banks.bank_id', '=', 'object_banks.bank_id')
                                ->where('object_banks.object_id', $storeId)
                                ->where('object_banks.object_type', 'store')
                                ->where('banks.status', 'active')
                                ->orderBy('banks.bank_name', 'asc');

                $bank = clone $merchantBank;
                // prioritize bca then mandiri
                $bank = $bank->whereIn('banks.bank_name', ['bca','mandiri'])->first();

                $bankGotomalls = null;
                if (! empty($bank)) {
                    // find bank gotomalls which are the same as merchant bank
                    $bankGotomalls = BankGotomall::join('banks', 'banks.bank_id', '=', 'banks_gotomalls.bank_id')
                                                 ->where('banks_gotomalls.bank_id', $bank->bank_id)
                                                 ->where('banks_gotomalls.payment_provider_id', $paymentProvider)
                                                 ->where('banks_gotomalls.status', 'active')
                                                 ->first();
                }

                if (! is_object($bankGotomalls)) {
                    // Falls back to 1 first active gotomalls bank
                    $bankGotomalls = BankGotomall::join('banks', 'banks.bank_id', '=', 'banks_gotomalls.bank_id')
                                                ->where('banks_gotomalls.payment_provider_id', $paymentProvider)
                                                ->where('banks_gotomalls.status', 'active')
                                                ->first();
                }

                if (empty($bankGotomalls)) {
                    $errorMessage = 'Bank for payment provider ' . $provider->payment_name . ' not found';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $merchantBank = $merchantBank->first();
                $merchantBankId = null;
                $merchantBankAccountName = null;
                $merchantBankAccountNumber = null;
                $merchantBankName = null;
                $merchantBankSwiftCode = null;
                $merchantBankAddress = null;
                if (! empty($merchantBank)) {
                    $merchantBankId = $merchantBank->bank_id;
                    $merchantBankAccountName = $merchantBank->account_name;
                    $merchantBankAccountNumber = $merchantBank->account_number;
                    $merchantBankName = $merchantBank->bank_name;
                    $merchantBankSwiftCode = $merchantBank->swift_code;
                    $merchantBankAddress = $merchantBank->bank_address;
                }

                $providerName = $provider->payment_name;

                $body['to'] = $phone;
                $body['payment_type'] = $paymentType;
                $body['amount'] = $amount;
                $body['phone_number_for_sms'] = $provider->phone_number_for_sms;
                $body['payment_provider_id'] = $paymentProvider;
                $body['payment_method'] = $providerName;
                $body['mdr'] = $provider->mdr;
                $body['default_mdr'] = $provider->default_mdr;
                $body['provider_mdr_commission_percentage'] = $provider->mdr_commission;
                $body['commission_transaction_percentage'] = $coupon->transaction_amount_commission;
                $body['gtm_bank_id'] = $bankGotomalls->bank_id;
                $body['gtm_bank_account_name'] = $bankGotomalls->account_name;
                $body['gtm_bank_account_number'] = $bankGotomalls->account_number;
                $body['gtm_bank_name'] = $bankGotomalls->bank_name;
                $body['gtm_bank_swift_code'] = $bankGotomalls->swift_code;
                $body['gtm_bank_address'] = $bankGotomalls->bank_address;
                $body['merchant_bank_id'] = $merchantBankId;
                $body['merchant_bank_account_name'] = $merchantBankAccountName;
                $body['merchant_bank_account_number'] = $merchantBankAccountNumber;
                $body['merchant_bank_name'] = $merchantBankName;
                $body['merchant_bank_swift_code'] = $merchantBankSwiftCode;
                $body['merchant_bank_address'] = $merchantBankAddress;
            }

            // Only send payment request to orbit-payment API if the coupon is NOT hot_deals and sepulsa
            $transactionId = '';
            if (in_array($coupon->promotion_type, [Coupon::TYPE_HOT_DEALS])) {
                // IssuedCoupon record should have transaction_id set...
                $transactionId = $issuedCoupon->transaction_id;

                if ($coupon->promotion_type === Coupon::TYPE_HOT_DEALS) {
                    $redeemLocationInfo = $issuedCoupon->payment->details->first()->normal_paypro_detail;

                    $redeemLocationInfo->merchant_id            = $redeemPlace->base_merchant_id;
                    $redeemLocationInfo->merchant_name          = $redeemPlace->store_name;
                    $redeemLocationInfo->store_id               = $redeemPlace->base_store_id;
                    $redeemLocationInfo->store_name             = $redeemPlace->store_name;
                    $redeemLocationInfo->building_id            = $redeemPlace->merchant_id;
                    $redeemLocationInfo->building_name          = $redeemPlace->mall_name;

                    $redeemLocationInfo->save();
                }
            }
            elseif (in_array($coupon->promotion_type, [Coupon::TYPE_NORMAL])) {
                // Saving to payment_transaction
                $transaction = new PaymentTransaction();
                $transaction->user_email = $body['user_email'];
                $transaction->user_name = $body['user_name'];
                $transaction->user_id = $body['user_id'];
                $transaction->country_id = $body['country_id'];
                $transaction->payment_provider_id = $body['payment_provider_id'];
                $transaction->payment_method = $body['payment_method'];
                $transaction->currency = $body['currency'];
                $transaction->status = 'success';
                $transaction->timezone_name = $body['timezone_name'];
                $transaction->post_data = '-';
                $transaction->save();

                // Saving to payment transaction detail
                $paymentTransactionDetail = new PaymentTransactionDetail();
                $paymentTransactionDetail->payment_transaction_id = $transaction->payment_transaction_id;
                $paymentTransactionDetail->object_id = $body['object_id'];
                $paymentTransactionDetail->object_type = $body['object_type'];
                $paymentTransactionDetail->object_name = $body['object_name'];
                $paymentTransactionDetail->currency = $body['currency'];
                $paymentTransactionDetail->save();

                //Saving to payment_normal_paypro_detail
                $paymentNormalPayproDetail = new PaymentTransactionDetailNormalPaypro();
                $paymentNormalPayproDetail->payment_transaction_detail_id = $paymentTransactionDetail->payment_transaction_detail_id;
                $paymentNormalPayproDetail->merchant_id = $body['merchant_id'];
                $paymentNormalPayproDetail->merchant_name = $body['merchant_name'];
                $paymentNormalPayproDetail->store_id = $body['store_id'];
                $paymentNormalPayproDetail->store_name = $body['store_name'];
                $paymentNormalPayproDetail->building_id = $body['building_id'];
                $paymentNormalPayproDetail->building_name = $body['building_name'];
                $paymentNormalPayproDetail->save();

                $transactionId = $transaction->payment_transaction_id;
            }
            // else {
            //     $paymentConfig = Config::get('orbit.payment_server');
            //     $paymentClient = PaymentClient::create($paymentConfig)->setFormParam($body);
            //     $response = $paymentClient->setEndPoint('api/v1/pay')
            //                             ->request('POST');

            //     if ($response->status !== 'success') {
            //         $errorMessage = 'Transaction Failed';
            //         OrbitShopAPI::throwInvalidArgument($errorMessage);
            //     }

            //     $transactionId = $response->data->transaction_id;
            // }

            $mall = App::make('orbit.empty.merchant');

            $issuedcoupon = IssuedCoupon::where('issued_coupon_id', $issuedCouponId)
                                        ->where('status', 'issued')
                                        ->first();

            if ($paymentProvider === '0') {
                $issuedcoupon->transaction_id = $transactionId;
                $issuedcoupon->redeemed_date = date('Y-m-d H:i:s');
                $issuedcoupon->redeem_retailer_id = $redeem_retailer_id;
                $issuedcoupon->redeem_user_id = $redeem_user_id;
                $issuedcoupon->redeem_verification_code = $verificationNumber;
                $issuedcoupon->status = 'redeemed';

                Event::fire('orbit.coupon.postissuedcoupon.before.save', array($this, $issuedcoupon));

                $issuedcoupon->save();
            }

            Event::fire('orbit.coupon.postissuedcoupon.after.save', array($this, $issuedcoupon));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.coupon');

            // Commit the changes
            $this->commit();

            $data = new stdclass();
            $data->issued_coupon_code = $issuedcoupon->issued_coupon_code;
            $data->transaction_id = $transactionId;

            $this->response->message = 'Coupon has been successfully redeemed.';
            $this->response->data = $data;

            if ($paymentProvider === '0' || $coupon->promotion_type === Coupon::TYPE_HOT_DEALS) {
                // Successfull Creation
                $activityNotes = sprintf('Coupon Redeemed: %s', $issuedcoupon->coupon->promotion_name);

                if ($coupon->promotion_type === Coupon::TYPE_HOT_DEALS) {
                    $activityNotes = Coupon::TYPE_HOT_DEALS;
                }

                $payment = PaymentTransaction::find($transactionId);

                // send google analytics event
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Redeem Coupon Successful',
                        'ec' => 'Coupon',
                        'el' => $issuedcoupon->coupon->promotion_name,
                        'cs' => is_object($payment) ? $payment->utm_source : null,
                        'cm' => is_object($payment) ? $payment->utm_medium : null,
                        'cn' => is_object($payment) ? $payment->utm_campaign : null,
                        'ck' => is_object($payment) ? $payment->utm_term : null,
                        'cc' => is_object($payment) ? $payment->utm_content : null
                    ])
                    ->request();

                $activity->setUser($user)
                        ->setActivityName('redeem_coupon')
                        ->setActivityNameLong('Coupon Redemption (Successful)')
                        ->setObject($coupon)
                        ->setNotes($activityNotes)
                        ->setLocation($mall)
                        ->setModuleName('Coupon')
                        ->responseOK();

                $activity->coupon_id = $issuedcoupon->promotion_id;
                $activity->coupon_name = $issuedcoupon->coupon->promotion_name;

                $activity->save();
            }

            Event::fire('orbit.coupon.postissuedcoupon.after.commit', array($this, $issuedcoupon, $user, $body));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.redeemcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            if (isset($issuedcoupon->coupon)) {

                // send google analytics event
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Redeem Coupon Failed',
                        'ec' => 'Coupon',
                        'el' => $issuedcoupon->coupon->promotion_name
                    ])
                    ->request();
            }

            // Deletion failed Activity log
            if ($paymentProvider === '0') {
                $activity->setUser($user)
                        ->setActivityName('redeem_coupon')
                        ->setActivityNameLong('Coupon Redemption (Failed)')
                        ->setObject($issuedcoupon)
                        ->setNotes($e->getMessage())
                        ->setLocation($mall)
                        ->setModuleName('Coupon')
                        ->responseFailed()
                        ->save();
            }

        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.redeemcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            if (isset($issuedcoupon->coupon)) {
                // send google analytics event
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Redeem Coupon Failed',
                        'ec' => 'Coupon',
                        'el' => $issuedcoupon->coupon->promotion_name
                    ])
                    ->request();
            }

            // Deletion failed Activity log
            if ($paymentProvider === '0') {
                $activity->setUser($user)
                        ->setActivityName('redeem_coupon')
                        ->setActivityNameLong('Coupon Redemption (Failed)')
                        ->setObject($issuedcoupon)
                        ->setNotes($e->getMessage())
                        ->setLocation($mall)
                        ->setModuleName('Coupon')
                        ->responseFailed()
                        ->save();
            }

        } catch (QueryException $e) {
            Event::fire('orbit.coupon.redeemcoupon.query.error', array($this, $e));

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

            if (isset($issuedcoupon->coupon)) {
                // send google analytics event
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Redeem Coupon Failed',
                        'ec' => 'Coupon',
                        'el' => $issuedcoupon->coupon->promotion_name
                    ])
                    ->request();
            }

            // Deletion failed Activity log
            if ($paymentProvider === '0') {
                $activity->setUser($user)
                        ->setActivityName('redeem_coupon')
                        ->setActivityNameLong('Coupon Redemption (Failed)')
                        ->setObject($issuedcoupon)
                        ->setNotes($e->getMessage())
                        ->setLocation($mall)
                        ->setModuleName('Coupon')
                        ->responseFailed()
                        ->save();
            }

        } catch (Exception $e) {
            Event::fire('orbit.coupon.redeemcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();


            if (isset($issuedcoupon->coupon)) {
                // send google analytics event
                GMP::create(Config::get('orbit.partners_api.google_measurement'))
                    ->setQueryString([
                        'cid' => time(),
                        't' => 'event',
                        'ea' => 'Redeem Coupon Failed',
                        'ec' => 'Coupon',
                        'el' => $issuedcoupon->coupon->promotion_name
                    ])
                    ->request();
            }

            // Deletion failed Activity log
            if ($paymentProvider === '0') {
                $activity->setUser($user)
                        ->setActivityName('delete_coupon')
                        ->setActivityNameLong('Delete Coupon Failed')
                        ->setObject($issuedcoupon)
                        ->setNotes($e->getMessage())
                        ->setLocation($mall)
                        ->setModuleName('Coupon')
                        ->responseFailed()
                        ->save();
            }
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - Search Coupon - List By Issue Retailer
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotion_id`          (optional) - Coupon ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Coupon name
     * @param string   `promotion_name_like`   (optional) - Coupon name like
     * @param string   `promotion_type`        (optional) - Coupon type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-30 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `coupon_notification`   (optional) - Coupon notification. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `city`                  (optional) - City name
     * @param string   `city_like`             (optional) - City name like
     * @param integer  `issue_retailer_id`     (optional) - Issue retailer ID
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCouponByIssueRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_coupon')) {
                Event::fire('orbit.coupon.getsearchcouponbyissueretailer.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:issue_retailer_name,registered_date,promotion_name,promotion_type,description,begin_date,end_date,updated_at,is_permanent,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.coupon_by_issue_retailer_sortby'),
                )
            );

            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $prefix = DB::getTablePrefix();
            $nowUTC = Carbon::now();
            // Builder object
            $coupons = Coupon::join('merchants', 'promotions.merchant_id', '=', 'merchants.merchant_id')
                             ->join('timezones', 'merchants.timezone_id', '=', 'timezones.timezone_id')
                             ->leftJoin(DB::raw("(select ic.promotion_id, count(ic.promotion_id) as total_issued
                                               from {$prefix}issued_coupons ic
                                               where ic.status = 'active' or ic.status = 'redeemed'
                                               group by promotion_id) issued"),
                                        // On
                                        DB::raw('issued.promotion_id'), '=', 'promotions.promotion_id')
                             ->select('merchants.name AS issue_retailer_name', 'promotions.*', 'timezones.timezone_name', DB::raw('issued.total_issued'))
                             ->where('promotions.is_coupon', '=', 'Y')
                             ->where('promotions.promotion_type', 'mall')
                             // ->where('promotions.status', '!=', 'deleted');
                             ->where('promotions.status', '=', 'active')
                             ->where(function ($q) {
                                    $q->where('promotions.maximum_issued_coupon', '>', DB::raw('issued.total_issued'))
                                        ->orWhere('promotions.maximum_issued_coupon', '=', 0)
                                        ->orWhereNull(DB::raw('issued.total_issued'));
                             });

            if (empty(OrbitInput::get('begin_date')) && empty(OrbitInput::get('end_date'))) {
                $coupons->where('begin_date', '<=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"))
                        ->where('end_date', '>=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"));
            }

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter coupon by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($coupons) {
                $coupons->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_name', $promotionName);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', 'like', "%$promotionName%");
            });

            // Filter coupon by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter coupon by description
            OrbitInput::get('description', function($description) use ($coupons)
            {
                $coupons->whereIn('promotions.description', $description);
            });

            // Filter coupon by matching description pattern
            OrbitInput::get('description_like', function($description) use ($coupons)
            {
                $coupons->where('promotions.description', 'like', "%$description%");
            });

            // Filter coupon by begin date
            OrbitInput::get('begin_date', function($beginDate) use ($coupons)
            {
                $coupons->where('promotions.begin_date', '<=', $beginDate);
            });

            // Filter coupon by end date
            OrbitInput::get('end_date', function($endDate) use ($coupons)
            {
                $coupons->where('promotions.end_date', '>=', $endDate);
            });

            // Filter coupon by is permanent
            OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
                $coupons->whereIn('promotions.is_permanent', $isPermanent);
            });

            // Filter coupon by coupon notification
            OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
                $coupons->whereIn('promotions.coupon_notification', $couponNotification);
            });

            // Filter coupon by status
            OrbitInput::get('status', function ($statuses) use ($coupons) {
                $coupons->whereIn('promotions.status', $statuses);
            });

            // Filter coupon by city
            OrbitInput::get('city', function($city) use ($coupons)
            {
                $coupons->whereIn('merchants.city', $city);
            });

            // Filter coupon by matching city pattern
            OrbitInput::get('city_like', function($city) use ($coupons)
            {
                $coupons->where('merchants.city', 'like', "%$city%");
            });

            // Filter coupon by issue retailer Ids
            OrbitInput::get('issue_retailer_id', function ($issueRetailerIds) use ($coupons) {
                $coupons->whereIn('promotion_retailer.retailer_id', $issueRetailerIds);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($coupons) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'translations') {
                        $coupons->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $coupons->with('translations.media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;

            // Get the take args
            if (trim(OrbitInput::get('take')) === '') {
                $take = $maxRecord;
            } else {
                OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
                {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                });
            }
            if ($take > 0) {
                $coupons->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $coupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'issue_retailer_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'issue_retailer_name'    => 'issue_retailer_name',
                    'registered_date'        => 'promotions.created_at',
                    'promotion_name'         => 'promotions.promotion_name',
                    'promotion_type'         => 'promotions.promotion_type',
                    'description'            => 'promotions.description',
                    'begin_date'             => 'promotions.begin_date',
                    'end_date'               => 'promotions.end_date',
                    'updated_at'             => 'promotions.updated_at',
                    'is_permanent'           => 'promotions.is_permanent',
                    'status'                 => 'promotions.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $coupons->orderBy($sortBy, $sortMode);

            $totalCoupons = $_coupons->count();
            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.query.error', array($this, $e));

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
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Available Wallet Operator Based on link to tenant
     *
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `store_ids`    (required) - store_ids
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getAvailableWalletOperator()
    {

        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $couponId = OrbitInput::get('coupon_id', null);
            $prefix = DB::getTablePrefix();

            $redemptionPlace = OrbitInput::get('redemption_place', []);
            if (empty($redemptionPlace)) {
                $errorMessage = "Redemption Place is required";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $stores = Tenant::select('merchants.merchant_id as store_id', DB::raw("CONCAT({$prefix}merchants.name,' at ', oms.name) as store_name"))
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->whereIn('merchants.merchant_id', $redemptionPlace)
                            ->where('merchants.is_payment_acquire', 'Y')
                            ->groupBy('merchants.merchant_id')
                            ->get();

            if (! $stores->isEmpty()) {
                foreach ($stores as $store) {
                    $store->payment_providers = null;
                    $paymentProviders = MerchantStorePaymentProvider::select('payment_providers.payment_provider_id', 'payment_providers.payment_name', DB::raw("'N' as is_selected"))
                                                                   ->join('payment_providers', 'payment_providers.payment_provider_id', '=', 'merchant_store_payment_provider.payment_provider_id')
                                                                   ->where('merchant_store_payment_provider.object_type', 'store')
                                                                   ->where('payment_providers.status', 'active')
                                                                   ->where('merchant_store_payment_provider.object_id', $store->store_id)
                                                                   ->get();

                    if (! $paymentProviders->isEmpty()) {
                        if (! empty($couponId)) {
                            foreach ($paymentProviders as $provider) {
                                $couponPaymentProvider = CouponPaymentProvider::join('promotion_retailer_redeem', 'coupon_payment_provider.promotion_retailer_redeem_id', '=', 'promotion_retailer_redeem.promotion_retailer_redeem_id')
                                                                        ->where('promotion_retailer_redeem.promotion_id', $couponId)
                                                                        ->where('promotion_retailer_redeem.retailer_id', $store->store_id)
                                                                        ->where('coupon_payment_provider.payment_provider_id', $provider->payment_provider_id)
                                                                        ->groupBy('coupon_payment_provider.coupon_payment_provider_id')
                                                                        ->first();

                                if (! empty($couponPaymentProvider)) {
                                    $provider->is_selected = 'Y';
                                }

                            }
                        }

                        $store->payment_providers = $paymentProviders;
                    }
                }
            }

            $data = new stdclass();
            $data->total_records = count($stores);
            $data->returned_records = count($stores);
            $data->records = $stores;
            $this->response->data = $data;
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
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check maximal of total issued coupons
        Validator::extend('orbit.max.total_issued_coupons', function ($attribute, $value, $parameters) {
            $promotion_id = $parameters[0];

            $total_issued_coupons = IssuedCoupon::where('promotion_id', '=', $promotion_id)
                                                ->count();

            if (! empty($value) && $value < $total_issued_coupons) {
                return FALSE;
            }

            App::instance('orbit.max.total_issued_coupons', $total_issued_coupons);

            return TRUE;
        });

        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $news = Language::where('language_id', '=', $value)
                             ->first();

            if (empty($news)) {
                return FALSE;
            }

            App::instance('orbit.empty.language_default', $news);

            return TRUE;
        });

        // Mall deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall ID
            $currentMall = $parameters[0];

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = 'The master password is not set.';
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = 'The master password is incorrect.';
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

        // Check the existance of coupon id
        Validator::extend('orbit.empty.coupon', function ($attribute, $value, $parameters) {
            $coupon = Coupon::excludeStoppedOrExpired('promotions')
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.empty.coupon', $coupon);

            return TRUE;
        });

        // Check the existance of coupon id
        Validator::extend('orbit.exist.coupon', function ($attribute, $value, $parameters) {
            $coupon = Coupon::where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.exist.coupon', $coupon);

            return TRUE;
        });

        // Check the existance of coupon id for update with permission check
        Validator::extend('orbit.update.coupon', function ($attribute, $value, $parameters) {
            $user = $this->api->user;

            $coupon = Coupon::allowedForPMPUser($user, 'coupon')->excludeStoppedOrExpired('promotions')
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.update.coupon', $coupon);

            return TRUE;
        });

        // Check the existance of issued coupon id
        $user = $this->api->user;
        Validator::extend('orbit.empty.issuedcoupon', function ($attribute, $value, $parameters) use ($user) {
            $now = date('Y-m-d H:i:s');
            $number = OrbitInput::post('merchant_verification_number');
            $mall_id = OrbitInput::post('current_mall');
            $storeId = OrbitInput::post('store_id');

            $prefix = DB::getTablePrefix();

            $issuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed', 'available'])
                        ->where('issued_coupons.issued_coupon_id', $value)
                        ->where('issued_coupons.user_id', $user->user_id)
                        // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                        ->with('coupon')
                        ->whereHas('coupon', function($q) use($now) {
                            $q->where('promotions.status', 'active');
                            $q->where('promotions.coupon_validity_in_date', '>=', $now);
                        })
                        ->first();

            if (empty($issuedCoupon)) {
                $errorMessage = sprintf('Issued coupon ID %s is not found.', htmlentities($value));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            //Checking verification number in cs and tenant verification number
            //Checking in tenant verification number first
            if ($issuedCoupon->coupon->is_all_retailer === 'Y') {
                $checkIssuedCoupon = Tenant::where('merchant_id','=', $storeId)
                            ->where('status', 'active')
                            ->where('masterbox_number', $number)
                            ->first();
            } elseif ($issuedCoupon->coupon->is_all_retailer === 'N') {
                $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed', 'available'])
                            ->join('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where('issued_coupons.issued_coupon_id', $value)
                            ->where('issued_coupons.user_id', $user->user_id)
                            // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                            ->whereHas('coupon', function($q) use($now) {
                                $q->where('promotions.status', 'active');
                                $q->where('promotions.coupon_validity_in_date', '>=', $now);
                            })
                            ->where('merchants.merchant_id', $storeId)
                            ->where('merchants.masterbox_number', $number)
                            ->first();
            }

            // Continue checking to tenant verification number
            if (empty($checkIssuedCoupon)) {
                // Checking cs verification number
                if ($issuedCoupon->coupon->is_all_employee === 'Y') {
                    $checkIssuedCoupon = UserVerificationNumber::
                                join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('status', 'active')
                                ->where('merchant_id', $mall_id)
                                ->where('verification_number', $number)
                                ->first();
                } elseif ($issuedCoupon->coupon->is_all_employee === 'N') {
                    $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed', 'available'])
                                ->join('promotion_employee', 'promotion_employee.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->join('user_verification_numbers', 'user_verification_numbers.user_id', '=', 'promotion_employee.user_id')
                                ->join('employees', 'employees.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('employees.status', 'active')
                                ->where('issued_coupons.issued_coupon_id', $value)
                                ->where('issued_coupons.user_id', $user->user_id)
                                // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
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

        // Check the existence of the coupon status
        Validator::extend('orbit.empty.coupon_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the coupon rule type
        Validator::extend('orbit.empty.coupon_rule_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array(
                            'auto_issue_on_signup',
                            'auto_issue_on_first_signin',
                            'auto_issue_on_every_signin',
                            'blast_via_sms',
                            'unique_coupon_per_user'
                        );
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the link status
        Validator::extend('orbit.empty.status_link_to', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the coupon type
        Validator::extend('orbit.empty.coupon_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $couponTypes = array('mall', 'tenant');
            foreach ($couponTypes as $couponType) {
                if($value === $couponType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule type
        Validator::extend('orbit.empty.rule_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruletypes = array('cart_discount_by_value', 'cart_discount_by_percentage', 'new_product_price', 'product_discount_by_value', 'product_discount_by_percentage');
            foreach ($ruletypes as $ruletype) {
                if($value === $ruletype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule object type
        Validator::extend('orbit.empty.rule_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruleobjecttypes = array('product', 'family');
            foreach ($ruleobjecttypes as $ruleobjecttype) {
                if($value === $ruleobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of rule_object_id1
        Validator::extend('orbit.empty.rule_object_id1', function ($attribute, $value, $parameters) {
            $ruleobjecttype = trim(OrbitInput::post('rule_object_type'));
            if ($ruleobjecttype === 'product') {
                $rule_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($ruleobjecttype === 'family') {
                $rule_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($rule_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id1', $rule_object_id1);

            return TRUE;
        });

        // Check the existance of rule_object_id2
        Validator::extend('orbit.empty.rule_object_id2', function ($attribute, $value, $parameters) {
            $rule_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id2', $rule_object_id2);

            return TRUE;
        });

        // Check the existance of rule_object_id3
        Validator::extend('orbit.empty.rule_object_id3', function ($attribute, $value, $parameters) {
            $rule_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id3', $rule_object_id3);

            return TRUE;
        });

        // Check the existance of rule_object_id4
        Validator::extend('orbit.empty.rule_object_id4', function ($attribute, $value, $parameters) {
            $rule_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id4', $rule_object_id4);

            return TRUE;
        });

        // Check the existance of rule_object_id5
        Validator::extend('orbit.empty.rule_object_id5', function ($attribute, $value, $parameters) {
            $rule_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id5', $rule_object_id5);

            return TRUE;
        });

        // Check the existence of the discount object type
        Validator::extend('orbit.empty.discount_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $discountobjecttypes = array('product', 'family', 'cash_rebate');
            foreach ($discountobjecttypes as $discountobjecttype) {
                if($value === $discountobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of discount_object_id1
        Validator::extend('orbit.empty.discount_object_id1', function ($attribute, $value, $parameters) {
            $discountobjecttype = trim(OrbitInput::post('discount_object_type'));
            if ($discountobjecttype === 'product') {
                $discount_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($discountobjecttype === 'family') {
                $discount_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($discount_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id1', $discount_object_id1);

            return TRUE;
        });

        // Check the existance of discount_object_id2
        Validator::extend('orbit.empty.discount_object_id2', function ($attribute, $value, $parameters) {
            $discount_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id2', $discount_object_id2);

            return TRUE;
        });

        // Check the existance of discount_object_id3
        Validator::extend('orbit.empty.discount_object_id3', function ($attribute, $value, $parameters) {
            $discount_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id3', $discount_object_id3);

            return TRUE;
        });

        // Check the existance of discount_object_id4
        Validator::extend('orbit.empty.discount_object_id4', function ($attribute, $value, $parameters) {
            $discount_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id4', $discount_object_id4);

            return TRUE;
        });

        // Check the existance of discount_object_id5
        Validator::extend('orbit.empty.discount_object_id5', function ($attribute, $value, $parameters) {
            $discount_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id5', $discount_object_id5);

            return TRUE;
        });

        // Check the existance of tenant id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                                ->where('merchant_id', $value)
                                ->first();

            if (empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return TRUE;
        });

        // Check the existance of employee id
        Validator::extend('orbit.empty.employee', function ($attribute, $value, $parameters) {
            $employee = Employee::excludeDeleted()
                                ->where('user_id', $value)
                                ->first();

            if (empty($employee)) {
                return FALSE;
            }

            App::instance('orbit.empty.employee', $employee);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.issuedcoupon.exists', function ($attribute, $value, $parameters) {
            $coupon = IssuedCoupon::active()->where('promotion_id', $value)->count();

            if ($coupon > 0) {
                $message = sprintf('Can not delete coupon since there is still %s issued coupon which not redeemed yet.', $coupon);
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

        Validator::extend('orbit.empty.is_all_age', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.is_all_gender', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.gender', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('M', 'F', 'U');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.age', function ($attribute, $value, $parameters) {
            $exist = AgeRange::excludeDeleted()
                        ->where('age_range_id', $value)
                        ->first();

            if (empty($exist)) {
                return false;
            }

            App::instance('orbit.empty.age', $exist);

            return true;
        });

        // check the partner exclusive or not if the is_exclusive is set to 'Y'
        Validator::extend('orbit.empty.exclusive_partner', function ($attribute, $value, $parameters) {
            $flag_exclusive = false;
            $is_exclusive = OrbitInput::post('is_exclusive');
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;

            $partner_exclusive = Partner::select('is_exclusive', 'status')
                           ->whereIn('partner_id', $partner_ids)
                           ->get();

            foreach ($partner_exclusive as $exclusive) {
                if ($exclusive->is_exclusive == 'Y' && $exclusive->status == 'active') {
                    $flag_exclusive = true;
                }
            }

            $valid = true;

            if ($is_exclusive == 'Y') {
                if ($flag_exclusive) {
                    $valid = true;
                } else {
                    $valid = false;
                }
            }

            return $valid;
        });

        Validator::extend('orbit.check.issued_coupon', function ($attribute, $value, $parameters) {
            $valid = true;
            if (strtolower($value) === 'stopped') {
                $couponId = OrbitInput::post('promotion_id');
                $couponIssued = IssuedCoupon::where('promotion_id', '=', $couponId)->where('status', '=', 'issued')->first();
                $valid = ($couponIssued) ? false : true;
            }

            return $valid;
        });
    }

    /**
     * @param Coupon $coupon
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($coupon, $translations_json_string, $scenario = 'create', $isThirdParty = FALSE)
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where Coupon object is object with keys:
         *   promotion_name, description, long_description, short_description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['promotion_name', 'description', 'long_description', 'short_description', 'terms_and_conditions', 'how_to_buy_and_redeem'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);

        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        $pmpAccountDefaultLanguage = Language::where('name', '=', $this->pmpAccountDefaultLanguage)
                ->first();


        foreach ($data as $merchant_language_id => $translations) {
            $language = Language::where('language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = CouponTranslation::excludeDeleted()
                ->where('promotion_id', '=', $coupon->promotion_id)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();

            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $new_translation = new CouponTranslation();
                $new_translation->promotion_id = $coupon->promotion_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an event which listen on orbit.coupon.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.coupon.after.translation.save', array($this, $new_translation));

                if ($isThirdParty) {
                    // validate header & image 1 if the coupon translation language = pmp account default language
                    // if ($new_translation->merchant_language_id === $pmpAccountDefaultLanguage->language_id) {
                    //     $header_files = OrbitInput::files('header_image_translation_' . $new_translation->merchant_language_id);
                    //     if (! $header_files && $isThirdParty) {
                    //         $errorMessage = 'Header image is required for ' . $pmpAccountDefaultLanguage->name_long;
                    //         OrbitShopAPI::throwInvalidArgument($errorMessage);
                    //     }
                    //     $image1_files = OrbitInput::files('image1_translation_' . $new_translation->merchant_language_id);
                    //     if (! $image1_files && $isThirdParty) {
                    //         $errorMessage = 'Image 1 is required for ' . $pmpAccountDefaultLanguage->name_long;
                    //         OrbitShopAPI::throwInvalidArgument($errorMessage);
                    //     }
                    // }
                    // Event::fire('orbit.coupon.after.header.translation.save', array($this, $new_translation));
                    // Event::fire('orbit.coupon.after.image1.translation.save', array($this, $new_translation));
                }

                $coupon->setRelation('translation_' . $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var CouponTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $coupon->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an event which listen on orbit.coupon.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.coupon.after.translation.save', array($this, $existing_translation));

                // if ($isThirdParty) {
                //     // validate header & image 1 if the coupon translation language = pmp account default language
                //     if ($existing_translation->merchant_language_id === $pmpAccountDefaultLanguage->language_id) {

                //         //check media header and image1
                //         $header = Media::where('object_id', $existing_translation->coupon_translation_id)
                //                         ->where('media_name_id', 'coupon_header_grab_translation')->first();
                //         $image1 = Media::where('object_id', $existing_translation->coupon_translation_id)
                //                         ->where('media_name_id', 'coupon_image1_grab_translation')->first();

                //         $header_files = OrbitInput::files('header_image_translation_' . $existing_translation->merchant_language_id);
                //         if (! $header_files && $isThirdParty && empty($header)) {
                //             $errorMessage = 'Header image is required for ' . $pmpAccountDefaultLanguage->name_long;
                //             OrbitShopAPI::throwInvalidArgument($errorMessage);
                //         }
                //         $image1_files = OrbitInput::files('image1_translation_' . $existing_translation->merchant_language_id);
                //         if (! $image1_files && $isThirdParty && empty($image1)) {
                //             $errorMessage = 'Image 1 is required for ' . $pmpAccountDefaultLanguage->name_long;
                //             OrbitShopAPI::throwInvalidArgument($errorMessage);
                //         }
                //     }
                //     Event::fire('orbit.coupon.after.header.translation.save', array($this, $existing_translation));
                //     Event::fire('orbit.coupon.after.image1.translation.save', array($this, $existing_translation));
                // }

                // return respones if any upload image or no
                $existing_translation->load('media');

                $coupon->setRelation('translation_' . $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var CouponTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    private function mapDiscounts($discounts)
    {
        $newDiscounts = [];

        foreach($discounts as $discount) {
            $newDiscounts[$discount] = ['object_type' => 'coupon'];
        }

        return $newDiscounts;
    }

    private function getCampaignStatus($coupon_id)
    {
        $status = 'not started';
        $table_prefix = DB::getTablePrefix();
        $getCampaignStatus = Coupon::select(DB::raw("CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired'
                                                        THEN {$table_prefix}campaign_status.campaign_status_name
                                                    ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                FROM {$table_prefix}merchants om
                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                                                            THEN 'expired'
                                                            ELSE {$table_prefix}campaign_status.campaign_status_name
                                                          END)
                                                    END AS campaign_status"))
                            ->where('promotion_id', $coupon_id)
                            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                            ->first();

        if ($getCampaignStatus) {
            $status = $getCampaignStatus->campaign_status;
        }

        return $status;
    }
}
