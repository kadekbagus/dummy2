<?php
use Carbon\Carbon as Carbon;

class Coupon extends Eloquent
{
    /**
     * Coupon Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;
    use CampaignStatusTrait;
    use CampaignAccessTrait;

    /**
     * Use Trait PromotionTypeTrait so we only displaying records with value
     * `is_coupon` = 'N'
     */
    use PromotionCouponTrait;

    /**
     * Column name which determine the type of Promotion or Coupon.
     */
    const OBJECT_TYPE = 'is_coupon';
    const NO_AVAILABLE_COUPON_ERROR_CODE = 1211;
    const THIRD_PARTY_COUPON_TENANT_VALIDATION_ERROR = 1212;
    const IS_EXCLUSIVE_ERROR_CODE = 9001;
    const NOT_FOUND_ERROR_CODE = 404;
    const INACTIVE_ERROR_CODE = 4040;

    const TYPE_NORMAL = 'mall';
    const TYPE_SEPULSA = 'sepulsa';
    const TYPE_HOT_DEALS = 'hot_deals';
    const TYPE_GIFTNCOUPON = 'gift_n_coupon';

    protected $table = 'promotions';

    protected $primaryKey = 'promotion_id';

    public function couponRule()
    {
        return $this->hasOne('CouponRule', 'promotion_id', 'promotion_id');
    }

