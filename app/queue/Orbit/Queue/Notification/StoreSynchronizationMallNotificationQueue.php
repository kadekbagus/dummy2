<?php namespace Orbit\Queue\Notification;
/**
 * Process queue for sent news / promotion mall notification
 *
 */
use Config;
use DB;
use Media;
use Log;
use Queue;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Tenant;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use stdClass;

class StoreSynchronizationMallNotificationQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     * @param array $data [
     *    'news_id' => The object Id in the media
     * ]
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $baseStoreId = $data['base_store_id'];

        $store = Tenant::where('merchant_id', $baseStoreId)->where('status', 'active')->first();

        if (empty($store)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Store Synch Mall Notification News ID %s is not found or inactive .', $job->getJobId(), $baseStoreId)
            ];
        }


        try {

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();
            $follower = null;
            $tokens = null;
            $userIds = null;
            $headings = null;
            $contents = null;
            $attachmentPath = null;
            $attachmentRealPath = null;
            $cdnUrl = null;
            $cdnBucketName = null;

            // get user_ids
            $userFollowSearch = ['object_id' => $store->merchant_id, 'object_type' => 'mall'];
            $userFollow = $mongoClient->setQueryString($userFollowSearch)
                                      ->setEndPoint('user-follows')
                                      ->request('GET');

            // if there is follower
            if (count($userFollow->data->records) !== 0)
            {
                foreach ($userFollow->data->records as $key => $value) {
                    $follower[] = $value->user_id;
                }
                $userIds = array_values(array_unique($follower));

                // get tokens
                $tokenSearch = ['user_ids' => json_encode($userIds), 'notification_provider' => 'onesignal'];
                $tokenData = $mongoClient->setQueryString($tokenSearch)
                                         ->setEndPoint('user-notification-tokens')
                                         ->request('GET');

                if ($tokenData->data->total_records > 0) {
                    foreach ($tokenData->data->records as $key => $value) {
                        $tokens[] = $value->notification_token;
                    }
                    $tokens = array_values(array_unique($tokens));
                }

                $launchUrl = LandingPageUrlGenerator::create('store', $baseStoreId, $store->name)->generateUrl();

                $dataNotification = [
                    'title' => $store->name,
                    'launch_url' => $launchUrl,
                    'attachment_path' => $attachmentPath,
                    'attachment_realpath' => $attachmentRealPath,
                    'cdn_url' => $cdnUrl,
                    'cdn_bucket_name' => $cdnBucketName,
                    'default_language' => null,
                    'headings' => $headings,
                    'contents' => $contents,
                    'type' => 'store',
                    'status' => 'pending',
                    'sent_at' => null,
                    'notification_tokens' => json_encode($tokens),
                    'user_ids' => json_encode($userIds),
                    'vendor_notification_id' => null,
                    'vendor_type' => 'onesignal',
                    'is_automatic' => true,
                    'mime_type' => 'image/jpeg',
                    'target_audience_ids' => null,
                    'created_at' => $dateTime
                ];

                // check notification exist or not
                $dataNotificationSearch = ['title' => $store->name, 'launch_url' => $launchUrl, 'type' => 'store'];
                $notificationSearch = $mongoClient->setQueryString($dataNotificationSearch)
                                                  ->setEndPoint('notifications')
                                                  ->request('GET');

                if (count($notificationSearch->data->records) === 0)
                {
                    $notification = $mongoClient->setFormParam($dataNotification)
                                                ->setEndPoint('notifications')
                                                ->request('POST');

                    $notificationId = $notification->data->_id;

                    // search mall object notification
                    $dataMallObjectNotificationSearch = ['mall_id' => $store->merchant_id, 'status' => 'pending'];
                    $mallObjectNotificationSearch = $mongoClient->setQueryString($dataMallObjectNotificationSearch)
                                                                ->setEndPoint('mall-object-notifications')
                                                                ->request('GET');

                    if (count($mallObjectNotificationSearch->data->records) === 0)
                    {
                        // insert data if not exist
                        $insertMallObjectNotification = [
                            'notification_ids' => (array)$notificationId,
                            'mall_id' => $store->merchant_id,
                            'user_ids' => $userIds,
                            'tokens' => $tokens,
                            'status' => 'pending',
                            'start_at' => null,
                            'created_at' => $dateTime
                        ];

                        $mallObjectNotification = $mongoClient->setFormParam($insertMallObjectNotification)
                                                              ->setEndPoint('mall-object-notifications')
                                                              ->request('POST');
                    } else {
                        $_tokens = null;
                        $_userIds = null;
                        $_notificationIds = (array) $mallObjectNotificationSearch->data->records[0]->notification_ids;
                        $_userIds = (array) $mallObjectNotificationSearch->data->records[0]->user_ids;
                        $_tokens = (array) $mallObjectNotificationSearch->data->records[0]->tokens;
                        $_notificationIds[] = $notificationId;
                        if (!empty($userIds)) {
                           foreach ($userIds as $key => $uservalue) {
                               $_userIds[] = $uservalue;
                           }
                           $_userIds = array_values(array_unique($_userIds));
                        }
                        if (!empty($tokens)) {
                           foreach ($tokens as $key => $tokenvalue) {
                               $_tokens[] = $tokenvalue;
                           }
                           $_tokens = array_values(array_unique($_tokens));
                        }
                        $updateMallObjectNotification = [
                           '_id' => $mallObjectNotificationSearch->data->records[0]->_id,
                           'notification_ids' => array_values(array_unique($_notificationIds)),
                           'mall_id' => $store->merchant_id,
                           'user_ids' => json_encode($_userIds),
                           'tokens' => json_encode($_tokens),
                        ];

                        $mallObjectNotification = $mongoClient->setFormParam($updateMallObjectNotification)
                                                             ->setEndPoint('mall-object-notifications')
                                                             ->request('PUT');
                    }
                }
            }


            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Store Synch Mall Notification; Status: OK; News ID: %s; Total Token: %s ',
                                $job->getJobId(),
                                $baseStoreId,
                                count($tokens)
                            )
            ];


        } catch (Exception $e) {
            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Store Synch Mall Notification; Status: FAIL; News ID: %s; Total Token: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $baseStoreId,
                                count($tokens),
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}