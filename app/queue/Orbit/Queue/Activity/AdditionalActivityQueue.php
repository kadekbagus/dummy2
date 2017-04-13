<?php namespace Orbit\Queue\Activity;
/**
 * Queue to save additional activity to particular tables.
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
use Log;
use Activity;
use User;
use Config;
use Orbit\Helper\Util\JobBurier;
use CampaignPageView;
use Exception;
use CampaignPopupView;
use CampaignClicks;
use MerchantPageView;
use WidgetGroupName;
use WidgetClick;
use ConnectionTime;
use CampaignGroupName;

class AdditionalActivityQueue
{
    /**
     * Data used accross the class
     *
     * @var array
     */
    protected $data = [];

    /**
     * Laravel main method to fire a job on a queue.
     *
     * @param Job $job
     * @param array $data [
     *                      activity_id => NUM
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        try {
            $this->data = $data;
            $activityId = $data['activity_id'];
            $activity = Activity::excludeDeleted()
                        ->where('activity_id', $activityId)
                        ->where('group', 'mobile-ci')
                        ->first();

            if (! is_object($activity)) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Activity ID %s is not found.', $job->getJobId(), $activityId)
                ];
            }

            $this->saveToCampaignPageViews($activity);
            $this->saveToCampaignPopUpView($activity);
            $this->saveToCampaignPopUpClick($activity);
            $this->saveToMerchantPageView($activity);
            $this->saveToWidgetClick($activity);
            $this->saveToConnectionTime($activity);

            $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $job->getJobId(),
                    $activity->activity_id,
                    $activity->activity_name_long);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            Log::error(sprintf('[Job ID: `%s`] Additional Activity Queue ERROR: %s', $activityId, $e->getMessage()));
            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Save to merchant_page_views table
     *
     * @param Activity $activity Object of Activity
     * @return void | NULL
     */
    protected function saveToCampaignPageViews(Activity $activity)
    {
        if (empty($activity->object_id)) {
            return;
        }
        // Save also the activity to particular `campaign_xyz` table
        switch ($activity->activity_name) {
            case 'view_promotion':
            case 'view_coupon':
            case 'view_lucky_draw':
            case 'view_event':
            case 'view_news':
            case 'view_landing_page_coupon_detail':
            case 'view_landing_page_news_detail':
            case 'view_landing_page_promotion_detail':
            case 'view_mall_event_detail':
            case 'view_mall_promotion_detail':
            case 'view_mall_coupon_detail':
                $campaign = new CampaignPageView();
                $campaign->campaign_id = $activity->object_id;
                $campaign->user_id = $activity->user_id;
                $campaign->location_id = ! empty($activity->location_id) ? $activity->location_id : 0;
                $campaign->activity_id = $activity->activity_id;
                $campaign->campaign_group_name_id = $this->campaignGroupNameIdFromActivityName($activity->activity_name);
                $campaign->save();
                break;
        }
    }

    /**
     * Save to campaign_popup_views table
     *
     * @param Activity $activity Object of Activity
     * @return void | NULL
     */
    protected function saveToCampaignPopUpView(Activity $activity)
    {
        $activity_name_long_array = array(
            'View Coupon Pop Up'       => 'View Coupon Pop Up',
            'View Promotion Pop Up'    => 'View Promotion Pop Up',
            'View News Pop Up'         => 'View News Pop Up'
        );

        $proceed = in_array($activity->activity_name_long, $activity_name_long_array);
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $popupview = new CampaignPopupView();
        $popupview->campaign_id = $activity->object_id;
        $popupview->user_id = $activity->user_id;
        $popupview->location_id = $activity->location_id;
        $popupview->activity_id = $activity->activity_id;
        $popupview->campaign_group_name_id = $this->campaignGroupNameIdFromActivityName($activity->activity_name);
        $popupview->save();
    }

    /**
     * Save to campaign_popup_views table
     *
     * @param Activity $activity Object of Activity
     * @return void | NULL
     */
    protected function saveToCampaignPopUpClick(Activity $activity)
    {
        $activity_name_long_array = array(
            'Click Coupon Pop Up'          => 'Click Coupon Pop Up',
            'Click Promotion Pop Up'       => 'Click Promotion Pop Up',
            'Click News Pop Up'            => 'Click News Pop Up',
            'Click mall featured carousel' => 'Click mall featured carousel',
        );

        $proceed = in_array($activity->activity_name_long, $activity_name_long_array);
        if (! $proceed) {
            return;
        }

        $location_id = $activity->location_id;
        if ($activity->activity_name === 'click_mall_featured_carousel') {
            $location_id = 0;
        }

        // Save also the activity to particular `campaign_xyz` table
        $popupview = new CampaignClicks();
        $popupview->campaign_id = $activity->object_id;
        $popupview->user_id = $activity->user_id;
        $popupview->location_id = $location_id;
        $popupview->activity_id = $activity->activity_id;
        $popupview->campaign_group_name_id = $this->campaignGroupNameIdFromActivityName($activity->activity_name);
        $popupview->save();
    }

    /**
     * Save to merchant_page_views table
     *
     * @param Activity $activity Object of Activity
     * @return void | NULL
     */
    protected function saveToMerchantPageView(Activity $activity)
    {
        $proceed = $activity->activity_name === 'view_retailer' && $activity->activity_name_long == 'View Tenant Detail';
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $pageview = new MerchantPageView();
        $pageview->merchant_id = $activity->object_id;
        $pageview->merchant_type = strtolower($activity->object_name);
        $pageview->user_id = $activity->user_id;
        $pageview->location_id = $activity->location_id;
        $pageview->activity_id = $activity->activity_id;
        $pageview->save();
    }

    /**
     * Save to `widget_clicks` table
     *
     * @param Activity $activity Object of Activity
     * @return void | NULL
     */
    protected function saveToWidgetClick(Activity $activity)
    {
        if ($activity->activity_name !== 'widget_click') {
            return;
        }

        $click = new WidgetClick();
        $click->widget_id = $activity->object_id;
        $click->user_id = $activity->user_id;
        $click->location_id = $activity->location_id;
        $click->activity_id = $activity->activity_id;

        $groupName = 'Unknown';
        switch ($activity->activity_name_long) {
            case 'Widget Click Promotion':
                $groupName = 'Promotion';
                break;

            case 'Widget Click News':
                $groupName = 'News';
                break;

            case 'Widget Click Tenant':
                $groupName = 'Tenant';
                break;

            case 'Widget Click Service':
                $groupName = 'Service';
                break;

            case 'Widget Click Coupon':
                $groupName = 'Coupon';
                break;

            case 'Widget Click Lucky Draw':
                $groupName = 'Lucky Draw';
                break;

            case 'Widget Click Free Wifi':
                $groupName = 'Free Wifi';
                break;
        }

        $object = WidgetGroupName::get()->keyBy('widget_group_name')->get($groupName);
        $click->widget_group_name_id = is_object($object) ? $object->widget_group_name_id : '0';

        $return = $click->save();
    }

    /**
     * Save to `connection_times` table. Only succesful operation (no failed response) recorded.
     *
     * @param Activity $activity Object of Activity
     * @return void | NULL
     */
    protected function saveToConnectionTime(Activity $activity)
    {
        $proceed = ($activity->activity_name === 'login_ok' || $activity->activity_name === 'logout_ok') && $activity->session_id;
        if (! $proceed) {
            return;
        }

        // Save also the activity to particular `campaign_xyz` table
        $connection = ConnectionTime::where('session_id', $activity->session_id)->where('location_id', $activity->location_id)->first();
        if (! is_object($connection)) {
            $connection = new ConnectionTime();
        }

        $connection->session_id = $activity->session_id;
        $connection->user_id = $activity->user_id;
        $connection->location_id = $activity->location_id;

        $now = $this->data['datetime'];
        if ($activity->activity_name === 'login_ok') {
            $connection->login_at = $now;
            $connection->logout_at = NULL;
        }
        if ($activity->activity_name === 'logout_ok') {
            $connection->logout_at = $now;
        }

        $connection->save();
    }

    /**
     * Used to get the campaign group name id.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $activityName
     * @return string
     */
    protected function campaignGroupNameIdFromActivityName($activityName)
    {
        $groupName = 'Unknown';

        switch ($activityName) {
            case 'view_promotion':
                $groupName = 'Promotion';
                break;

            case 'view_coupon':
                $groupName = 'Coupon';
                break;

            case 'view_lucky_draw':
                $groupName = 'Lucky Draw';
                break;

            case 'view_event':
                $groupName = 'Event';
                break;

            case 'view_news':
                $groupName = 'News';
                break;

            case 'view_promotion_popup':
                $groupName = 'Promotion';
                break;

            case 'view_coupon_popup':
                $groupName = 'Coupon';
                break;

            case 'view_news_popup':
                $groupName = 'News';
                break;

            case 'click_promotion_popup':
                $groupName = 'Promotion';
                break;

            case 'click_coupon_popup':
                $groupName = 'Coupon';
                break;

            case 'click_news_popup':
                $groupName = 'News';
                break;

            case 'view_landing_page_coupon_detail':
                $groupName = 'Coupon';
                break;

            case 'view_landing_page_news_detail':
                $groupName = 'News';
                break;

            case 'view_landing_page_promotion_detail':
                $groupName = 'Promotion';
                break;

            case 'view_mall_event_detail':
                $groupName = 'News';
                break;

            case 'view_mall_promotion_detail':
                $groupName = 'Promotion';
                break;

            case 'view_mall_coupon_detail':
                $groupName = 'Coupon';
                break;

            case 'click_mall_featured_carousel':
                if ($this->module_name == 'News') {
                    $groupName = 'News';
                } elseif ($this->module_name == 'Promotion') {
                    $groupName = 'Promotion';
                } elseif ($this->module_name == 'Coupon') {
                    $groupName = 'Coupon';
                }
                break;
        }

        $object = CampaignGroupName::get()->keyBy('campaign_group_name')->get($groupName);

        return is_object($object) ? $object->campaign_group_name_id : '0';
    }
}