    public function mall()
    {
        return $this->belongsTo('Mall', 'merchant_id', 'merchant_id')->isMall();
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function tenants()
    {
        return $this->belongsToMany('Tenant', 'promotion_retailer_redeem', 'promotion_id', 'retailer_id');
    }

    public function employee()
    {
        return $this->belongsToMany('User', 'promotion_employee', 'promotion_id', 'user_id');
    }

    public function linkToTenants()
    {
        return $this->belongsToMany('Tenant', 'promotion_retailer', 'promotion_id', 'retailer_id');
    }

    public function linkToMalls()
    {
        return $this->belongsToMany('Mall', 'promotion_retailer', 'promotion_id', 'retailer_id');
    }

    public function issuedCoupons()
    {
        return $this->hasMany('IssuedCoupon', 'promotion_id', 'promotion_id');
    }

    public function genders()
    {
        return $this->hasMany('CampaignGender', 'campaign_id', 'promotion_id');
    }

    public function ages()
    {
        return $this->hasMany('CampaignAge', 'campaign_id', 'promotion_id')
                    ->join('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id');
    }

    public function keywords()
    {
        return $this->hasMany('KeywordObject', 'object_id', 'promotion_id')
                    ->join('keywords', 'keywords.keyword_id', '=', 'keyword_object.keyword_id');
    }

    public function product_tags()
    {
        return $this->hasMany('ProductTagObject', 'object_id', 'promotion_id')
                    ->join('product_tags', 'product_tags.product_tag_id', '=', 'product_tag_object.product_tag_id');
    }

    public function campaign_status()
    {
        return $this->belongsTo('CampaignStatus', 'campaign_status_id', 'campaign_status_id');
    }

    public function total_page_views()
    {
        return $this->hasMany('TotalObjectPageView', 'object_id', 'promotion_id');
    }

    public function adverts()
    {
        return $this->hasMany('Advert', 'link_object_id', 'promotion_id')
            ->leftJoin('advert_link_types', 'adverts.advert_link_type_id', '=', 'advert_link_types.advert_link_type_id')
            ->where('advert_link_types.advert_type', '=', 'coupon');
    }

    public function campaignLocations()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'promotion_retailer', 'promotion_id', 'retailer_id')
                ->select('merchants.*', DB::raw("IF({$prefix}merchants.object_type = 'tenant', (select language_id from {$prefix}languages where name = pm.mobile_default_language), (select language_id from {$prefix}languages where name = {$prefix}merchants.mobile_default_language)) as default_language"))
                ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id');
    }

    public function esCampaignLocations()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'promotion_retailer', 'promotion_id', 'retailer_id')
                ->select(
                    'merchants.merchant_id',
                    'merchants.name',
                    'merchants.object_type',
                    DB::raw('oms.city,
                    oms.province,
                    oms.country,
                    oms.country_id'),
                    DB::raw("
                        (CASE WHEN {$prefix}merchants.object_type = 'tenant'
                            THEN {$prefix}merchants.parent_id
                            ELSE {$prefix}merchants.merchant_id
                        END) as parent_id
                    "),
                    DB::raw("
                        (CASE WHEN {$prefix}merchants.object_type = 'tenant'
                            THEN oms.name
                            ELSE {$prefix}merchants.name
                        END) as mall_name
                    ")
                )
                ->leftJoin(DB::raw("{$prefix}merchants oms"), DB::raw("oms.merchant_id"), '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"))
                ;
    }

    public function country()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'promotion_retailer', 'promotion_id', 'retailer_id')
                ->select(DB::raw('oms.country'))
                ->leftJoin(DB::raw("{$prefix}merchants oms"), DB::raw("oms.merchant_id"), '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"))
                ->groupBy(DB::raw('oms.country'));
    }

    public function city()
    {
        $prefix = DB::getTablePrefix();
        return $this->belongsToMany('CampaignLocation', 'promotion_retailer', 'promotion_id', 'retailer_id')
                ->select(DB::raw('oms.city'))
                ->leftJoin(DB::raw("{$prefix}merchants oms"), DB::raw("oms.merchant_id"), '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"))
                ->groupBy(DB::raw('oms.city'));
    }

    public function campaignObjectPartners()
    {
        $prefix = DB::getTablePrefix();
        return $this->hasMany('ObjectPartner', 'object_id', 'promotion_id')
                      ->select('object_partner.object_id', DB::raw("{$prefix}partners.partner_id"), DB::raw("{$prefix}partners.partner_name"), DB::raw("{$prefix}partners.token"), DB::raw("{$prefix}partners.is_exclusive"))
                      ->leftjoin('partners', 'partners.partner_id', '=', 'object_partner.partner_id');
    }

    /**
     * Coupon strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('CouponTranslation', 'promotion_id', 'promotion_id')
                    ->where('coupon_translations.status', '!=', 'deleted')
                    ->has('language')
                    ->join('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id');
    }

    public function coupon_sepulsa()
    {
        return $this->hasOne('CouponSepulsa', 'promotion_id', 'promotion_id');
    }

    public function discounts()
    {
        return $this->belongsToMany('Discount', 'object_discount', 'object_id')->where('object_type', 'coupon')->withTimestamps();
    }

    /**
     * Add Filter coupons based on user who request it.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForUser($builder, $user)
    {
        // Super admin allowed to see all entries
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only coupons which belongs to merchant
        // The merchant owner has an ability to view all coupons
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}promotions.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Join promotion retailer
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function scopeJoinPromotionRetailer($query)
    {
        return $query->join('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id');
    }

    /**
     * Join promotion retailer with merchants
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function scopeJoinMerchant($query)
    {
        return $query->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id');
    }

    /**
     * Join promotion retailer
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    public function scopeJoinPromotionRules($query)
    {
        return $query->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id');
    }

    /**
     * Join promotion retailer
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param double $amount - User receipt money amount
     */
    public static function getApplicableCoupons($amount, $retailerIds=[])
    {
        if (empty($retailerIds)) {
            throw new Exception('Could not get applicable coupons, tenants argument is empty.');
        }

        $prefix = DB::getTablePrefix();
        $now = date('Y-m-d');
        $amount = (double)$amount;
        return Coupon::selectRaw("(floor ($amount / {$prefix}promotion_rules.rule_value)) issue_count,
                                  {$prefix}promotion_rules.rule_value,
                                  {$prefix}promotions.*")
                    ->joinPromotionRetailer()
                    ->joinPromotionRules()
                    ->whereRaw("(floor ($amount / {$prefix}promotion_rules.rule_value)) > 0")
                    ->whereRaw("(date('$now') >= date({$prefix}promotions.begin_date) and date('$now') <= date({$prefix}promotions.end_date))")
                    ->whereRaw("(select count({$prefix}issued_coupons.promotion_id) from {$prefix}issued_coupons
                                        where {$prefix}issued_coupons.promotion_id={$prefix}promotions.promotion_id
                                        and status!='deleted') < {$prefix}promotions.maximum_issued_coupon")
                    ->active('promotions')
                    ->whereIn('promotion_retailer.retailer_id', $retailerIds)
                    ->groupBy('promotions.promotion_id');
    }

    /**
     * Add Filter coupons based on user who request it. (Should be used on view only)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  User $user Instance of object user
     */
    public function scopeAllowedForViewOnly($builder, $user)
    {
        // Super admin and Consumer allowed to see all entries
        // Weird? yeah this is supposed to call on merchant portal only
        $superAdmin = Config::get('orbit.security.superadmin');
        if (empty($superAdmin))
        {
            $superAdmin = array('super admin', 'consumer');
        }

        // Transform all array into lowercase
        $superAdmin = array_map('strtolower', $superAdmin);
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $superAdmin))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only coupons which belongs to merchant
        // The merchant owner has an ability to view all coupons
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->whereRaw("{$prefix}promotions.merchant_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.user_id=? and m2.object_type='merchant')", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Coupon has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'promotion_id')
                    ->where('object_name', 'coupon');
    }

    public function scopeOfMerchantId($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Runnning Date dynamic scope
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @todo Make a trait for such method
     */
    public function scopeOfRunningDate($query, $date)
    {
        return $query->where('begin_date', '<=', $date)->where('end_date', '>=', $date);
    }

    /**
     * Campaign Status scope
     *
     * @author Irianto <irianto@dominopos.com>
     * @todo change campaign status to expired when over the end date
     */
    public function scopeCampaignStatus($query, $campaign_status, $mallTimezone = NULL)
    {
        $prefix = DB::getTablePrefix();
        $quote = function($arg)
        {
            return DB::connection()->getPdo()->quote($arg);
        };

        if ($mallTimezone != NULL) {
            return $query->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                         ->where(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                            THEN {$prefix}campaign_status.campaign_status_name
                                                ELSE (CASE WHEN {$prefix}promotions.end_date < {$quote($mallTimezone)}
                                                        THEN 'expired'
                                                            ELSE {$prefix}campaign_status.campaign_status_name
                                                        END)
                                            END"), $campaign_status);
        }

        return $query->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                     ->where(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (CASE WHEN {$prefix}promotions.end_date < UTC_TIMESTAMP()
                                                    THEN 'expired'
                                                        ELSE {$prefix}campaign_status.campaign_status_name
                                                    END)
                                        END"), $campaign_status);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public static function issueAutoCoupon($retailer, $user, $session)
    {
        if (! $user->isConsumer()) {
            return;
        }

        $userAge = 0;
        if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
            $userAge =  self::calculateAge($user->userDetail->birthdate); // 27
        }

        $userGender = 'U'; // default is Unknown
        if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
            $userGender =  $user->userDetail->gender;
        }

        $mallTime = Carbon::now($retailer->timezone->timezone_name);


        //filter by age and gender
        $queryAgeGender = '';

        if ($userGender !== null) {
            $queryAgeGender .= " AND ( gender_value = '" . $userGender . "' OR is_all_gender = 'Y' ) ";
        }

        if ($userAge !== null) {
            if ($userAge === 0){
                $queryAgeGender .= " AND ( (min_value = " . $userAge . " and max_value = " . $userAge . " ) or is_all_age = 'Y' ) ";
            } else {
                if ($userAge >= 55) {
                    $queryAgeGender .=  " AND ( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ";
                } else {
                    $queryAgeGender .=  " AND ( (min_value <= " . $userAge . " and max_value >= " . $userAge . " ) or is_all_age = 'Y' ) ";
                }
            }
        }

        $prefix = DB::getTablePrefix();
        // check available coupon campaigns
        $coupons = DB::select(
            DB::raw("
                        SELECT *,
                        (
                        select count(ic.issued_coupon_id) from {$prefix}issued_coupons ic
                        where ic.promotion_id = p.promotion_id
                        and ic.status != 'deleted') as total_issued_coupon
                        FROM {$prefix}promotions p
                        inner join {$prefix}promotion_rules pr on p.promotion_id = pr.promotion_id

                        left join {$prefix}campaign_gender cg on cg.campaign_id = p.promotion_id
                        left join {$prefix}campaign_age ca on ca.campaign_id = p.promotion_id
                        left join {$prefix}age_ranges ar on ar.age_range_id = ca.age_range_id
                        left join {$prefix}promotion_retailer pre on pre.promotion_id = p.promotion_id
                        left join {$prefix}merchants m on m.merchant_id = pre.retailer_id

                        WHERE 1=1
                            " . $queryAgeGender . "
                            AND (m.parent_id = :merchantid or m.merchant_id = :merchantidx)
                            AND p.is_coupon = 'Y' AND p.status = 'active'
                            AND p.begin_date <= '" . $mallTime . "'
                            AND p.end_date >= '" . $mallTime . "'
                            AND p.coupon_validity_in_date >= '" . $mallTime . "'
                        GROUP BY p.promotion_id
                        HAVING
                            (p.maximum_issued_coupon > total_issued_coupon AND p.maximum_issued_coupon <> 0)
                            OR
                            (p.maximum_issued_coupon = 0)
                    "),
                array(
                        'merchantid' => $retailer->merchant_id,
                        'merchantidx' => $retailer->merchant_id
                    )
            );

        // check available auto-issuance coupon that already obtained by user
        $obtained_coupons = DB::select(
            DB::raw("
                SELECT * FROM {$prefix}promotions p
                inner join {$prefix}promotion_rules pr on p.promotion_id = pr.promotion_id
                inner join {$prefix}issued_coupons ic on p.promotion_id = ic.promotion_id
                left join {$prefix}promotion_retailer pre on pre.promotion_id = p.promotion_id
                left join {$prefix}merchants m on m.merchant_id = pre.retailer_id
                WHERE
                    (m.parent_id = :merchantid or m.merchant_id = :merchantidx)
                    AND ic.user_id = :userid
                    AND p.is_coupon = 'Y' AND p.status = 'active'
                    AND p.begin_date <= '" . $mallTime . "'
                    AND p.end_date >= '" . $mallTime . "'
                    AND pr.rule_type != 'auto_issue_on_every_signin'
                GROUP BY p.promotion_id
            "),
            array('merchantid' => $retailer->merchant_id, 'merchantidx' => $retailer->merchant_id, 'userid' => $user->user_id)
        );

        // get obtained auto-issuance coupon ids
        $obtained_coupon_ids = array();
        foreach ($obtained_coupons as $obtained_coupon) {
            $obtained_coupon_ids[] = $obtained_coupon->promotion_id;
        }

        // filter available auto-issuance coupon id by array above
        $coupons_to_be_obtained = array_filter(
            $coupons,
            function ($v) use ($obtained_coupon_ids) {
                $match = TRUE;
                foreach ($obtained_coupon_ids as $key => $obtained_coupon) {
                    if($v->promotion_id === $obtained_coupon) {
                        $match = $match && FALSE;
                    }
                }

                if($match) {
                    return $v;
                }
            }
        );

        // get available auto-issuance coupon ids
        $couponIds = array();
        foreach ($coupons_to_be_obtained as $coupon_to_be_obtained) {
            $couponIds[] = $coupon_to_be_obtained->promotion_id;
        }

        $isSignedIn = TRUE;
        $coupon_locations = [];
        if (! empty($session->read('coupon_location'))) {
            $coupon_locations = $session->read('coupon_location');
        }

        if (in_array($retailer->merchant_id, $coupon_locations)) {
            $isSignedIn = FALSE;
        }

        // use them to issue
        if(count($couponIds)) {
            // Issue coupons
            $objectCoupons = [];
            $issuedCoupons = [];
            $numberOfCouponIssued = 0;
            $applicableCouponNames = [];
            $issuedCouponNames = [];
            $prefix = DB::getTablePrefix();

            foreach ($coupons_to_be_obtained as $coupon) {
                $issued = false;

                $ruleBeginDateUTC = Carbon::createFromFormat('Y-m-d H:i:s', $coupon->rule_begin_date, $retailer->timezone->timezone_name);
                $ruleBeginDateUTC->setTimezone('UTC');

                $ruleEndDateUTC = Carbon::createFromFormat('Y-m-d H:i:s', $coupon->rule_end_date, $retailer->timezone->timezone_name);
                $ruleEndDateUTC->setTimezone('UTC');

                $couponBeginDateUTC = Carbon::createFromFormat('Y-m-d H:i:s', $coupon->begin_date, $retailer->timezone->timezone_name);
                $couponBeginDateUTC->setTimezone('UTC');

                $couponEndDateUTC = Carbon::createFromFormat('Y-m-d H:i:s', $coupon->end_date, $retailer->timezone->timezone_name);
                $couponEndDateUTC->setTimezone('UTC');

                if ($coupon->rule_type === 'auto_issue_on_signup') {
                    $issued = \UserAcquisition::where('acquirer_id', $retailer->merchant_id)
                                            ->where('user_id', $user->user_id)
                                            ->whereRaw("created_at between ? and ?", [$ruleBeginDateUTC, $ruleEndDateUTC])->first();
                } elseif ($coupon->rule_type === 'auto_issue_on_first_signin') {
                    if ($isSignedIn) {
                        if ($couponBeginDateUTC == $ruleBeginDateUTC) {

                            if ($mallTime >= $ruleBeginDateUTC && $mallTime <= $ruleEndDateUTC) {
                                $acq = \UserAcquisition::where('acquirer_id', $retailer->merchant_id)
                                                        ->where('user_id', $user->user_id)->first();

                                $never_sign_in = \UserSignin::where('location_id', $retailer->merchant_id)
                                                        ->where('user_id', $user->user_id)->first();

                                $signin_in_rule_period = \UserSignin::where('location_id', $retailer->merchant_id)
                                                        ->where('user_id', $user->user_id)
                                                        ->whereRaw("created_at between ? and ?", [$ruleBeginDateUTC, $ruleEndDateUTC])->first();

                                if(! empty($signin_in_rule_period)) {
                                    $issued = true;
                                }

                                if (!empty($acq) && empty($never_sign_in)) {
                                    $issued = true;
                                }

                                if ($mallTime >= $couponBeginDateUTC && $mallTime <= $couponEndDateUTC) {
                                    $issued = true;
                                }
                            }

                        }
                        elseif ($ruleBeginDateUTC < $couponBeginDateUTC) {

                            if ($mallTime >= $ruleBeginDateUTC && $mallTime <= $ruleEndDateUTC) {
                                if ($mallTime >= $couponBeginDateUTC && $mallTime <= $couponEndDateUTC) {
                                    $acq = \UserAcquisition::where('acquirer_id', $retailer->merchant_id)
                                                        ->where('user_id', $user->user_id)->first();

                                    $never_sign_in = \UserSignin::where('location_id', $retailer->merchant_id)
                                                        ->where('user_id', $user->user_id)->first();

                                    $signin_in_rule_period = \UserSignin::where('location_id', $retailer->merchant_id)
                                                        ->where('user_id', $user->user_id)
                                                        ->whereRaw("created_at between ? and ?", [$ruleBeginDateUTC, $ruleEndDateUTC])->first();

                                    if(! empty($signin_in_rule_period)) {
                                        $issued = true;
                                    }

                                    if (!empty($acq) && empty($never_sign_in)) {
                                        $issued = true;
                                    }
                                }
                            }

                        }
                    }
                } elseif ($coupon->rule_type === 'auto_issue_on_every_signin') {
                    if ($isSignedIn) {
                        $issued = true;
                    }
                }

                if (! empty($issued)) {
                    $issuedCoupon = new IssuedCoupon();
                    $tmp = $issuedCoupon->issue($coupon, $user->user_id, $user, $retailer);

                    $obj = new stdClass();
                    $obj->coupon_number = $tmp->issued_coupon_code;
                    $obj->coupon_name = $coupon->promotion_name;
                    $obj->promotion_id = $coupon->promotion_id;

                    $objectCoupons[] = $coupon;
                    $issuedCoupons[] = $obj;
                    $applicableCouponNames[] = $coupon->promotion_name;
                    $issuedCouponNames[$tmp->issued_coupon_code] = $coupon->promotion_name;

                    $tmp = NULL;
                    $obj = NULL;

                    $numberOfCouponIssued++;
                }
            }

            // Insert to alert system
            $issuedCouponNames = self::flipArrayElement($issuedCouponNames);

            if (! empty($issuedCouponNames)) {
                $name = $user->getFullName();
                $name = trim($name) ? trim($name) : $user->user_email;
                $subject = 'Coupon';

                $inbox = new Inbox();
                $inbox->addToInbox($user->user_id, $issuedCouponNames, $retailer->merchant_id, 'coupon_issuance');
            }

            foreach ($objectCoupons as $object) {
                $activity_coupon = Coupon::where('promotion_id', $object->promotion_id)->first();

                $activity = Activity::mobileci()
                                    ->setActivityType('view');
                $activityPageNotes = sprintf('Page viewed: %s', 'Coupon List Page');
                $activity->setUser($user)
                        ->setLocation($retailer)
                        ->setActivityName('view_coupon_list')
                        ->setActivityNameLong('Coupon Issuance')
                        ->setObject($activity_coupon)
                        ->setCoupon($activity_coupon)
                        ->setModuleName('Coupon')
                        ->setNotes($activityPageNotes)
                        ->responseOK()
                        ->save();
            }
        }

        if ($isSignedIn) {
            $session->write('coupon_location', array_merge($coupon_locations, [$retailer->merchant_id]));
        }
    }

    protected static function flipArrayElement($source)
    {
        $flipped = [];

        $names = array_flip(array_unique(array_values($source)));
        foreach ($names as $key=>$name) {
            $names[$key] = [];
        }

        foreach ($source as $number=>$name) {
            $flipped[$name][] = $number;
        }

        return $flipped;
    }

    protected static function calculateAge($birth_date)
    {
        $age = date_diff(date_create($birth_date), date_create('today'))->y;

        if ($birth_date === null) {
            return null;
        }

        return $age;
    }

    /**
     * Determine if coupon is available for purchase or not.
     *
     * @return [type] [description]
     */
    public function notAvailable()
    {
        return $this->available === 0 || $this->status === 'inactive' || Carbon::now('UTC')->gt(Carbon::parse($this->end_date, 'UTC'));
    }

    /**
     * Update availability of current coupon.
     *
     * @return [type] [description]
     */
    public function updateAvailability()
    {
        $issued = IssuedCoupon::where('promotion_id', $this->promotion_id)->whereIn('status', [
                                    IssuedCoupon::STATUS_ISSUED,
                                    IssuedCoupon::STATUS_REDEEMED,
                                    IssuedCoupon::STATUS_RESERVED,
                                ])->count();

        $available = $this->maximum_issued_coupon - $issued;
        $available = $available < 0 ? 0 : $available;

        $this->available = $available;

        $this->touch();

        if ($this->available > 0) {
            Queue::later(2, 'Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                'coupon_id' => $this->promotion_id
            ]);
        }
        else if ($this->available === 0) {
            // Delete the coupon and also suggestion
            Queue::later(2, 'Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                'coupon_id' => $this->promotion_id
            ]);

            Queue::later(2, 'Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                'coupon_id' => $this->promotion_id
            ]);
        }
    }
}
