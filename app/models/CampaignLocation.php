<?php

class CampaignLocation extends Eloquent
{
    /**
     * Campaign Location Model
     * Defining the mall and tenant and probably other type of merchant as an entity
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

    /**
     * This enables $merchant->tenant_at_mall.
     *
     * @author Qosdil A. <qosdil@dominopos.com>
     * @return string
     */
    public function getTenantAtMallAttribute()
    {
        if ($this->object_type == 'mall') {
            return 'Mall at '.$this->name;
        }

        $mall = CampaignLocation::find($this->parent_id);

        return $this->name.' at '.$mall->name;
    }

    public function news()
    {
        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')->where('news.object_type', 'news')->active();
    }

    public function newsPromotions()
    {
        return $this->belongsToMany('News', 'news_merchant', 'merchant_id', 'news_id')->where('news.object_type', 'promotion')->active();
    }

    public function coupons()
    {
        return $this->belongsToMany('Coupon', 'promotion_retailer', 'retailer_id', 'promotion_id')->active();
    }

    public function mall()
    {
        return $this->belongsTo('Mall', 'parent_id', 'merchant_id');
    }

    public function timezone()
    {
        return $this->belongsTo('Timezone', 'timezone_id', 'timezone_id');
    }

    public function categories()
    {
        return $this->belongsToMany('Category', 'category_merchant', 'merchant_id', 'category_id');
    }

    public function geofence()
    {
        $prefix = DB::getTablePrefix();
        return $this->hasOne('MerchantGeofence', 'mall_id', 'merchant_id')
            ->addSelect(DB::raw("
                {$prefix}merchant_geofences.merchant_id,
                x(position) as latitude,
                y(position) as longitude
            "))
            ;
    }
}
