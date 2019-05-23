<?php namespace Orbit\Queue\Notification;
/**
 * Process queue for sending user store notification
 * Fired from UserNotificationStoreCommand artisan command
 *
 */
use Config;
use DB;
use Log;
use Queue;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use stdClass;
use Media;
use News;
use Coupon;
use Orbit\Helper\OneSignal\OneSignal;
use Orbit\Helper\Util\CdnUrlGenerator;
use Carbon\Carbon as Carbon;

class UserStoreNotificationQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     * @param array $data [
     *    'object_id' => campaign_id
     *    'object_type' => campaign type
     *    'mongo_id' => mongo id
     * ]
     */
    public function fire($job, $data)
    {
        $timezone = 'Asia/Jakarta'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();
        $dateTimeNow = $date->setTimezone($timezone)->toDateTimeString();

        $prefix = DB::getTablePrefix();

        $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);

        $objectId = $data['object_id'];
        $objectType = $data['object_type'];
        $mongoId = $data['mongo_id'];

        try {

            if ($objectType === 'event' || $objectType === 'promotion') {
                $campaign = News::join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                 ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                                 // Get media with defaul lang
                                 ->leftJoin('news_translations', function($q){
                                        $q->on('news_translations.news_id', '=', 'news.news_id')
                                          ->on('news_translations.merchant_language_id', '=', DB::raw('default_languages.language_id'))
                                        ;
                                    })
                                 ->leftJoin('media', function($r){
                                        $r->on('media.object_id', '=', 'news_translations.news_translation_id')
                                          ->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                                    })
                                 ->where('news.news_id', '=', $objectId);
            } else if ($objectType === 'coupon') {
                $campaign = Coupon::join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                                    ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                                     // Get media with defaul lang
                                     ->leftJoin('coupon_translations', function($q){
                                            $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                              ->on('coupon_translations.merchant_language_id', '=', DB::raw('default_languages.language_id'))
                                            ;
                                        })
                                     ->leftJoin('media', function($r){
                                            $r->on('media.object_id', '=', 'coupon_translations.coupon_translation_id')
                                              ->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                                        })
                                    ->where('promotions.promotion_id', '=', $objectId);
            }


            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $campaign = $campaign->select(
                            DB::raw("{$image} as image_url"),
                            DB::raw('default_languages.language_id as default_language_id'),
                            DB::raw('default_languages.name as default_language_name')
                        )->first();

            $defaultLangName = 'id';
            $imageUrl = '';

            if (empty($campaign)) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] User Store Notification Campaign ID %s, Object Type %s is not found .', $job->getJobId(), $objectId, $objectType)
                ];
            }

            $defaultLangName = $campaign->default_language_name;
            $imageUrl = $campaign->image_url;


            // Get image url
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            // get single mongo data
            $storeObjectNotification = $mongoClient->setEndPoint('store-object-notifications/' . $mongoId)
                                                    ->request('GET')
                                                    ->data;

            // send to onesignal
            if (! empty($storeObjectNotification->notification->notification_tokens)) {
                $mongoNotifId = $storeObjectNotification->notification->_id;
                $launchUrl = $storeObjectNotification->notification->launch_url;
                $headings = $storeObjectNotification->notification->headings;
                $contents = $storeObjectNotification->notification->contents;
                $notificationTokens = $storeObjectNotification->notification->notification_tokens;

                // add query string for activity recording
                $newUrl =  $launchUrl . '&notif_id=' . $mongoNotifId;

                // english is mandatory in onesignal, set en value with default language content
                if (empty($headings->en)) {
                    $headings->en = $headings->$defaultLangName;
                }

                if (empty($contents->en)) {
                    $contents->en = $contents->$defaultLangName;
                }

                // Slice token where token up to 1500
                if (count($notificationTokens) > 1500) {
                    $newToken = array();
                    $stopLoop = false;
                    $startLoop = 0;
                    $oneSignalId = array();
                    while ($stopLoop == false) {
                        $newToken = array_slice($notificationTokens , $startLoop, 1500);

                        if (empty($newToken)) {
                            $stopLoop =  true;
                            break;
                        }

                        $data = [
                            'headings'           => $headings,
                            'contents'           => $contents,
                            'url'                => $newUrl,
                            'include_player_ids' => $newToken,
                            'ios_attachments'    => $imageUrl,
                            'big_picture'        => $imageUrl,
                            'adm_big_picture'    => $imageUrl,
                            'chrome_big_picture' => $imageUrl,
                            'chrome_web_image'   => $imageUrl,
                            'web_push_topic'     => 'auto-store',
                        ];

                        $oneSignal = new OneSignal($oneSignalConfig);
                        $newNotif = $oneSignal->notifications->add($data);
                        $oneSignalId[] = $newNotif->id;

                        $startLoop = $startLoop + 1500;
                    }
                    $bodyUpdate['vendor_notification_id'] = $oneSignalId;
                } else {
                    $data = [
                        'headings'           => $headings,
                        'contents'           => $contents,
                        'url'                => $newUrl,
                        'include_player_ids' => $notificationTokens,
                        'ios_attachments'    => $imageUrl,
                        'big_picture'        => $imageUrl,
                        'adm_big_picture'    => $imageUrl,
                        'chrome_big_picture' => $imageUrl,
                        'chrome_web_image'   => $imageUrl,
                    ];

                    $oneSignal = new OneSignal($oneSignalConfig);
                    $newNotif = $oneSignal->notifications->add($data);
                    $bodyUpdate['vendor_notification_id'] = $newNotif->id;
                }

                // Update status in notification collection from pending to sent
                $bodyUpdate['sent_at'] = $dateTime;
                $bodyUpdate['_id'] = $mongoNotifId;
                $bodyUpdate['status'] = 'sent';

                $responseUpdate = $mongoClient->setFormParam($bodyUpdate)
                                            ->setEndPoint('notifications') // express endpoint
                                            ->request('PUT');
            }

            // send as inApps notification
            if (! empty($storeObjectNotification->notification->user_ids)) {
                $bodyInApps = [
                    'user_ids'       => $storeObjectNotification->notification->user_ids,
                    'token'         => null,
                    'notifications' => $storeObjectNotification->notification,
                    'send_status'   => 'sent',
                    'is_viewed'     => false,
                    'is_read'       => false,
                    'created_at'    => $dateTime,
                    'image_url'     => $imageUrl
                ];

                $inApps = $mongoClient->setFormParam($bodyInApps)
                    ->setEndPoint('user-notifications') // express endpoint
                    ->request('POST');
/*  
                foreach ($storeObjectNotification->notification->user_ids as $userId) {
                    $bodyInApps = [
                        'user_id'       => $userId,
                        'token'         => null,
                        'notifications' => $storeObjectNotification->notification,
                        'send_status'   => 'sent',
                        'is_viewed'     => false,
                        'is_read'       => false,
                        'created_at'    => $dateTime,
                        'image_url'     => $imageUrl
                    ];

                    $inApps = $mongoClient->setFormParam($bodyInApps)
                                ->setEndPoint('user-notifications') // express endpoint
                                ->request('POST');
                }
*/
            }

            // Update status in store-object-notifications collection from pending to sent
            if (! empty($responseUpdate)) {
                $storeBodyUpdate['notification'] = $responseUpdate->data;
            }
            $storeBodyUpdate['_id'] = $storeObjectNotification->_id;
            $storeBodyUpdate['status'] = 'sent';

            $responseStoreUpdate = $mongoClient->setFormParam($storeBodyUpdate)
                                        ->setEndPoint('store-object-notifications') // express endpoint
                                        ->request('PUT');


            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] User Store Notification; Status: OK; Object ID: %s; Object Type: %s; Total Token: %s ',
                                $job->getJobId(),
                                $objectId,
                                $objectType,
                                count($notificationTokens)
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
                'message' => sprintf('[Job ID: `%s`] User Store Notification; Status: FAIL; Object ID: %s; Object Type: %s; Total Token: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $objectId,
                                $objectType,
                                count($notificationTokens),
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}