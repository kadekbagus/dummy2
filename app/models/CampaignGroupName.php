<?php
/**
 * Model for table `campaing_group_names`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class CampaignGroupName extends Eloquent
{
    protected $primaryKey = 'campaign_group_name_id';
    protected $table = 'campaign_group_names';

    /**
     * Has many for table campaign_popup_views
     */
    public function campaignPopupViews()
    {
        return $this->hasMany('CampaignPopupView', 'campaign_group_name_id', 'campaign_group_name_id');
    }

    /**
     * Has many for table campaign_page_views
     */
    public function campaignPageViews()
    {
        return $this->hasMany('CampaignPageView', 'campaign_group_name_id', 'campaign_group_name_id');
    }

    /**
     * Has many for table campaign_clicks
     */
    public function campaignClicks()
    {
        return $this->hasMany('CampaignClick', 'campaign_group_name_id', 'campaign_group_name_id');
    }

    /**
     * Get the statistic pop up view for each group campaign and for particular location and period.
     *
     * @param string $locationId - Mall or Retailer ID
     * @param string $beginDate
     * @param strign $endDate
     * @return CampaignPopupView
     */
    public static function getPopupViewByLocation($locationId, $beginDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $campaigns = static::select('campaign_group_names.campaign_group_name', DB::raw('IFNULL(tmp.count, 0) count'))
                ->leftJoin(

                // Table
                DB::raw("(select {$prefix}campaign_popup_views.campaign_group_name_id, count({$prefix}campaign_popup_views.campaign_popup_view_id) as count
                        from {$prefix}campaign_popup_views
                        where {$prefix}campaign_popup_views.location_id = :loc and
                        {$prefix}campaign_popup_views.created_at between :begin and :end
                        group by {$prefix}campaign_popup_views.campaign_group_name_id) tmp"),

                // Col 1
                DB::raw('tmp.campaign_group_name_id'),

                // Operator
                '=',

                // Col 2
                'campaign_group_names.campaign_group_name_id'
        )->setBindings(['loc' => $locationId, 'begin' => $beginDate, 'end' => $endDate]);

        return $campaigns;
    }

    /**
     * Get the statistic pop up view for each group campaign and for particular location and period.
     *
     * @param string $locationId - Mall or Retailer ID
     * @param string $beginDate
     * @param strign $endDate
     * @return CampaignPopupView
     */
    public static function getPageViewByLocation($locationId, $beginDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $campaigns = static::select('campaign_group_names.campaign_group_name', DB::raw('IFNULL(tmp.count, 0) count'))
                ->leftJoin(

                // Table
                DB::raw("(select {$prefix}campaign_page_views.campaign_group_name_id, count({$prefix}campaign_page_views.campaign_page_view_id) as count
                        from {$prefix}campaign_page_views
                        where {$prefix}campaign_page_views.location_id = :loc and
                        {$prefix}campaign_page_views.created_at between :begin and :end
                        group by {$prefix}campaign_page_views.campaign_group_name_id) tmp"),

                // Col 1
                DB::raw('tmp.campaign_group_name_id'),

                // Operator
                '=',

                // Col 2
                'campaign_group_names.campaign_group_name_id'
        )->setBindings(['loc' => $locationId, 'begin' => $beginDate, 'end' => $endDate]);

        return $campaigns;
    }
}