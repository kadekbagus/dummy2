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

class CouponAPIController extends ControllerAPI
{
     /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $couponViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $couponModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign admin', 'campaign employee'];
    protected $couponModifiyRolesWithConsumer = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign admin', 'consumer'];

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

/*
            if (! ACL::create($user)->isAllowed('create_coupon')) {
                Event::fire('orbit.coupon.postnewcoupon.authz.notallowed', array($this, $user));
                $createCouponLang = Lang::get('validation.orbit.actionlist.new_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createCouponLang));
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

            Event::fire('orbit.coupon.postnewcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type');
            $campaignStatus = OrbitInput::post('campaign_status');
            $description = OrbitInput::post('description');
            $long_description = OrbitInput::post('long_description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $is_all_retailer = OrbitInput::post('is_all_retailer');
            $is_all_employee = OrbitInput::post('is_all_employee');
            $maximum_issued_coupon_type = OrbitInput::post('maximum_issued_coupon_type');
            $maximum_issued_coupon = OrbitInput::post('maximum_issued_coupon');
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
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age');
            $is_popup = OrbitInput::post('is_popup');
            $rule_begin_date = OrbitInput::post('rule_begin_date');
            $rule_end_date = OrbitInput::post('rule_end_date');
            $gender_ids = OrbitInput::post('gender_ids');
            $gender_ids = (array) $gender_ids;
            $age_range_ids = OrbitInput::post('age_range_ids');
            $age_range_ids = (array) $age_range_ids;
            $keywords = OrbitInput::post('keywords');
            $keywords = (array) $keywords;
            $linkToTenantIds = OrbitInput::post('link_to_tenant_ids');
            $linkToTenantIds = (array) $linkToTenantIds;

            if (empty($campaignStatus)) {
                $campaignStatus = 'not started';
            }

            $status = 'inactive';
            if ($campaignStatus === 'ongoing') {
                $status = 'active';
            }

            $validator = Validator::make(
                array(
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
                    'rule_begin_date'         => $rule_begin_date,
                    'rule_end_date'           => $rule_end_date,
                    'is_all_gender'           => $is_all_gender,
                    'is_all_age'              => $is_all_age,
                    'is_popup'            => $is_popup,
                ),
                array(
                    'promotion_name'          => 'required|max:255|orbit.exists.coupon_name',
                    'promotion_type'          => 'required|orbit.empty.coupon_type',
                    'begin_date'              => 'required|date_format:Y-m-d H:i:s',
                    'end_date'                => 'required|date_format:Y-m-d H:i:s',
                    'rule_type'               => 'required|orbit.empty.coupon_rule_type',
                    'status'                  => 'required|orbit.empty.coupon_status',
                    'coupon_validity_in_date' => 'date_format:Y-m-d H:i:s',
                    'rule_value'              => 'required|numeric|min:0',
                    'discount_value'          => 'required|numeric|min:0',
                    'is_all_retailer'         => 'orbit.empty.status_link_to',
                    'is_all_employee'         => 'orbit.empty.status_link_to',
                    'id_language_default'     => 'required|orbit.empty.language_default',
                    'rule_begin_date'         => 'date_format:Y-m-d H:i:s',
                    'rule_end_date'           => 'date_format:Y-m-d H:i:s',
                    'is_all_gender'           => 'required|orbit.empty.is_all_gender',
                    'is_all_age'              => 'required|orbit.empty.is_all_age',
                    'is_popup'            => 'required|in:Y,N',
                ),
                array(
                    'rule_value.required'     => 'The amount to obtain is required',
                    'rule_value.numeric'      => 'The amount to obtain must be a number',
                    'rule_value.min'          => 'The amount to obtain must be greater than zero',
                    'discount_value.required' => 'The coupon value is required',
                    'discount_value.numeric'  => 'The coupon value must be a number',
                    'discount_value.min'      => 'The coupon value must be greater than zero',
                    'is_popup.in' => 'is popup must Y or N',
                )
            );

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

            // validating retailer_ids.
            foreach ($retailer_ids as $retailer_json) {
                $data = @json_decode($retailer_json);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                $validator = Validator::make(
                    array(
                        'retailer_id'   => $tenant_id,

                    ),
                    array(
                        'retailer_id'   => 'orbit.empty.tenant',
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

            foreach ($gender_ids as $gender_id_check) {
                $validator = Validator::make(
                    array(
                        'gender_id'   => $gender_id_check,
                    ),
                    array(
                        'gender_id'   => 'orbit.empty.gender',
                    )
                );

                Event::fire('orbit.coupon.postnewcoupon.before.gendervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.coupon.postnewcoupon.after.retailervalidation', array($this, $validator));
            }

            foreach ($age_range_ids as $age_range_id_check) {
                $validator = Validator::make(
                    array(
                        'age_range_id'   => $age_range_id_check,
                    ),
                    array(
                        'age_range_id'   => 'orbit.empty.age',
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


            Event::fire('orbit.coupon.postnewcoupon.after.validation', array($this, $validator));

            // save Coupon.
            $idStatus = CampaignStatus::select('campaign_status_id','campaign_status_name')->where('campaign_status_name', $campaignStatus)->first();

            $newcoupon = new Coupon();
            $newcoupon->merchant_id = $merchant_id;
            $newcoupon->promotion_name = $promotion_name;
            $newcoupon->promotion_type = $promotion_type;
            $newcoupon->status = $status;
            $newcoupon->campaign_status_id = $idStatus->campaign_status_id;
            $newcoupon->description = $description;
            $newcoupon->long_description = $long_description;
            $newcoupon->begin_date = $begin_date;
            $newcoupon->end_date = $end_date;
            $newcoupon->is_permanent = $is_permanent;
            $newcoupon->is_all_retailer = $is_all_retailer;
            $newcoupon->is_all_employee = $is_all_employee;
            $newcoupon->maximum_issued_coupon_type = $maximum_issued_coupon_type;
            $newcoupon->maximum_issued_coupon = $maximum_issued_coupon;
            $newcoupon->coupon_validity_in_days = $coupon_validity_in_days;
            $newcoupon->coupon_validity_in_date = $coupon_validity_in_date;
            $newcoupon->coupon_notification = $coupon_notification;
            $newcoupon->created_by = $this->api->user->user_id;
            $newcoupon->is_all_age = $is_all_age;
            $newcoupon->is_all_gender = $is_all_gender;
            $newcoupon->is_popup = $is_popup;

            Event::fire('orbit.coupon.postnewcoupon.before.save', array($this, $newcoupon));

            $newcoupon->save();

            // Return campaign_status_name
            $newcoupon->campaign_status = $idStatus->campaign_status_name;

            // save default language translation
            $coupon_translation_default = new CouponTranslation();
            $coupon_translation_default->promotion_id = $newcoupon->promotion_id;
            $coupon_translation_default->merchant_language_id = $id_language_default;
            $coupon_translation_default->promotion_name = $newcoupon->promotion_name;
            $coupon_translation_default->description = $newcoupon->description;
            $coupon_translation_default->long_description = $newcoupon->long_description;
            $coupon_translation_default->status = 'active';
            $coupon_translation_default->created_by = $this->api->user->user_id;
            $coupon_translation_default->modified_by = $this->api->user->user_id;
            $coupon_translation_default->save();

            Event::fire('orbit.coupon.after.translation.save', array($this, $coupon_translation_default));

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
            $couponrule->rule_begin_date = $rule_begin_date;
            $couponrule->rule_end_date = $rule_end_date;
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

            // save CampaignAge
            $couponAges = array();
            foreach ($age_range_ids as $age_range) {
                $couponAge = new CampaignAge();
                $couponAge->campaign_type = 'coupon';
                $couponAge->campaign_id = $newcoupon->promotion_id;
                $couponAge->age_range_id = $age_range;
                $couponAge->save();
                $couponAges[] = $couponAge;
            }
            $newcoupon->age = $couponAges;

            // save CampaignGender
            $couponGenders = array();
            foreach ($gender_ids as $gender) {
                $couponGender = new CampaignGender();
                $couponGender->campaign_type = 'coupon';
                $couponGender->campaign_id = $newcoupon->promotion_id;
                $couponGender->gender_value = $gender;
                $couponGender->save();
                $gender_name = null;
                $couponGenders[] = $couponGender;
            }
            $newcoupon->gender = $couponGenders;

            // save Keyword
            $couponKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                    ->where('keyword', '=', $keyword)
                    ->where('merchant_id', '=', $newcoupon->merchant_id)
                    ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = $newcoupon->merchant_id;
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

            Event::fire('orbit.coupon.postnewcoupon.after.save', array($this, $newcoupon));

            //save campaign price
            $campaignbaseprice = CampaignBasePrice::where('merchant_id', '=', $newcoupon->merchant_id)
                                            ->where('campaign_type', '=', 'coupon')
                                            ->first();

            $baseprice = 0;
            if (! empty($campaignbaseprice->price)) {
                $baseprice = $campaignbaseprice->price;
            }

            $campaignprice = new CampaignPrice();
            $campaignprice->base_price = $baseprice;
            $campaignprice->campaign_type = 'coupon';
            $campaignprice->campaign_id = $newcoupon->promotion_id;
            $campaignprice->save();

            // get action id for campaign history
            $actionstatus = 'activate';
            if ($status === 'inactive') {
                $actionstatus = 'deactivate';
            }
            $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);
            $addtenantid = CampaignHistoryAction::getIdFromAction('add_tenant');

            // save campaign history status
            $campaignhistory = new CampaignHistory();
            $campaignhistory->campaign_type = 'coupon';
            $campaignhistory->campaign_id = $newcoupon->promotion_id;
            $campaignhistory->campaign_history_action_id = $activeid;
            $campaignhistory->number_active_tenants = 0;
            $campaignhistory->campaign_cost = 0;
            $campaignhistory->created_by = $this->api->user->user_id;
            $campaignhistory->modified_by = $this->api->user->user_id;
            $campaignhistory->save();

            // save campaign history tenant
            $withSpending = 'Y';
            foreach ($linkToTenantIds as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                // insert tenant/merchant to campaign history
                $tenantstatus = CampaignLocation::select('status')->where('merchant_id', $tenant_id)->first();
                $spendingrule = SpendingRule::select('with_spending')->where('object_id', $tenant_id)->first();

                if ($spendingrule) {
                    $withSpending = $spendingrule->with_spending;
                } else {
                    $withSpending = 'N';
                }

                if (($tenantstatus->status === 'active') && ($withSpending === 'Y')) {
                    $addtenant = new CampaignHistory();
                    $addtenant->campaign_type = 'coupon';
                    $addtenant->campaign_id = $newcoupon->promotion_id;
                    $addtenant->campaign_external_value = $tenant_id;
                    $addtenant->campaign_history_action_id = $addtenantid;
                    $addtenant->number_active_tenants = 0;
                    $addtenant->created_by = $this->api->user->user_id;
                    $addtenant->modified_by = $this->api->user->user_id;
                    $addtenant->campaign_cost = 0;
                    $addtenant->save();
                }
            }

            //calculate spending
            foreach ($mallid as $mall) {

                $campaign_id = $newcoupon->promotion_id;
                $campaign_type = 'coupon';
                $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaign_id)}, {$this->quote($campaign_type)}, NULL, NULL, {$this->quote($mall)})");

                if ($procResults === false) {
                    // Do Nothing
                }

                $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                $mallTimezone = $this->getTimezone($mall);
                $nowMall = Carbon::now($mallTimezone);
                $dateNowMall = $nowMall->toDateString();

                // if campaign begin date is same with date now
                if ($dateNowMall === date('Y-m-d', strtotime($begin_date))) {
                    $dailySpending = new CampaignDailySpending();
                    $dailySpending->date = $getspending->date_in_utc;
                    $dailySpending->campaign_type = $campaign_type;
                    $dailySpending->campaign_id = $campaign_id;
                    $dailySpending->mall_id = $mall;
                    $dailySpending->number_active_tenants = 0;
                    $dailySpending->base_price = $getspending->base_price;
                    $dailySpending->campaign_status = $getspending->campaign_status;
                    $dailySpending->total_spending = 0;
                    $dailySpending->save();
                }
            }

            OrbitInput::post('translations', function($translation_json_string) use ($newcoupon, $mallid) {
                $this->validateAndSaveTranslations($newcoupon, $translation_json_string, 'create');
            });

            foreach ($mallid as $mall) {
                // get default mall language id
                $default = Mall::select('mobile_default_language', 'name')
                                ->where('merchant_id', '=', $mall)
                                ->first();

                $idLanguage = Language::select('language_id', 'name_long')
                                    ->where('name', '=', $default->mobile_default_language)
                                    ->first();

                $isAvailable = CouponTranslation::where('merchant_language_id', '=', $idLanguage->language_id)
                                                ->where('promotion_id', '=', $newcoupon->promotion_id)
                                                ->where('promotion_name', '!=', '')
                                                ->where('description', '!=', '')
                                                ->count();

                if ($isAvailable == 0) {
                    $errorMessage = Lang::get('validation.orbit.empty.default_language');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            $this->response->data = $newcoupon;
            $this->response->data->translation_default = $coupon_translation_default;

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
        } catch (Exception $e) {
            Event::fire('orbit.coupon.postnewcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
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
     * @param string     `is_all_gender`         (optional) - Is all gender. Valid value: Y, N.
     * @param string     `is_all_age`            (optional) - Is all retailer age group. Valid value: Y, N.
     * @param string     `gender_ids`            (optional) - for Male, Female. Unknown. Valid value: M, F, U.
     * @param string     `age_range_ids`         (optional) - Age Range IDs
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

/*
            if (! ACL::create($user)->isAllowed('update_coupon')) {
                Event::fire('orbit.coupon.postupdatecoupon.authz.notallowed', array($this, $user));
                $updateCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateCouponLang));
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

            Event::fire('orbit.coupon.postupdatecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();


            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_id = OrbitInput::post('current_mall');
            $promotion_type = OrbitInput::post('promotion_type');
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
            $maximum_issued_coupon = OrbitInput::post('maximum_issued_coupon');
            $coupon_validity_in_days = OrbitInput::post('coupon_validity_in_days');
            $coupon_validity_in_date = OrbitInput::post('coupon_validity_in_date');
            $discount_value = OrbitInput::post('discount_value');
            $rule_value = OrbitInput::post('rule_value');
            $id_language_default = OrbitInput::post('id_language_default');
            $rule_begin_date = OrbitInput::post('rule_begin_date');
            $rule_end_date = OrbitInput::post('rule_end_date');
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age');

            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $linkToTenantIds = OrbitInput::post('link_to_tenant_ids');
            $linkToTenantIds = (array) $linkToTenantIds;

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
                'maximum_issued_coupon'   => $maximum_issued_coupon,
                'rule_begin_date'         => $rule_begin_date,
                'rule_end_date'           => $rule_end_date,
                'is_all_gender'           => $is_all_gender,
                'is_all_age'              => $is_all_age,
            );

            // Validate promotion_name only if exists in POST.
            OrbitInput::post('promotion_name', function($promotion_name) use (&$data) {
                $data['promotion_name'] = $promotion_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'promotion_id'            => 'required|orbit.update.coupon',
                    'promotion_name'          => 'sometimes|required|min:5|max:255|coupon_name_exists_but_me',
                    'promotion_type'          => 'orbit.empty.coupon_type',
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
                    'maximum_issued_coupon'   => 'orbit.max.total_issued_coupons:' . $promotion_id,
                    'rule_begin_date'         => 'date_format:Y-m-d H:i:s',
                    'rule_end_date'           => 'date_format:Y-m-d H:i:s',
                    'is_all_gender'           => 'required|orbit.empty.is_all_gender',
                    'is_all_age'              => 'required|orbit.empty.is_all_age',
                ),
                array(
                    'coupon_name_exists_but_me' => Lang::get('validation.orbit.exists.coupon_name'),
                    'rule_value.required'       => 'The amount to obtain is required',
                    'rule_value.numeric'        => 'The amount to obtain must be a number',
                    'rule_value.min'            => 'The amount to obtain must be greater than zero',
                    'discount_value.required'   => 'The coupon value is required',
                    'discount_value.numeric'    => 'The coupon value must be a number',
                    'discount_value.min'        => 'The coupon value must be greater than zero',
                    'orbit.update.coupon'       => 'Cannot update campaign with status ' . $campaignStatus,
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

            $statusdb = $updatedcoupon->status;
            $enddatedb = $updatedcoupon->end_date;

            // get merchant for db
            $promoretailer = PromotionRetailer::select('retailer_id')->where('promotion_id', $promotion_id)->get()->toArray();
            $retailerdb = array();
            foreach($promoretailer as $promoretailerid) {
                $retailerdb[] = $promoretailerid['retailer_id'];
            }

            //save campaign histories (status)
            $actionstatus = '';
            if ($statusdb != $status) {
                // get action id for campaign history
                $actionstatus = 'activate';
                if ($status === 'inactive') {
                    $actionstatus = 'deactivate';
                }
                $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);
                $campaignhistory = new CampaignHistory();
                $campaignhistory->campaign_type = 'coupon';
                $campaignhistory->campaign_id = $promotion_id;
                $campaignhistory->campaign_history_action_id = $activeid;
                $campaignhistory->number_active_tenants = 0;
                $campaignhistory->campaign_cost = 0;
                $campaignhistory->created_by = $this->api->user->user_id;
                $campaignhistory->modified_by = $this->api->user->user_id;
                $campaignhistory->save();

            } else {
                $utcNow = Carbon::now();
                $checkFirst = CampaignHistory::where('campaign_id', '=', $promotion_id)->where('created_at', 'like', $utcNow->toDateString().'%')->count();
                if ($checkFirst === 0){
                    $actionstatus = 'activate';
                    if ($statusdb === 'inactive') {
                        $actionstatus = 'deactivate';
                    }
                    $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);
                    $campaignhistory = new CampaignHistory();
                    $campaignhistory->campaign_type = 'coupon';
                    $campaignhistory->campaign_id = $promotion_id;
                    $campaignhistory->campaign_history_action_id = $activeid;
                    $campaignhistory->number_active_tenants = 0;
                    $campaignhistory->campaign_cost = 0;
                    $campaignhistory->created_by = $this->api->user->user_id;
                    $campaignhistory->modified_by = $this->api->user->user_id;
                    $campaignhistory->save();
                }
            }

            //check for add/remove tenant
            $removetenant = array_diff($retailerdb, $linktotenantnew);
            $addtenant = array_diff($linktotenantnew, $retailerdb);
            $withSpending = 'Y';

            if (! empty($removetenant)) {
                $actionhistory = 'delete';
                $addtenantid = CampaignHistoryAction::getIdFromAction('delete_tenant');
                //save histories
                foreach ($removetenant as $retailer_id) {
                    // insert tenant/merchant to campaign history
                    $tenantstatus = CampaignLocation::select('status')->where('merchant_id', $retailer_id)->first();
                    $spendingrule = SpendingRule::select('with_spending')->where('object_id', $retailer_id)->first();

                    if ($spendingrule) {
                        $withSpending = $spendingrule->with_spending;
                    } else {
                        $withSpending = 'N';
                    }

                    if (($tenantstatus->status === 'active') && ($withSpending === 'Y')) {
                        $tenanthistory = new CampaignHistory();
                        $tenanthistory->campaign_type = 'coupon';
                        $tenanthistory->campaign_id = $promotion_id;
                        $tenanthistory->campaign_external_value = $retailer_id;
                        $tenanthistory->campaign_history_action_id = $addtenantid;
                        $tenanthistory->number_active_tenants = 0;
                        $tenanthistory->campaign_cost = 0;
                        $tenanthistory->created_by = $this->api->user->user_id;
                        $tenanthistory->modified_by = $this->api->user->user_id;
                        $tenanthistory->save();
                    }
                }
            }
            if (! empty($addtenant)) {
                $actionhistory = 'add';
                $addtenantid = CampaignHistoryAction::getIdFromAction('add_tenant');

                //save histories
                foreach ($addtenant as $retailer_id) {
                    // insert tenant/merchant to campaign history
                    $tenantstatus = CampaignLocation::select('status')->where('merchant_id', $retailer_id)->first();
                    $spendingrule = SpendingRule::select('with_spending')->where('object_id', $retailer_id)->first();

                    if ($spendingrule) {
                        $withSpending = 'Y';
                    } else {
                        $withSpending = 'N';
                    }

                    if (($tenantstatus->status === 'active') && ($withSpending === 'Y')) {
                        $tenanthistory = new CampaignHistory();
                        $tenanthistory->campaign_type = 'coupon';
                        $tenanthistory->campaign_id = $promotion_id;
                        $tenanthistory->campaign_external_value = $retailer_id;
                        $tenanthistory->campaign_history_action_id = $addtenantid;
                        $tenanthistory->number_active_tenants = 0;
                        $tenanthistory->campaign_cost = 0;
                        $tenanthistory->created_by = $this->api->user->user_id;
                        $tenanthistory->modified_by = $this->api->user->user_id;
                        $tenanthistory->save();
                    }
                }
            }

            $updatedcoupon_default_language = CouponTranslation::excludeDeleted()->where('promotion_id', $promotion_id)->where('merchant_language_id', $id_language_default)->first();

            // save Coupon
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedcoupon) {
                $updatedcoupon->merchant_id = $merchant_id;
            });

            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedcoupon) {
                $updatedcoupon->promotion_name = $promotion_name;
            });

            OrbitInput::post('promotion_type', function($promotion_type) use ($updatedcoupon) {
                $updatedcoupon->promotion_type = $promotion_type;
            });

            OrbitInput::post('campaign_status', function($campaignStatus) use ($updatedcoupon, $idStatus, $status) {
                $updatedcoupon->status = $status;
                $updatedcoupon->campaign_status_id = $idStatus->campaign_status_id;
            });

            OrbitInput::post('description', function($description) use ($updatedcoupon) {
                $updatedcoupon->description = $description;
            });

            OrbitInput::post('long_description', function($long_description) use ($updatedcoupon) {
                $updatedcoupon->long_description = $long_description;
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

            OrbitInput::post('maximum_issued_coupon_type', function($maximum_issued_coupon_type) use ($updatedcoupon) {
                $updatedcoupon->maximum_issued_coupon_type = $maximum_issued_coupon_type;
            });

            OrbitInput::post('maximum_issued_coupon', function($maximum_issued_coupon) use ($updatedcoupon) {
                $updatedcoupon->maximum_issued_coupon = $maximum_issued_coupon;
            });

            OrbitInput::post('coupon_validity_in_days', function($coupon_validity_in_days) use ($updatedcoupon) {
                $updatedcoupon->coupon_validity_in_days = $coupon_validity_in_days;
            });

            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatedcoupon) {
                $updatedcoupon->is_all_gender = $is_all_gender;
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatedcoupon) {
                $updatedcoupon->is_all_age = $is_all_age;
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

            $updatedcoupon->modified_by = $this->api->user->user_id;

            //  save coupon default language
            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedcoupon_default_language) {
                $updatedcoupon_default_language->promotion_name = $promotion_name;
            });

            OrbitInput::post('description', function($description) use ($updatedcoupon_default_language) {
                $updatedcoupon_default_language->description = $description;
            });

            OrbitInput::post('long_description', function($long_description) use ($updatedcoupon_default_language) {
                $updatedcoupon_default_language->long_description = $long_description;
            });

            OrbitInput::post('campaign_status', function($campaignStatus) use ($updatedcoupon_default_language, $status) {
                $updatedcoupon_default_language->status = $status;
            });

            $updatedcoupon_default_language->modified_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postupdatecoupon.before.save', array($this, $updatedcoupon));

            $updatedcoupon->save();
            $updatedcoupon_default_language->save();

            Event::fire('orbit.coupon.after.translation.save', array($this, $updatedcoupon_default_language));

            // return respones if any upload image or no
            $updatedcoupon_default_language->load('media');


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

            OrbitInput::post('rule_begin_date', function($rule_begin_date) use ($couponrule) {
                $couponrule->rule_begin_date = $rule_begin_date;
            });

            OrbitInput::post('rule_end_date', function($rule_end_date) use ($couponrule) {
                $couponrule->rule_end_date = $rule_end_date;
            });

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


            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatedcoupon, $promotion_id) {
                $updatedcoupon->is_all_gender = $is_all_gender;
                if ($is_all_gender == 'Y') {
                    $deleted_campaign_genders = CampaignGender::where('campaign_id', '=', $promotion_id)
                                                            ->where('campaign_type', '=', 'coupon');
                    $deleted_campaign_genders->delete();
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($promotion_id) {
                // validating retailer_ids.
                foreach ($retailer_ids as $retailer_id_json) {
                    $data = @json_decode($retailer_id_json);
                    $tenant_id = $data->tenant_id;
                    $mall_id = $data->mall_id;

                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $tenant_id,

                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.tenant',
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
                }
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatedcoupon, $promotion_id) {
                $updatedcoupon->is_all_age = $is_all_age;
                if ($is_all_age == 'Y') {
                    $deleted_campaign_ages = CampaignAge::where('campaign_id', '=', $promotion_id)
                                                            ->where('campaign_type', '=', 'coupon');
                    $deleted_campaign_ages->delete();
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

            OrbitInput::post('gender_ids', function($gender_ids) use ($updatedcoupon, $promotion_id) {
                // validate gender_ids
                $gender_ids = (array) $gender_ids;
                foreach ($gender_ids as $gender_id_check) {
                    $validator = Validator::make(
                        array(
                            'gender_id'   => $gender_id_check,
                        ),
                        array(
                            'gender_id'   => 'orbit.empty.gender',
                        )
                    );

                    Event::fire('orbit.coupon.postupdatecoupon.before.gendervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.coupon.postupdatecoupon.after.gendervalidation', array($this, $validator));
                }

                // Delete old data
                $deleted_campaign_genders = CampaignGender::where('campaign_id', '=', $promotion_id)
                                                        ->where('campaign_type', '=', 'coupon');
                $deleted_campaign_genders->delete();

                // Insert new data
                $couponGenders = array();
                foreach ($gender_ids as $gender) {
                    $couponGender = new CampaignGender();
                    $couponGender->campaign_type = 'coupon';
                    $couponGender->campaign_id = $promotion_id;
                    $couponGender->gender_value = $gender;
                    $couponGender->save();
                    $couponGenders[] = $couponGenders;
                }
                $updatedcoupon->gender = $couponGenders;

            });

            OrbitInput::post('age_range_ids', function($age_range_ids) use ($updatedcoupon, $promotion_id) {
                // validate age_range_ids
                $age_range_ids = (array) $age_range_ids;
                foreach ($age_range_ids as $age_range_id_check) {
                    $validator = Validator::make(
                        array(
                            'age_range_id'   => $age_range_id_check,
                        ),
                        array(
                            'age_range_id'   => 'orbit.empty.age',
                        )
                    );

                    Event::fire('orbit.coupon.postupdatecoupon.before.agevalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.coupon.postupdatecoupon.after.agevalidation', array($this, $validator));
                }

                // Delete old data
                $deleted_campaign_ages = CampaignAge::where('campaign_id', '=', $promotion_id)
                                                        ->where('campaign_type', '=', 'coupon');
                $deleted_campaign_ages->delete();

                // Insert new data
                $couponAges = array();
                foreach ($age_range_ids as $age_range) {
                    $couponAge = new CampaignAge();
                    $couponAge->campaign_type = 'coupon';
                    $couponAge->campaign_id = $promotion_id;
                    $couponAge->age_range_id = $age_range;
                    $couponAge->save();
                    $couponAges[] = $couponAges;
                }
                $updatedcoupon->age = $couponAges;

            });

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $promotion_id)
                                                    ->where('object_type', '=', 'coupon');
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatedcoupon, $merchant_id, $user, $promotion_id) {
                // Insert new data
                $couponKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', $merchant_id)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = $merchant_id;
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

            //calculate spending
            foreach ($mallid as $mall) {
                $campaign_id = $promotion_id;
                $campaign_type = 'coupon';
                $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaign_id)}, {$this->quote($campaign_type)}, NULL, NULL, {$this->quote($mall)})");

                if ($procResults === false) {
                    // Do Nothing
                }

                $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                $mallTimezone = $this->getTimezone($mall);
                $nowMall = Carbon::now($mallTimezone);
                $dateNowMall = $nowMall->toDateString();
                $beginMall = date('Y-m-d', strtotime($begin_date));
                $endMall = date('Y-m-d', strtotime($end_date));

                // only calculate spending when update date between start and date of campaign
                if ($dateNowMall >= $beginMall && $dateNowMall <= $endMall) {
                    $daily = CampaignDailySpending::where('date', '=', $getspending->date_in_utc)->where('campaign_id', '=', $campaign_id)->where('mall_id', '=', $mall)->first();

                    if ($daily['campaign_daily_spending_id']) {
                        $dailySpending = CampaignDailySpending::find($daily['campaign_daily_spending_id']);
                    } else {
                        $dailySpending = new CampaignDailySpending;
                    }

                    $dailySpending->date = $getspending->date_in_utc;
                    $dailySpending->campaign_type = $campaign_type;
                    $dailySpending->campaign_id = $campaign_id;
                    $dailySpending->mall_id = $mall;
                    $dailySpending->number_active_tenants = $getspending->campaign_number_tenant;
                    $dailySpending->base_price = $getspending->base_price;
                    $dailySpending->campaign_status = $getspending->campaign_status;
                    $dailySpending->total_spending = $getspending->daily_cost;
                    $dailySpending->save();
                }
            }

            Event::fire('orbit.coupon.postupdatecoupon.after.save', array($this, $updatedcoupon));

            OrbitInput::post('translations', function($translation_json_string) use ($updatedcoupon, $mallid) {
                $this->validateAndSaveTranslations($updatedcoupon, $translation_json_string, 'create');
            });

            foreach ($mallid as $mall) {
                // get default mall language id
                $default = Mall::select('mobile_default_language', 'name')
                                ->where('merchant_id', '=', $mall)
                                ->first();

                $idLanguage = Language::select('language_id', 'name_long')
                                    ->where('name', '=', $default->mobile_default_language)
                                    ->first();

                $isAvailable = CouponTranslation::where('merchant_language_id', '=', $idLanguage->language_id)
                                                ->where('promotion_id', '=', $promotion_id)
                                                ->where('promotion_name', '!=', '')
                                                ->where('description', '!=', '')
                                                ->count();

                if ($isAvailable == 0) {
                    $errorMessage = Lang::get('validation.orbit.empty.default_language');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            $this->response->data = $updatedcoupon;
            $this->response->data->translation_default = $updatedcoupon_default_language;

            // Commit the changes
            $this->commit();

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
                $this->response->message = $e->getMessage();
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
        } catch (Exception $e) {
            Event::fire('orbit.coupon.postupdatecoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

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

/*
            if (! ACL::create($user)->isAllowed('view_coupon')) {
                Event::fire('orbit.coupon.getsearchcoupon.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
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
            $currentmall = OrbitInput::get('current_mall');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'current_mall' => $currentmall
                ),
                array(
                    'sort_by' => 'in:registered_date,promotion_name,promotion_type,description,begin_date,end_date,status,is_permanent,rule_type,tenant_name,is_auto_issuance,display_discount_value,updated_at,coupon_status',
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

            // Builder object
            // Addition select case and join for sorting by discount_value.
            $coupons = Coupon::allowedForPMPUser($user, 'coupon')
                ->with('couponRule')
                ->select(DB::raw("{$table_prefix}promotions.*, {$table_prefix}promotions.promotion_id as campaign_id, 'coupon' as campaign_type, {$table_prefix}campaign_price.campaign_price_id, {$table_prefix}coupon_translations.promotion_name AS name_english, media.path as image_path,
                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 'expired' ELSE {$table_prefix}campaign_status.campaign_status_name END) END AS campaign_status,
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
                    DB::raw("CASE WHEN {$table_prefix}campaign_price.base_price is null THEN 0 ELSE {$table_prefix}campaign_price.base_price END AS base_price"),
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
                    DB::raw("((CASE WHEN {$table_prefix}campaign_price.base_price is null THEN 0 ELSE {$table_prefix}campaign_price.base_price END) * (DATEDIFF({$table_prefix}promotions.end_date, {$table_prefix}promotions.begin_date) + 1) * (COUNT({$table_prefix}promotion_retailer.promotion_retailer_id))) AS estimated"),
                    DB::raw("COUNT(DISTINCT {$table_prefix}promotion_retailer.promotion_retailer_id) as total_location")
                )
                ->leftJoin('campaign_price', function ($join) {
                         $join->on('promotions.promotion_id', '=', 'campaign_price.campaign_id')
                              ->where('campaign_price.campaign_type', '=', 'coupon');
                  })
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                //->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'coupon_translations.merchant_language_id')
                ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                ->leftJoin(DB::raw("( SELECT * FROM {$table_prefix}media WHERE media_name_long = 'coupon_translation_image_resized_default' ) as media"), DB::raw('media.object_id'), '=', 'coupon_translations.coupon_translation_id')
                ->where('languages.name', '=', 'en')
                ->joinPromotionRules()
                ->groupBy('promotions.promotion_id');

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
                $coupons->whereIn('promotions.promotion_id', $promotionIds);
            });

            // to do : enable filter for mall id
            // Filter coupon by merchant Ids
            // OrbitInput::get('merchant_id', function ($merchantIds) use ($coupons) {
            //     $coupons->whereIn('promotions.merchant_id', (array)$merchantIds);
            // });

            // Filter coupon by merchant Ids / dupes, same as above
            // OrbitInput::get('current_mall', function ($merchantIds) use ($coupons) {
            //     $coupons->whereIn('promotions.merchant_id', (array)$merchantIds);
            // });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', '=', $promotionName);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('coupon_translations.promotion_name', 'like', "%$promotionName%");
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

            // Filter news by estimated total cost
            OrbitInput::get('etc_from', function ($etcfrom) use ($coupons) {
                $etcto = OrbitInput::get('etc_to');
                if (empty($etcto)) {
                    $coupons->havingRaw('estimated >= ' . floatval(str_replace(',', '', $etcfrom)));
                }
            });

            // Filter coupon by estimated total cost
            OrbitInput::get('etc_to', function ($etcto) use ($coupons) {
                $etcfrom = OrbitInput::get('etc_from');
                if (empty($etcfrom)) {
                    $etcfrom = 0;
                }
                $coupons->havingRaw('estimated between ' . floatval(str_replace(',', '', $etcfrom)) . ' and '. floatval(str_replace(',', '', $etcto)));
            });

            $from_cs = OrbitInput::get('from_cs', 'no');

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($coupons, $from_cs) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $coupons->with('mall');
                    } elseif ($relation === 'tenants') {
                        if ($from_cs === 'yes') {
                            $coupons->with(array('tenants' => function($q) {
                                $q->where('merchants.status', 'active');
                            }));
                        } else {
                            $coupons->with('tenants');
                        }
                    } elseif ($relation === 'tenants.mall') {
                        if ($from_cs === 'yes') {
                            $coupons->with(array('tenants' => function($q) {
                                $q->where('merchants.status', 'active');
                                $q->with('mall');
                            }));
                        } else {
                            $coupons->with('tenants.mall');
                        }
                    } elseif ($relation === 'translations') {
                        $coupons->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $coupons->with('translations.media');
                    } elseif ($relation === 'employee') {
                        $coupons->with('employee.employee.retailers');
                    } elseif ($relation === 'link_to_tenants') {
                        $coupons->with('linkToTenants');
                    } elseif ($relation === 'link_to_tenants.mall') {
                        if ($from_cs === 'yes') {
                            $coupons->with(array('linkToTenants' => function($q) {
                                $q->where('merchants.status', 'active');
                                $q->with('mall');
                            }));
                        } else {
                            $coupons->with('linkToTenants.mall');
                        }
                    } elseif ($relation === 'campaignLocations') {
                        $coupons->with('campaignLocations');
                    } elseif ($relation === 'campaignLocations.mall') {
                        $coupons->with('campaignLocations.mall');
                    } elseif ($relation === 'genders') {
                        $coupons->with('genders');
                    } elseif ($relation === 'ages') {
                        $coupons->with('ages');
                    } elseif ($relation === 'keywords') {
                        $coupons->with('keywords');
                    }
                }
            });

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
                    'registered_date'          => 'promotions.created_at',
                    'promotion_name'           => 'coupon_translations.promotion_name',
                    'promotion_type'           => 'promotions.promotion_type',
                    'description'              => 'promotions.description',
                    'begin_date'               => 'promotions.begin_date',
                    'end_date'                 => 'promotions.end_date',
                    'updated_at'               => 'promotions.updated_at',
                    'is_permanent'             => 'promotions.is_permanent',
                    'status'                   => 'campaign_status',
                    'rule_type'                => 'rule_type',
                    'tenant_name'              => 'tenant_name',
                    'is_auto_issuance'         => 'is_auto_issue_on_signup',
                    'display_discount_value'   => 'display_discount_value',
                    'coupon_status'            => 'coupon_status'
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
                $coupons->orderBy('coupon_translations.promotion_name', 'asc');
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

/*
            if (! ACL::create($user)->isAllowed('delete_coupon')) {
                Event::fire('orbit.coupon.redeemcoupon.authz.notallowed', array($this, $user));
                $deleteCouponLang = Lang::get('validation.orbit.actionlist.delete_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
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

            $issuedCouponId = OrbitInput::post('issued_coupon_id');
            $verificationNumber = OrbitInput::post('merchant_verification_number');

            $validator = Validator::make(
                array(
                    'current_mall' => $mall_id,
                    'issued_coupon_id' => $issuedCouponId,
                    'merchant_verification_number' => $verificationNumber,
                ),
                array(
                    'current_mall'                  => 'required|orbit.empty.merchant',
                    'issued_coupon_id'              => 'required|orbit.empty.issuedcoupon',
                    'merchant_verification_number'  => 'required'
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
            Event::fire('orbit.coupon.postissuedcoupon.after.validation', array($this, $validator));

            $tenant = Tenant::active()
                ->where('parent_id', $mall_id)
                ->where('masterbox_number', $verificationNumber)
                ->first();

            $csVerificationNumber = UserVerificationNumber::
                where('merchant_id', $mall_id)
                ->where('verification_number', $verificationNumber)
                ->first();

            $redeem_retailer_id = NULL;
            $redeem_user_id = NULL;
            if (! is_object($tenant) && ! is_object($csVerificationNumber)) {
                // @Todo replace with language
                $message = 'Tenant is not found.';
                ACL::throwAccessForbidden($message);
            } else {
                if (is_object($tenant)) {
                    $redeem_retailer_id = $tenant->merchant_id;
                }
                if (is_object($csVerificationNumber)) {
                    $redeem_user_id = $csVerificationNumber->user_id;
                }
            }

            $mall = App::make('orbit.empty.merchant');
            $issuedcoupon = App::make('orbit.empty.issuedcoupon');

            // The coupon information
            $coupon = $issuedcoupon->coupon;

            $issuedcoupon->redeemed_date = date('Y-m-d H:i:s');
            $issuedcoupon->redeem_retailer_id = $redeem_retailer_id;
            $issuedcoupon->redeem_user_id = $redeem_user_id;
            $issuedcoupon->redeem_verification_code = $verificationNumber;
            $issuedcoupon->status = 'redeemed';

            Event::fire('orbit.coupon.postissuedcoupon.before.save', array($this, $issuedcoupon));

            $issuedcoupon->save();

            Event::fire('orbit.coupon.postissuedcoupon.after.save', array($this, $issuedcoupon));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.coupon');

            // Commit the changes
            $this->commit();

            $this->response->message = 'Coupon has been successfully redeemed.';
            $this->response->data = $issuedcoupon;

            // Successfull Creation
            $activityNotes = sprintf('Coupon Redeemed: %s', $issuedcoupon->coupon->promotion_name);
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

            Event::fire('orbit.coupon.postissuedcoupon.after.commit', array($this, $issuedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.redeemcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption (Failed)')
                    ->setObject($issuedcoupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.redeemcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption (Failed)')
                    ->setObject($issuedcoupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption (Failed)')
                    ->setObject($issuedcoupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.coupon.redeemcoupon.general.exception', array($this, $e));

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
                    ->setObject($issuedcoupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

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

    protected function registerCustomValidation()
    {
        // Check maximal of total issued coupons
        Validator::extend('orbit.max.total_issued_coupons', function ($attribute, $value, $parameters) {
            $promotion_id = $parameters[0];

            $total_issued_coupons = IssuedCoupon::where('promotion_id', '=', $promotion_id)
                                                ->count();

            if ($value < $total_issued_coupons) {
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

            $prefix = DB::getTablePrefix();

            $issuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
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
                $checkIssuedCoupon = Tenant::where('parent_id','=', $mall_id)
                            ->where('status', 'active')
                            ->where('masterbox_number', $number)
                            ->first();
            } elseif ($issuedCoupon->coupon->is_all_retailer === 'N') {
                $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                            ->join('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where('issued_coupons.issued_coupon_id', $value)
                            ->where('issued_coupons.user_id', $user->user_id)
                            // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
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
                    $checkIssuedCoupon = UserVerificationNumber::
                                join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('status', 'active')
                                ->where('merchant_id', $mall_id)
                                ->where('verification_number', $number)
                                ->first();
                } elseif ($issuedCoupon->coupon->is_all_employee === 'N') {
                    $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                                ->join('promotion_employee', 'promotion_employee.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->join('user_verification_numbers', 'user_verification_numbers.user_id', '=', 'promotion_employee.user_id')
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

        // Check coupon name, it should not exists
        Validator::extend('orbit.exists.coupon_name', function ($attribute, $value, $parameters) {
            $merchant_id = OrbitInput::post('current_mall');

            $couponName = Coupon::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($couponName)) {
                return FALSE;
            }

            App::instance('orbit.validation.coupon_name', $couponName);

            return TRUE;
        });

        // Check coupon name, it should not exists (for update)
        Validator::extend('coupon_name_exists_but_me', function ($attribute, $value, $parameters) {
            $promotion_id = trim(OrbitInput::post('promotion_id'));
            $merchant_id = OrbitInput::post('current_mall');

            $coupon = Coupon::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('promotion_id', '!=', $promotion_id)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.validation.coupon_name', $coupon);

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
            $statuses = array('auto_issue_on_signup', 'auto_issue_on_first_signin', 'auto_issue_on_every_signin');
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

    }

    /**
     * @param Coupon $coupon
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($coupon, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where Coupon object is object with keys:
         *   promotion_name, description, long_description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['promotion_name', 'description', 'long_description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);

        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
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
                    if (! empty(trim($translations->promotion_name))) {
                        $coupon_translation = CouponTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $merchant_language_id)
                                                    ->where('promotion_name', '=', $translations->promotion_name)
                                                    ->first();
                        if (! empty($coupon_translation)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.coupon_name'));
                        }
                    }
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    if (! empty(trim($translations->promotion_name))) {
                        $coupon_translation_but_not_me = CouponTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $merchant_language_id)
                                                    ->where('promotion_id', '!=', $coupon->promotion_id)
                                                    ->where('promotion_name', '=', $translations->promotion_name)
                                                    ->first();
                        if (! empty($coupon_translation_but_not_me)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.coupon_name'));
                        }
                    }
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

}
