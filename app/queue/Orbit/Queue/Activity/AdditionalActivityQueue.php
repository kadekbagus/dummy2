<?php namespace Orbit\Queue\Activity;
/**
 * Queue to save additional activity to particular tables.
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 * @author Rio AStamal <rio@dominopos.com>
 */
use Log;
use Activity;
use User;
use Config;
use Orbit\Helper\Util\JobBurier;
use Exception;
use CampaignPopupView;
use CampaignClicks;
use MerchantPageView;
use WidgetGroupName;
use WidgetClick;
use ConnectionTime;
use CampaignGroupName;
use Orbit\Helper\Util\FilterParser;
use Orbit\Helper\Util\CampaignSourceParser;
use ExtendedActivity;
use Merchant;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESActivityUpdateQueue;
use DB;
use Mall;

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
            $activity = Activity::findOnWriteConnection($activityId);

            if (! is_object($activity)) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Activity ID %s is not found.', $job->getJobId(), $activityId)
                ];
            }

            $this->saveToCampaignPopUpView($activity);
            $this->saveToCampaignPopUpClick($activity);
            $this->saveToMerchantPageView($activity);
            $this->saveToWidgetClick($activity);
            $this->saveToConnectionTime($activity);
            $extendedActivity = $this->saveExtendedData($activity);
            $this->saveToElasticSearch($activity, $extendedActivity);

            $job->delete();

            $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s; Extended Activity ID: %s',
                    $job->getJobId(),
                    $activity->activity_id,
                    $activity->activity_name_long,
                    $extendedActivity->extended_activity_id);

            // Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Activity ID: %s. Additional Activity Queue ERROR: %s', $job->getJobId(), $activityId, $e->getMessage());
            Log::error($message);

            // @Todo shold be moved to helper
            $exceptionNoLine = preg_replace('/\s+/', ' ', $e->getMessage());

            // Format -> JOB_ID;EXTENDED_ACTIVITY_ID;ACTIVITY_ID;MESSAGE
            $dataLogFailed = sprintf("%s;%s;%s\n", $job->getJobId(), $activityId, trim($exceptionNoLine));

            // Write the error log to dedicated file so it is easy to investigate and
            // easy to replay because the log is structured
            file_put_contents(storage_path() . '/logs/additional-activity-queue-error.log', $dataLogFailed, FILE_APPEND);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
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

    /**
     * Save to extended activity table
     *
     * @param Activity $activity
     * @return ExtendedActivity
     */
    protected function saveExtendedData($activity)
    {
        // Normal referer
        $referer = $this->data['referer'];
        $fullCurrentUrl = $this->data['current_url'];

        $urlForTracking = [$referer, $fullCurrentUrl];
        $campaignData = CampaignSourceParser::create()
                            ->setUrls($urlForTracking)
                            ->getCampaignSource();

        $filterData = FilterParser::create()
                            ->setUrls($urlForTracking)
                            ->getFilters();

        $extendedActivity = new ExtendedActivity();
        $extendedActivity->activity_id = $activity->activity_id;
        $extendedActivity->referrer = $referer;
        $extendedActivity->utm_source = $campaignData['campaign_source'];
        $extendedActivity->utm_medium = $campaignData['campaign_medium'];
        $extendedActivity->utm_term = $campaignData['campaign_term'];
        $extendedActivity->utm_content = $campaignData['campaign_content'];
        $extendedActivity->utm_campaign = $campaignData['campaign_name'];
        $extendedActivity->filter_country = $filterData['filter_country'];
        $extendedActivity->filter_cities = $filterData['filter_cities'];
        $extendedActivity->filter_keywords = $filterData['filter_keywords'];
        $extendedActivity->filter_categories = $filterData['filter_categories'];
        $extendedActivity->filter_partner = $filterData['filter_partner'];

        $this->reviewSubmitActivity($activity, $extendedActivity);
        $this->tenantIdActivity($activity, $extendedActivity);

        $this->notificationTokenActivity($activity, $extendedActivity);

        $extendedActivity->save();

        return $extendedActivity;
    }

    /**
     * Fill columns which related with review and rating
     *
     * @param Object $activity
     * @param Object $extendedActivity
     * @return void
     * @todo Should also check the object_id being commented so we can know whether this particular
     *       user already made review. It's to prevent possibility of activity spamming.
     */
    protected function reviewSubmitActivity($activity, $extendedActivity)
    {
        if (! $activity->activity_name === 'click_review_submit') {
            return FALSE;
        }

        // Parse the JSON in notes if applicable
        $jsonNotes = @json_decode($activity->notes);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return FALSE;
        }

        if (! is_object($jsonNotes)) {
            return FALSE;
        }

        $rating = 0;

        if (isset($jsonNotes->rating)) {
            $rating = (int)$jsonNotes->rating;
        }

        $extendedActivity->rating = $rating;
    }

    /**
     * Fill columns notification_token
     *
     * @param Object $actvitiy
     * @param Object $extendedActivity
     * @return void
     */
    protected function notificationTokenActivity($activity, $extendedActivity)
    {
        if (! isset($this->data['notification_token'])) {
            return;
        }

        if (empty($this->data['notification_token'])) {
            return;
        }

        $notificationToken = $this->data['notification_token'];

        // This is tenant object so get the parent
        $extendedActivity->notification_token = $notificationToken;
    }

    /**
     * Fill columns related with store or mall id
     *
     * @param Object $actvitiy
     * @param Object $extendedActivity
     * @return void
     */
    protected function tenantIdActivity($activity, $extendedActivity)
    {
        if (! isset($this->data['merchant_id'])) {
            return;
        }

        if (empty($this->data['merchant_id'])) {
            return;
        }

        $merchantId = $this->data['merchant_id'];
        $merchant = DB::table('merchants')
                        ->where('status', '!=', 'deleted')
                        ->where('merchant_id', $merchantId)
                        ->first();

        if (! is_object($merchant)) {
            return;
        }

        // Determine the type of merchant, if it is tenant we need to lookup
        // the parent to get the mall
        if ($merchant->object_type === 'mall') {
            $extendedActivity->mall_id = $merchantId;
            $extendedActivity->mall_name = $merchant->name;

            return;
        }

        if ($merchant->object_type !== 'tenant') {
            return;
        }

        // This is tenant object so get the parent
        $extendedActivity->tenant_id = $merchantId;
        $extendedActivity->tenant_name = $merchant->name;

        // Get parent (mall) data
        $mall = Mall::excludeDeleted()->find($merchant->parent_id);
        if (! is_object($mall)) {
            return;
        }

        $extendedActivity->mall_id = $mall->merchant_id;
        $extendedActivity->mall_name = $mall->name;
    }

    /**
     * Create new document in elasticsearch.
     *
     * @param Activity $activity
     * @param ExtendedActicity $extendedActivity
     * @return void
     */
    protected function saveToElasticSearch($activity, $extendedActivity)
    {
        if ($activity->group !== 'mobile-ci') {
            return;
        }

        // queue for create/update activity document in elasticsearch
        $job = new FakeJob();

        $esActivityQueue = new ESActivityUpdateQueue();
        $esActivityQueue->fire($job, [
            'activity_id' => $activity->activity_id,
            'referer' => $this->data['referer'],
            'orbit_referer' => $this->data['orbit_referer'],
            'current_url' => $this->data['current_url'],
            'extended_activity_id' => $extendedActivity->extended_activity_id
        ]);
    }
}