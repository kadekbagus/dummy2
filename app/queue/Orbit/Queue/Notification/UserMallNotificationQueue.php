<?php namespace Orbit\Queue\Notification;
/**
 * Process queue for sent user mall notification per record mongo db
 *
 */
use Config;
use DB;
use Log;
use Queue;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Mall;
use stdClass;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Orbit\Helper\OneSignal\OneSignal;
use Orbit\Helper\Util\CdnUrlGenerator;


class UserMallNotificationQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     * @param array $data [
     *    'mall_id' => mall ID
     * ]
     */
    public function fire($job, $data)
    {
        $timezone = 'Asia/Jakarta'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();
        $dateTimeNow = $date->setTimezone($timezone)->toDateTimeString();

        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

        $mallId = $data['mall_id'];
        $mallObjectNotificationId = $data['mongo_id'];

        $headings = null;
        $contents = null;
        $newUrl = null;
        $imageUrl = null;
        $mongoNotifId = null;
        $attachmentPath = null;
        $cdnUrl = null;
        $user_ids = null;
        $userIds = null;
        $notification_token = null;
        $notificationTokens = null;
        $vendorNotificationId = null;

        $mall = Mall::excludeDeleted('merchants')
                    ->leftJoin('media', 'media.object_id', '=', 'merchants.merchant_id')
                    ->where('media.media_name_long', '=', 'mall_logo_orig')
                    ->where('merchant_id', '=', $mallId)
                    ->first();

        if (empty($mall)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] User Mall Notification Mall ID %s is not found or inactive .', $job->getJobId(), $mallId)
            ];
        }

        try {

            // Get the user id
            $userFollowSearch = ['object_id'   => $mallId, 'object_type' => 'mall'];
            $userFollow = $mongoClient->setQueryString($userFollowSearch)
                                      ->setEndPoint('user-follows')
                                      ->request('GET');

            if (count($userFollow->data->records) > 0) {
                foreach ($userFollow->data->records as $key => $value) {
                    $user_ids[] = $value->user_id;
                }
                $userIds = array_values(array_unique($user_ids));
            }

            // Get notification tokens
            $tokenSearch = ['user_ids' => json_encode($userIds), 'notification_provider' => 'onesignal'];
            $tokenData = $mongoClient->setQueryString($tokenSearch)
                                     ->setEndPoint('user-notification-tokens')
                                     ->request('GET');

            if ($tokenData->data->total_records > 0) {
                foreach ($tokenData->data->records as $key => $value) {
                    $notification_token[] = $value->notification_token;
                }
                $notificationTokens = array_unique($notification_token);
            }

            if (count($notificationTokens) > 0)
            {
                $attachmentPath = (!empty($mall->path)) ? $mall->path : '';
                $cdnUrl = (!empty($mall->cdnUrl)) ? $mall->cdnUrl : '';
                $cdnConfig = Config::get('orbit.cdn');
                $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
                $imageUrl = $imgUrl->getImageUrl($attachmentPath, $cdnUrl);
                $launchUrl = LandingPageUrlGenerator::create('mall', $mall->merchant_id, $mall->name)->generateUrl(true);
                $headings = new stdClass();
                $contents = new stdClass();
                $headings->en = $mall->name;
                $contents->en = 'There are new happenings in '.$mall->name;
                $mongoNotifId = (!empty($mallObjectNotification->notification_ids[0])) ? $mallObjectNotification->notification_ids[0]: '';
                // add query string for activity recording
                $newUrl =  $launchUrl . '&notif_id=' . $mongoNotifId;

                // Slice token where token up to 1500
                if (count($notificationTokens) > 1500) {
                    $newToken = array();
                    $stopLoop = false;
                    $startLoop = 0;
                    $oneSignalId = array();
                    while ($stopLoop == false) {
                        $newToken = array_slice($notificationTokens, $startLoop, 1500);

                        if (empty($newToken)) {
                            $stopLoop =  true;
                            break;
                        }

                        $data = [
                            'headings'           => $headings,
                            'contents'           => $contents,
                            'url'                => $newUrl,
                            'include_player_ids' => array_values($newToken),
                            'ios_attachments'    => $imageUrl,
                            'big_picture'        => $imageUrl,
                            'adm_big_picture'    => $imageUrl,
                            'chrome_big_picture' => $imageUrl,
                            'chrome_web_image'   => $imageUrl,
                            'web_push_topic'     => 'auto-mall',
                        ];

                        $oneSignal = new OneSignal($oneSignalConfig);
                        $newNotif = $oneSignal->notifications->add($data);
                        $oneSignalId[] = $newNotif->id;

                        $startLoop = $startLoop + 1500;
                    }
                    $vendorNotificationId = $oneSignalId;
                } else {
                    $data = [
                        'headings'           => $headings,
                        'contents'           => $contents,
                        'url'                => $newUrl,
                        'include_player_ids' => array_values($notificationTokens),
                        'ios_attachments'    => $imageUrl,
                        'big_picture'        => $imageUrl,
                        'adm_big_picture'    => $imageUrl,
                        'chrome_big_picture' => $imageUrl,
                        'chrome_web_image'   => $imageUrl,
                    ];

                    $oneSignal = new OneSignal($oneSignalConfig);
                    $newNotif = $oneSignal->notifications->add($data);
                    $vendorNotificationId = $newNotif->id;
                }
            }

            // update mall object notification
            $mallObjectNotificationUpdate['_id'] = $mallObjectNotificationId;
            $mallObjectNotificationUpdate['status'] = 'sent';
            $responseMallUpdate = $mongoClient->setFormParam($mallObjectNotificationUpdate)
                                              ->setEndPoint('mall-object-notifications')
                                              ->request('PUT');

            // update notification
            $mallObjectNotification = $mongoClient->setEndPoint('mall-object-notifications/' . $mallObjectNotificationId)
                                                    ->request('GET')
                                                    ->data;

            $notificationIds = $mallObjectNotification->notification_ids;
            if (! empty($notificationIds)) {
                foreach ($notificationIds as $key => $value) {
                    $notificationUpdate = ['_id' => $value,
                                           'vendor_notification_id' => $vendorNotificationId,
                                           'status' => 'sent',
                                           'sent_at' => $dateTime];
                    $responseNotificationUpdate = $mongoClient->setFormParam($notificationUpdate)
                                                              ->setEndPoint('notifications')
                                                              ->request('PUT');
                }
            }

            $notificationMall = new stdClass();
            $notificationMall->title = $mall->name;
            $notificationMall->launch_url = $newUrl;
            $notificationMall->default_language = 'en';
            $notificationMall->headings = $headings;
            $notificationMall->contents = $contents;
            $notificationMall->type = 'mall';

            // send as inApps notification
            if (! empty($userIds)) {
                $bodyInApps = [
                    'user_ids'       => $userIds,
                    'token'         => null,
                    'notifications' => $notificationMall,
                    'send_status'   => 'sent',
                    'is_viewed'     => false,
                    'is_read'       => false,
                    'created_at'    => $dateTime,
                    'image_url'     => $imageUrl
                ];

                $inApps = $mongoClient->setFormParam($bodyInApps)
                                      ->setEndPoint('user-notifications')
                                      ->request('POST');
            }

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] User Mall Notification; Status: OK; Mall ID: %s; Total Token: %s ',
                                $job->getJobId(),
                                $mallId,
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
                'message' => sprintf('[Job ID: `%s`] User Mall Notification; Status: FAIL; Mall ID: %s; Total Token: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $mallId,
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