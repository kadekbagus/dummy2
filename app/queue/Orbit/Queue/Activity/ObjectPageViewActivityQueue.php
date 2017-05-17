<?php namespace Orbit\Queue\Activity;
/**
 * Queue to save activity view detail page to particular tables.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use Log;
use Activity;
use User;
use Config;
use Orbit\Helper\Util\JobBurier;
use ObjectPageView;
use TotalObjectPageView;
use Exception;
use Tenant;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESCouponUpdateQueue;
use Orbit\Queue\Elasticsearch\ESPromotionUpdateQueue;
use Orbit\Queue\Elasticsearch\ESNewsUpdateQueue;
use Orbit\Queue\Elasticsearch\ESMallUpdateQueue;
use Orbit\Queue\Elasticsearch\ESStoreUpdateQueue;

class ObjectPageViewActivityQueue
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

            // Save also the activity to particular `campaign_xyz` table
            switch ($activity->activity_name) {
                case 'view_mall':
                case 'view_landing_page_store_detail':
                case 'view_landing_page_news_detail':
                case 'view_landing_page_promotion_detail':
                case 'view_landing_page_coupon_detail':
                case 'view_mall_store_detail':
                case 'view_mall_event_detail':
                case 'view_mall_promotion_detail':
                case 'view_mall_coupon_detail':
                    // insert to object_page_views
                    $object_page_view = new ObjectPageView();
                    $object_page_view->object_type = strtolower($activity->object_name);
                    $object_page_view->object_id = $activity->object_id;
                    $object_page_view->user_id = $activity->user_id;
                    $object_page_view->location_id = $activity->location_id;
                    $object_page_view->activity_id = $activity->activity_id;
                    $object_page_view->save();

                    $total_object_page_view = TotalObjectPageView::where('object_type', strtolower($activity->object_name))
                                                ->where('object_id', $activity->object_id)
                                                ->where('location_id', $activity->location_id)
                                                ->lockForUpdate()
                                                ->first();

                    if (! is_object($total_object_page_view)) {
                        $total_object_page_view = new TotalObjectPageView();
                        $total_object_page_view->total_view = 0;
                    }

                    $total_object_page_view->object_type = strtolower($activity->object_name);
                    $total_object_page_view->object_id = $activity->object_id;
                    $total_object_page_view->location_id = $activity->location_id;
                    $total_object_page_view->total_view = $total_object_page_view->total_view + 1;
                    $total_object_page_view->save();

                    // update elastic search
                    $fakeJob = new FakeJob();
                    if ($activity->object_name === 'Coupon') {
                        $data = [
                            'coupon_id' => $activity->object_id
                        ];

                        $esQueue = new ESCouponUpdateQueue();
                    } elseif ($activity->object_name === 'Promotion'){
                        $data = [
                            'news_id' => $activity->object_id
                        ];

                        $esQueue = new ESPromotionUpdateQueue();
                    } elseif ($activity->object_name === 'News'){
                        $data = [
                            'news_id' => $activity->object_id
                        ];

                        $esQueue = new ESNewsUpdateQueue();
                    } elseif ($activity->object_name === 'Mall'){
                        $data = [
                            'mall_id' => $activity->object_id
                        ];

                        $esQueue = new ESMallUpdateQueue();
                    } elseif ($activity->object_name === 'Tenant'){
                        $store = Tenant::excludeDeleted()
                            ->where('merchant_id', $activity->object_id)
                            ->first();

                        if (! empty($store)) {
                            $data = [
                                'name'    => $store->name,
                                'country' => $store->country
                            ];

                            $esQueue = new ESStoreUpdateQueue();
                        }
                    }

                    $response = $esQueue->fire($fakeJob, $data);
                    break;
            }

            $fakeJob->delete();

            $message = sprintf('[Job ID: `%s`] Object Page View Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $fakeJob->getJobId(),
                    $activity->activity_id,
                    $activity->activity_name_long);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            Log::error(sprintf('[Job ID: `%s`] Object Page View Activity Queue ERROR: %s', $activityId, $e->getMessage()));
            return [
                'status' => 'fail',
                'message' => $e->getMessage()
            ];
        }
    }
}