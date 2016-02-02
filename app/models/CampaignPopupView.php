<?php
/**
 * Model for table `campaing_popup_views`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class CampaignPopupView extends Eloquent
{
    protected $primaryKey = 'campaign_popup_view_id';
    protected $table = 'campaign_popup_views';

    /**
     * Belongs to table campaign_popup_views
     */
    public function campaignGroupName()
    {
        return $this->belongsTo('CampaignGroupName', 'campaign_group_name_id', 'campaign_group_name_id');
    }
}