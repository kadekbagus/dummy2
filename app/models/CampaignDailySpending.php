<?php

class CampaignDailySpending extends Eloquent
{
    /**
     * UserSignin Model
     *
     * @author shelgi <shelgi@dominopos.com>
     */

    protected $table = 'campaign_daily_spendings';

    protected $primaryKey = 'campaign_daily_spending_id';

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