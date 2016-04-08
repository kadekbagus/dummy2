<?php

class CampaignPrice extends Eloquent
{
	/**
     * CampaignPrice Model
     *
     * @author Shelgi <shelgi@dominopos.com>
     */
	
    protected $table = 'campaign_price';

    protected $primaryKey = 'campaign_price_id';

    public function news()
    {
        return $this->belongsTo('News', 'campaign_id', 'news_id');
    }

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'campaign_id', 'promotion_id');
    }

    public function promotion()
    {
        return $this->belongsTo('News', 'campaign_id', 'news_id');
    }
}