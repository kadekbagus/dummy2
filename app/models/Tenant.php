<?php

class Tenant extends Eloquent
{
    /**
     * Tenant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait MallTypeTrait so we only displaying records with value
     * `object_type` = 'tenant'
     */
    use MallTypeTrait;

    /**
     * Column name which determine the type of Mall or Tenant.
     */
    const OBJECT_TYPE = 'object_type';


    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function mall()
    {
        return $this->belongsTo('Mall', 'parent_id', 'merchant_id');
    }

    // @author Irianto Pratama <irianto@dominopos.com>
    public function link_to_tenant()
    {
        return $this->hasOne('RetailerTenant', 'retailer_id', 'merchant_id');
    }

    public function parent()
    {
        return $this->mall();
    }

    public function news()
    {
        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')->where('news.object_type', 'news')->active();
    }

    public function newsProfiling()
    {
        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')
                    ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                    ->where('news.object_type', 'news')
                    ->where('news.status', '=', 'active');
    }

    /**
     * Event strings can be translated to many languages.
     */
    public function translations()
    {
        return $this->hasMany('MerchantTranslation', 'merchant_id', 'merchant_id')->excludeDeleted()->whereHas('language', function($has) {
            $has->where('merchant_languages.status', 'active');
        });

    }

    public function newsPromotions()
    {
        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')->where('news.object_type', 'promotion')->active();
    }

    public function newsPromotionsProfiling()
    {
        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')
                    ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                    ->where('news.object_type', 'promotion')
                    ->where('news.status', '=', 'active');
    }

    public function coupons()
    {
        return $this->belongsToMany('Coupon', 'promotion_retailer', 'retailer_id', 'promotion_id')->active();
    }

    public function couponsProfiling()
    {
        return $this->belongsToMany('Coupon', 'promotion_retailer', 'retailer_id', 'promotion_id')
                    ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                    ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                    ->where('promotions.is_coupon', 'Y')
                    ->where('promotions.status', '=', 'active');
    }

    /**
     * A Retailer has many and belongs to an employee
     */
    public function employees()
    {
        return $this->belongsToMany('Employee', 'employee_retailer', 'merchant_id', 'retailer_id');
    }

    public function keywords()
    {
        return $this->hasMany('KeywordObject', 'object_id', 'merchant_id')
                    ->join('keywords', 'keywords.keyword_id', '=', 'keyword_object.keyword_id');
    }

    /**
     * Eagler load the count query. It is not very optimized but it works for now
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit http://laravel.io/forum/05-03-2014-eloquent-get-count-relation
     * @return int
     */
    public function mallNumber()
    {
        // Basically we query Merchant which the its id are on the parent_id
        // of retailers
        return $this->belongsTo('Mall', 'parent_id', 'merchant_id')
                    ->excludeDeleted()
                    ->selectRaw('merchant_id, count(*) as count')
                    ->groupBy('merchant_id');
    }

    public function getMallCountAttribute()
    {
        return $this->mallNumber ? $this->mallNumber->count : 0;
    }

    public function userNumber()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')
                    ->excludeDeleted()
                    ->selectRaw('user_id, count(*) as count')
                    ->groupBy('user_id');
    }

    public function getUserCountAttribute()
    {
        return $this->userNumber ? $this->userNumber->count : 0;
    }

    /**
     * Retailers belongs to and has many category.
     */
    public function categories()
    {
        return $this->belongsToMany('Category', 'category_merchant', 'merchant_id', 'category_id');
    }

    /**
     * Add Filter retailers based on user who request it.
     *
     * @author Rio Astamal <me@rioastamal.net>
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

        // if user role is consumer, then do nothing.
        $consumer = array('consumer');
        $userRole = trim(strtolower($user->role->role_name));
        if (in_array($userRole, $consumer))
        {
            // do nothing return as is
            return $builder;
        }

        // This will filter only user which belongs to retailer or
        // merchant owner (the parent). The merchant owner has an ability
        // to view all retailers
        $builder->where(function($query) use ($user)
        {
            $prefix = DB::getTablePrefix();
            $query->where('merchants.user_id', $user->user_id)
                  ->orWhereRaw("{$prefix}merchants.parent_id in (select m2.merchant_id from {$prefix}merchants m2
                                where m2.object_type='merchant' and
                                m2.status != 'deleted' and
                                m2.user_id=?)", array($user->user_id));
        });

        return $builder;
    }

    /**
     * Add Filter merchant based on transaction and users.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param array $userIds - List of user ids
     * @param array $merchantIds - List of merchant Ids
     */
    public function scopeTransactionCustomerMerchantIds($builder, array $userIds, array $merchantIds)
    {
        return $builder->select('merchants.*')
                       // ->join('transactions', 'transactions.merchant_id', '=', 'merchants.merchant_id')
                       ->join('transactions', function($join) {
                            $join->on('transactions.retailer_id', '=', 'merchants.merchant_id');
                            $join->on('merchants.object_type', '=', DB::raw("'retailer'"));
                       })
                       ->where('transactions.status', 'paid')
                       ->whereIn('customer_id', $userIds)
                       ->whereIn('merchants.parent_id', $merchantIds)
                       ->groupBy('merchants.merchant_id');
    }

    /**
     * Retailer has many uploaded media.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'retailer');
    }

    /**
     * Retailer has many uploaded media with original type.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaOrig()
    {
        return $this->hasMany('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'retailer')
                    ->where('media_name_long', 'like', '%_orig')
                    ->orderBy('metadata', 'asc');
    }

    /**
     * Retailer has many uploaded media with cropped_default type.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaCroppedDefault()
    {
        return $this->hasMany('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'retailer')
                    ->where('media_name_long', 'like', '%_cropped_default')
                    ->orderBy('metadata', 'asc');
    }

    /**
     * Retailer has many uploaded media with cropped_default type.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaResizedDefault()
    {
        return $this->hasMany('Media', 'object_id', 'merchant_id')
                    ->where('object_name', 'retailer')
                    ->where('media_name_long', 'like', '%_resized_default');
    }

    /**
     * Retailer has many uploaded logo.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'retailer_logo');
    }

    /**
     * Retailer has many uploaded logo.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaLogoOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'retailer_logo');
    }

    /**
     * Retailer has many uploaded images.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImage()
    {
        return $this->media()->where('media_name_id', 'retailer_image');
    }

    /**
     * Retailer has many uploaded images.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaImageOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'retailer_image');
    }

    /**
     * Retailer has many uploaded maps.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaMap()
    {
        return $this->media()->where('media_name_id', 'retailer_map');
    }

    /**
     * Retailer has many uploaded maps.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaMapOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'retailer_map');
    }

    /**
     * Merchant/Retailer/Mall has many uploaded backgrounds.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaBackground()
    {
        return $this->media()->where('media_name_id', 'retailer_background');
    }

    /**
     * Retailer has many uploaded backgrounds.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mediaBackgroundOrig()
    {
        return $this->mediaOrig()->where('media_name_id', 'retailer_background');
    }

    public static function getStatus($idtenant)
    {
        return Tenant::where('merchant_id', '=', $idtenant)->pluck('status'); 
    }

    public function merchantSocialMedia() {
        return $this->hasMany('MerchantSocialMedia', 'merchant_id', 'merchant_id');
    }
}
