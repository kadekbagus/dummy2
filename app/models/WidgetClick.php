<?php
/**
 * Model for table `widget_clicks`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class WidgetClick extends Eloquent
{
    protected $primaryKey = 'widget_click_id';
    protected $table = 'widget_clicks';

    /**
     * Belongs to table campaign_popup_views
     */
    public function campaignGroupName()
    {
        return $this->belongsTo('CampaignGroupName', 'campaign_group_name_id', 'campaign_group_name_id');
    }
}