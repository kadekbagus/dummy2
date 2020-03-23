<?php namespace Orbit\Queue\Notification;
/**
 * Process queue for sent coupon mall notification
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
use Coupon;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use stdClass;

class CouponMallNotificationQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     * @param array $data [
     *    'coupon_id' => The object Id in the media
     * ]
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $couponId = $data['coupon_id'];

        $coupon = Coupon::excludeDeleted()->where('promotion_id', $couponId)->where('status', 'active')->first();

        if (empty($coupon)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Coupon Mall Notification Coupon ID %s is not found or inactive .', $job->getJobId(), $couponId)
            ];
        }


        try {

            // check mall follower
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();
            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $follower = null;
            $mallData = null;
            $malls = null;
            $headings = null;
            $contents = null;
            $userIds = '';
            $attachmentPath = null;
            $attachmentRealPath = null;
            $cdnUrl = null;
            $cdnBucketName = null;
            $notificationId = null;
            $tokens = array();

            $prefix = DB::getTablePrefix();
            $malls = Coupon::select(DB::raw("CASE WHEN {$prefix}merchants.object_type ='tenant' THEN {$prefix}merchants.parent_id
                                                    ELSE {$prefix}merchants.merchant_id
                                            END as mall_id"))
                            ->excludeDeleted('promotions')
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->where('promotions.promotion_id', $coupon->promotion_id)
                            ->groupBy(DB::raw("CASE WHEN {$prefix}merchants.object_type ='tenant'
                                                THEN {$prefix}merchants.parent_id
                                                ELSE {$prefix}merchants.merchant_id END"))
                            ->get();

            if (!empty($malls))
            {
               foreach ($malls as $key => $value)
                {
                    $queryString = [
                        'object_id'   => $value->mall_id,
                        'object_type' => 'mall'
                    ];

                    $userFollow = $mongoClient->setQueryString($queryString)
                                              ->setEndPoint('user-follows')
                                              ->request('GET');

                    if (count($userFollow->data->records) !== 0)
                    {
                        foreach ($userFollow->data->records as $key => $value) {
                            $follower[] = $value->user_id;
                        }
                        $mallData[] = $value->mall_id;
                    }
                }
            }

            if (!empty($follower) && !empty($mallData))
            {
                // get user_ids and tokens
                $userIds = array_values(array_unique($follower));

                // Split data
                $totalUserIds = count($userIds);
                if (count($totalUserIds) > 0) {
                    $chunkSize = 100;
                    $chunkedArray = array_chunk($userIds, $chunkSize);

                    foreach ($chunkedArray as $chunk) {

                        $tokenSearch = ['user_ids' => json_encode($chunk), 'notification_provider' => 'onesignal'];
                        $tokenData = $mongoClient->setFormParam($tokenSearch)
                                                 ->setEndPoint('user-notification-tokens')
                                                 ->request('GET');

                        if ($tokenData->data->total_records > 0) {
                            foreach ($tokenData->data->records as $key => $value) {
                                $tokens[] = $value->notification_token;
                            }
                        }

                        usleep(100000);
                    }
                }

                $tokens = array_values(array_unique($tokens));

                $_coupon = Coupon::select('promotions.*',
                                      DB::raw('default_languages.name as default_language_name'),
                                      DB::raw('default_languages.language_id as default_language_id')
                                     )
                             ->with('translations.media')
                             ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                             ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                             ->where('promotions.promotion_id', '=', $coupon->promotion_id)
                             ->first();

                $launchUrl = LandingPageUrlGenerator::create('coupon', $_coupon->promotion_id, $_coupon->promotion_name)->generateUrl(true);

                $headings = new stdClass();
                $contents = new stdClass();
                $attachmentPath = null;
                $attachmentRealPath = null;
                $cdnUrl = null;
                $cdnBucketName = null;
                $mimeType = null;

                foreach ($coupon->translations as $key => $value)
                {
                    if (!empty($value->promotion_name) && !empty($value->description))
                    {
                        $languageName = $value->name;
                        if (! empty($value->promotion_name)) {
                            $headings->$languageName = $value->promotion_name;
                            $contents->$languageName = substr(str_replace('&nbsp;', ' ', strip_tags($value->description)), 0, 40) . '...';
                        }
                    }
                    if ($value->merchant_language_id === $_coupon->default_language_id)
                    {
                        if (count($value->media) !==0)
                        {
                            foreach ($value->media as $key => $value_media)
                            {
                                if($value_media->media_name_long === 'coupon_translation_image_orig')
                                {
                                    $attachmentPath = $value_media->file_name;
                                    $attachmentRealPath = $value_media->path;
                                    $cdnUrl = $value_media->cdn_url;
                                    $cdnBucketName = $value_media->cdn_bucket_name;
                                    $mimeType = $value_media->mime_type;
                                }
                            }
                        }
                    }
                }


                $dataNotification = [
                    'title' => $_coupon->promotion_name,
                    'launch_url' => $launchUrl,
                    'attachment_path' => $attachmentPath,
                    'attachment_realpath' => $attachmentRealPath,
                    'cdn_url' => $cdnUrl,
                    'cdn_bucket_name' => $cdnBucketName,
                    'default_language' => $_coupon->default_language_name,
                    'headings' => $headings,
                    'contents' => $contents,
                    'type' => 'coupon',
                    'status' => 'pending',
                    'sent_at' => null,
                    'notification_tokens' => json_encode($tokens),
                    'user_ids' => json_encode($userIds),
                    'vendor_notification_id' => null,
                    'vendor_type' => 'onesignal',
                    'is_automatic' => true,
                    'mime_type' => $mimeType,
                    'target_audience_ids' => null,
                    'created_at' => $dateTime
                ];

                $dataNotificationCheck = [
                    'title' => $_coupon->promotion_name,
                    'launch_url' => $launchUrl,
                    'type' => $_coupon->object_type,
                    'status' => 'pending',
                ];

                $notification = $mongoClient->setQueryString($dataNotificationCheck)
                                             ->setEndPoint('notifications')
                                             ->request('GET');

                if (count($notification->data->records) === 0) {
                    $notification = $mongoClient->setFormParam($dataNotification)
                                                ->setEndPoint('notifications')
                                                ->request('POST');
                    $notificationId = $notification->data->_id;
                } else {
                    $notificationId = $notification->data->records[0]->_id;
                    $updateDataNotification = [
                        '_id' => $notificationId,
                        'notification_tokens' => json_encode($tokens),
                        'user_ids' => json_encode($userIds),
                    ];

                    $updateNotification = $mongoClient->setFormParam($updateDataNotification)
                                                          ->setEndPoint('notifications')
                                                          ->request('PUT');
                }

                // loop the mall again
                foreach ($mallData as $key => $mallvalue)
                {
                    $queryString = [
                        'mall_id' => $mallvalue,
                        'status' => 'pending'
                    ];

                    $mallObjectNotif = $mongoClient->setQueryString($queryString)
                                              ->setEndPoint('mall-object-notifications')
                                              ->request('GET');

                    if (count($mallObjectNotif->data->records) === 0)
                    {
                        // insert data if not exist
                        $insertMallObjectNotification = [
                            'notification_ids' => (array)$notificationId,
                            'mall_id' => $mallvalue,
                            'user_ids' => json_encode($userIds),
                            'tokens' => json_encode($tokens),
                            'status' => 'pending',
                            'start_at' => null,
                            'created_at' => $dateTime
                        ];

                        $mallObjectNotification = $mongoClient->setFormParam($insertMallObjectNotification)
                                                              ->setEndPoint('mall-object-notifications')
                                                              ->request('POST');
                    } else {
                        // update data if exist
                        $_tokens = null;
                        $_userIds = null;
                        $_notificationIds = (array) $mallObjectNotif->data->records[0]->notification_ids;
                        $_userIds = (array) $mallObjectNotif->data->records[0]->user_ids;
                        $_tokens = (array) $mallObjectNotif->data->records[0]->tokens;
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
                            '_id' => $mallObjectNotif->data->records[0]->_id,
                            'notification_ids' => array_values(array_unique($_notificationIds)),
                            'mall_id' => $mallvalue,
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
                'message' => sprintf('[Job ID: `%s`] Coupon Mall Notification; Status: OK; Coupon ID: %s; Total Token: %s ',
                                $job->getJobId(),
                                $couponId,
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
                'message' => sprintf('[Job ID: `%s`] Coupon Mall Notification; Status: FAIL; Coupon ID: %s; Total Token: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $couponId,
                                count($tokens),
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}