<?php
/**
 * Model for table `campaing_page_views`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class CampaignPageView extends Eloquent
{
    protected $primaryKey = 'campaign_page_view_id';
    protected $table = 'campaign_page_views';

    /**
     * Belongs to table campaign_popup_views
     */
    public function campaignGroupName()
    {
        return $this->belongsTo('CampaignGroupName', 'campaign_group_name_id', 'campaign_group_name_id');
    }

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