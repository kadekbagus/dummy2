<?php
/**
 * Model for table `campaign_clicks`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class CampaignClicks extends Eloquent
{
    protected $primaryKey = 'campaing_popup_views';
    protected $table = 'campaign_popup_view_id';

    /**
     * Belongs to table campaign_popup_views
     */
    public function campaignGroupName()
    {
        return $this->belongsTo('CampaignGroupName', 'campaign_group_name_id', 'campaign_group_name_id');
    }
}