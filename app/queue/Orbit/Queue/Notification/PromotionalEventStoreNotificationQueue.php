<?php namespace Orbit\Queue\Notification;
/**
 * Process queue for sent promotional event store notification
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
use News;
use ObjectSponsor;
use UserSponsor;
use NewsMerchant;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use stdClass;

class PromotionalEventStoreNotificationQueue
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
        $newsId = $data['news_id'];
        $notificationToken = array();

        $updatedPromotionalEvent = News::excludeDeleted()->where('news_id', $newsId)->where('status', 'active')->first();

        if (empty($updatedPromotionalEvent)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Promotional Event Store Notification News ID %s is not found or inactive .', $job->getJobId(), $newsId)
            ];
        }


        try {


            //Check date and status
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();
            $dateTimeNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');
            $table_prefix = DB::getTablePrefix();

            if ($updatedPromotionalEvent->status === 'active') {

                // check existing notification
                $objectType = 'event';

                $queryStringStoreObject = [
                    'object_id' => $updatedPromotionalEvent->news_id,
                    'object_type' => $objectType,
                ];

                $storeObjectNotifications = $mongoClient->setQueryString($queryStringStoreObject)
                                        ->setEndPoint('store-object-notifications')
                                        ->request('GET');

                $newsMain = News::join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                 ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                                 ->where('news_id', '=', $updatedPromotionalEvent->news_id);

                $langNews = $newsMain->select(DB::raw('default_languages.name as default_language_name'))->first();
                $defaultLangName = $langNews->default_language_name;

                $_news = $newsMain->select('news.*', DB::raw('default_languages.name as default_language_name'), DB::raw('default_languages.language_id as default_language_id'))
                                 ->with('translations.media')
                                 ->first();

                $userSponsor = [];
                if ($updatedPromotionalEvent->is_sponsored === 'Y') {
                    // Notification Credit Card & E-wallet
                    // get campaign cities
                    $cities = News::select('news.news_name',
                                            DB::raw("CASE WHEN m1.object_type = 'tenant' THEN m2.city
                                                          WHEN m1.object_type = 'mall' THEN m1.city
                                                    END as city"),
                                            DB::raw("CASE WHEN m1.object_type = 'tenant' THEN mc2.mall_city_id
                                                          WHEN m1.object_type = 'mall' THEN mc1.mall_city_id
                                                    END as city_id")
                                            )
                                      ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                      ->leftJoin(DB::raw("{$table_prefix}merchants as m1"), function($join) {
                                            $join->on(DB::raw('m1.merchant_id'), '=', 'news_merchant.merchant_id');
                                        })
                                      ->leftJoin(DB::raw("{$table_prefix}merchants as m2"), function($join) {
                                            $join->on(DB::raw('m2.merchant_id'), '=', DB::raw('m1.parent_id'));
                                        })
                                      ->leftJoin(DB::raw("{$table_prefix}mall_cities as mc1"), function($join) {
                                            $join->on(DB::raw('mc1.city'), '=', DB::raw('m1.city'));
                                        })
                                      ->leftJoin(DB::raw("{$table_prefix}mall_cities as mc2"), function($join) {
                                            $join->on(DB::raw('mc2.city'), '=', DB::raw('m2.city'));
                                        })
                                      ->where('news.news_id', '=', $updatedPromotionalEvent->news_id)
                                      ->groupBy('city_id')
                                      ->get();

                    $campaignCities = [];
                    if (!empty($cities)) {
                        foreach($cities as $key => $value) {
                            $campaignCities [] = $value->city_id;
                        }
                    }

                    // get the user that using credit-card/ewallet that link to campaign and has the same city as the campaign
                    // get ewallet id
                    $sponsorId = [];
                    $sponsorProviderEwallet = ObjectSponsor::select('sponsor_providers.sponsor_provider_id')
                                                            ->join('sponsor_providers','sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                                            ->where('sponsor_providers.status', 'active')
                                                            ->where('sponsor_providers.object_type', 'ewallet')
                                                            ->where('object_sponsor.object_id', $updatedPromotionalEvent->news_id)
                                                            ->get();

                    if (!empty($sponsorProviderEwallet)) {
                        foreach ($sponsorProviderEwallet as $key => $value) {
                            $sponsorId [] = $value->sponsor_provider_id;
                        }
                    }

                    // get credit card id
                    $sponsorProviderCreditCard = ObjectSponsor::select('sponsor_credit_cards.sponsor_credit_card_id', 'sponsor_providers.sponsor_provider_id', 'object_sponsor.is_all_credit_card')
                                                            ->join('sponsor_providers','sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                                            ->join('sponsor_credit_cards','sponsor_credit_cards.sponsor_provider_id', '=', 'sponsor_providers.sponsor_provider_id')
                                                            ->where('sponsor_providers.status', 'active')
                                                            ->where('sponsor_providers.object_type', 'bank')
                                                            ->where('object_sponsor.object_id', $updatedPromotionalEvent->news_id)
                                                            ->get();

                    if (!empty($sponsorProviderCreditCard)) {
                        foreach ($sponsorProviderCreditCard as $key => $value) {
                            $sponsorId [] = $value->sponsor_credit_card_id;
                        }
                    }

                    // get the user id that match criteria
                    $objectSponsorUser = UserSponsor::select('user_sponsor.user_id')
                                                    ->join('user_sponsor_allowed_notification', 'user_sponsor_allowed_notification.user_id', '=', 'user_sponsor.user_id')
                                                    ->join('user_sponsor_allowed_notification_cities', 'user_sponsor_allowed_notification_cities.user_id', '=', 'user_sponsor_allowed_notification.user_id')
                                                    ->whereIn('user_sponsor.sponsor_id', $sponsorId)
                                                    ->whereIn('user_sponsor_allowed_notification_cities.mall_city_id', $campaignCities)
                                                    ->groupBy('user_sponsor.user_id')
                                                    ->get();

                    if (!empty($objectSponsorUser)) {
                        foreach($objectSponsorUser as $key => $value) {
                            $userSponsor [] = $value->user_id;
                        }
                    }
                }

                $launchUrl = LandingPageUrlGenerator::create('promotional-event', $_news->news_id, $_news->news_name)->generateUrl(true);
                $attachmentPath = null;
                $attachmentRealPath = null;
                $cdnUrl = null;
                $cdnBucketName = null;
                $mimeType = null;
                $headings = new stdClass();
                $contents = new stdClass();

                // get heading, content, and image
                foreach ($_news->translations as $translation) {
                    $languageName = $translation['name'];
                    if (! empty($translation['news_name'])) {
                        $headings->$languageName = $translation['news_name'];
                        $contents->$languageName = substr(str_replace('&nbsp;', ' ', strip_tags($translation['description'])), 0, 40) . '...';
                    }

                    if ($translation['merchant_language_id'] === $_news->default_language_id) {
                        if (! empty($translation->media)) {
                            foreach ($translation->media as $media) {
                                if ($media['media_name_long'] === 'news_translation_image_orig') {
                                    $attachmentPath = $media['path'];
                                    $attachmentRealPath = $media['realpath'];
                                    $cdnUrl = $media['cdn_url'];
                                    $cdnBucketName = $media['cdn_bucket_name'];
                                    $mimeType = $media['mime_type'];
                                }
                            }
                        }
                    }
                }

                // Insert when no data, update when exist
                if (! empty($storeObjectNotifications->data->records)) {
                    // Update name, description and image
                    if ($storeObjectNotifications->data->records[0]->status === 'pending') {
                        $notificationId = isset($storeObjectNotifications->data->records[0]->notification->_id) ? $storeObjectNotifications->data->records[0]->notification->_id : '';
                        $bodyUpdateNotification['title'] = $_news->news_name;
                        $bodyUpdateNotification['launch_url'] = $launchUrl;
                        $bodyUpdateNotification['attachment_path'] = $attachmentPath;
                        $bodyUpdateNotification['attachment_realpath'] = $attachmentRealPath;
                        $bodyUpdateNotification['cdn_url'] = $cdnUrl;
                        $bodyUpdateNotification['cdn_bucket_name'] = $cdnBucketName;
                        $bodyUpdateNotification['default_language'] = $_news->default_language_name;
                        $bodyUpdateNotification['headings'] = $headings;
                        $bodyUpdateNotification['contents'] = $contents;
                        $bodyUpdateNotification['mime_type'] = $mimeType;
                        $bodyUpdateNotification['created_at'] = $dateTime;
                        $bodyUpdateNotification['_id'] = $notificationId;
                        $updateNotification = $mongoClient->setFormParam($bodyUpdateNotification)
                                                    ->setEndPoint('notifications')
                                                    ->request('PUT');

                        if ($updateNotification) {
                            $notification = $mongoClient->setEndPoint('notifications/' . $notificationId)
                                                        ->request('GET');

                            $storeObjectNotificationId = isset($storeObjectNotifications->data->records[0]->_id) ? $storeObjectNotifications->data->records[0]->_id : '';
                            $bodyUpdateStoreObjectNotifation['notification'] = $notification->data;
                            $bodyUpdateStoreObjectNotifation['_id'] = $storeObjectNotificationId;
                            $updatepdateStoreObjectNotifation = $mongoClient->setFormParam($bodyUpdateStoreObjectNotifation)
                                                        ->setEndPoint('store-object-notifications')
                                                        ->request('PUT');
                        }
                    }
                } else {
                    // Insert
                    $newsLinkToTenant = NewsMerchant::where('news_id', $updatedPromotionalEvent->news_id)
                                                    ->lists('merchant_id');

                    $tenantIds = '';
                    if (count($newsLinkToTenant) > 0) {
                        $tenantIds = json_encode($newsLinkToTenant);
                    }

                    $queryStringUserFollow = [
                        'object_id'   => $tenantIds,
                        'object_type' => 'store'
                    ];

                    // get user_ids and tokens
                    $userFollows = $mongoClient->setQueryString($queryStringUserFollow)
                                            ->setEndPoint('user-id-follows')
                                            ->request('GET');

                    $userIds = null;
                    $notificationToken = array();

                    // If there is any followed by user
                    if ($userFollows->data->returned_records > 0) {
                        $userIds = $userFollows->data->records;

                        // add user_id from credit-card/ewallet (if any)
                        if (!empty($userSponsor)) {
                            foreach ($userSponsor as $key => $value) {
                                $userIds[] = $value;
                            }
                            $userIds = array_values(array_unique($userIds));
                        }

                        $queryStringUserNotifToken['user_ids'] = json_encode($userIds);

                        $notificationTokens = $mongoClient->setFormParam($queryStringUserNotifToken)
                                            ->setEndPoint('user-notification-tokens')
                                            ->request('POST');

                        if ($notificationTokens->data->total_records > 0) {
                            foreach ($notificationTokens->data->records as $key => $val) {
                                $notificationToken[] = $val->notification_token;
                            }
                        }

                        // save to notifications collection in mongodb
                        $dataNotification = [
                            'title' => $_news->news_name,
                            'launch_url' => $launchUrl,
                            'attachment_path' => $attachmentPath,
                            'attachment_realpath' => $attachmentRealPath,
                            'cdn_url' => $cdnUrl,
                            'cdn_bucket_name' => $cdnBucketName,
                            'default_language' => $_news->default_language_name,
                            'headings' => $headings,
                            'contents' => $contents,
                            'type' => $objectType,
                            'status' => 'pending',
                            'sent_at' => null,
                            'notification_tokens' => json_encode($notificationToken),
                            'user_ids' => json_encode($userIds),
                            'vendor_notification_id' => null,
                            'vendor_type' => Config::get('orbit.vendor_push_notification.default'),
                            'is_automatic' => true,
                            'mime_type' => $mimeType,
                            'target_audience_ids' => null,
                            'created_at' => $dateTime
                        ];

                        $notification = $mongoClient->setFormParam($dataNotification)
                                                    ->setEndPoint('notifications')
                                                    ->request('POST');
                        $notificationId = $notification->data->_id;

                        // save to store_object_notifications collection in mongodb
                        $bodyStoreObjectNotifications = [
                            'notification' => $notification->data,
                            'object_id' => $_news->news_id,
                            'object_type' => $objectType,
                            'status' => 'pending',
                            'start_date' => $_news->begin_date,
                            'created_at' => $dateTime
                        ];

                        $storeObjectNotif = $mongoClient->setFormParam($bodyStoreObjectNotifications)
                                                        ->setEndPoint('store-object-notifications')
                                                        ->request('POST');
                    }

                    // If there is no follower but there is user linked to credit-card/ewallet
                    if ($userFollows->data->returned_records === 0 && !empty($userSponsor)) {
                        $userIds = $userSponsor;
                        $queryStringUserNotifToken['user_ids'] = json_encode($userIds);

                        $notificationTokens = $mongoClient->setFormParam($queryStringUserNotifToken)
                                            ->setEndPoint('user-notification-tokens')
                                            ->request('POST');

                        if ($notificationTokens->data->total_records > 0) {
                            foreach ($notificationTokens->data->records as $key => $val) {
                                $notificationToken[] = $val->notification_token;
                            }
                        }

                        // save to notifications collection in mongodb
                        $dataNotification = [
                            'title' => $_news->news_name,
                            'launch_url' => $launchUrl,
                            'attachment_path' => $attachmentPath,
                            'attachment_realpath' => $attachmentRealPath,
                            'cdn_url' => $cdnUrl,
                            'cdn_bucket_name' => $cdnBucketName,
                            'default_language' => $_news->default_language_name,
                            'headings' => $headings,
                            'contents' => $contents,
                            'type' => $objectType,
                            'status' => 'pending',
                            'sent_at' => null,
                            'notification_tokens' => json_encode($notificationToken),
                            'user_ids' => json_encode($userIds),
                            'vendor_notification_id' => null,
                            'vendor_type' => Config::get('orbit.vendor_push_notification.default'),
                            'is_automatic' => true,
                            'mime_type' => $mimeType,
                            'target_audience_ids' => null,
                            'created_at' => $dateTime
                        ];

                        $notification = $mongoClient->setFormParam($dataNotification)
                                                    ->setEndPoint('notifications')
                                                    ->request('POST');
                        $notificationId = $notification->data->_id;

                        // save to store_object_notifications collection in mongodb
                        $bodyStoreObjectNotifications = [
                            'notification' => $notification->data,
                            'object_id' => $_news->news_id,
                            'object_type' => $objectType,
                            'status' => 'pending',
                            'start_date' => $_news->begin_date,
                            'created_at' => $dateTime
                        ];

                        $storeObjectNotif = $mongoClient->setFormParam($bodyStoreObjectNotifications)
                                                        ->setEndPoint('store-object-notifications')
                                                        ->request('POST');
                    }
                }

            }


            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Promotional Event Store Notification; Status: OK; News ID: %s; Total Token: %s ',
                                $job->getJobId(),
                                $newsId,
                                count($notificationToken)
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
                'message' => sprintf('[Job ID: `%s`] Promotional Event Store Notification; Status: FAIL; News ID: %s; Total Token: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $newsId,
                                count($notificationToken),
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}