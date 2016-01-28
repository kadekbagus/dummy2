<?php
/**
 * Model for table `merchant_page_views`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class MerchantPageView extends Eloquent
{
    protected $primaryKey = 'merchant_page_view_id';
    protected $table = 'merchant_page_views';

    /**
     * Belongs to table campaign_popup_views
     */
    public function campaignGroupName()
    {
        return $this->belongsTo('CampaignGroupName', 'campaign_group_name_id', 'campaign_group_name_id');
    }

    /**
     * Belongs to table merchants (Mall object)
     */
    public function location()
    {
        return $this->belongsTo('Mall', 'location_id', 'merchant_id');
    }

    public function mall()
    {
        return $this->belongsTo('Mall', 'location_id', 'merchant_id');
    }

    /**
     * Belongs to table merchants (Tenant object)
     */
    public function tenant()
    {
        return $this->belongsTo('Tenant', 'merchant_id', 'merchant_id');
    }

    /**
     * Belongs to user
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Belongs to activity
     */
    public function activity()
    {
        return $this->belongsTo('Activity', 'activity_id', 'activity_id');
    }
}