<?php
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

    public function campaign_status()
    {
        return $this->belongsTo('CampaignStatus', 'campaign_status_id', 'campaign_status_id');
    }

    public function campaignLocations()
    {
        return $this->belongsToMany('CampaignLocation', 'promotion_retailer', 'promotion_id', 'retailer_id');
    }

    /**
     * Coupon strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('CouponTranslation', 'promotion_id', 'promotion_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });
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

    /**
     * Accessor for empty product image
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param string $value - image path
     * @return string $value
     */
    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return 'mobile-ci/images/default_coupon.png';
        }
        return ($value);
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
    public function scopeCampaignStatus($query, $campaign_status, $mallTime)
    {
        $prefix = DB::getTablePrefix();

        return $query->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                     ->where(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < {$this->quote($mallTime)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END"), $campaign_status);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
