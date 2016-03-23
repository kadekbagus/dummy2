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

    protected $primaryKey = 'merchant_id';

    protected $table = 'merchants';

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
}
