<?php namespace Orbit\Queue;
/**
 * Process queue for store synchronization
 * in merchant database manager app
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use BaseMerchant;
use BaseMerchantCategory;
use BaseMerchantTranslation;
use BaseStore;
use BaseMerchantKeyword;
use Tenant;
use KeywordObject;
use CategoryMerchant;
use ObjectPartner;
use BaseObjectPartner;
use MerchantTranslation;
use Media;
use Country;
use PreSync;
use Cache;
use Sync;
use Config;
use DB;
use Carbon\Carbon as Carbon;
use Orbit\Database\ObjectID;
use User;
use Event;
use Helper\EloquentRecordCounter as RecordCounter;
use Queue;
use Orbit\FakeJob;
use Log;
use Orbit\Helper\Util\JobBurier;
use ObjectBank;
use ObjectContact;
use ObjectFinancialDetail;
use MerchantStorePaymentProvider;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Exception;
use News;
use Coupon;
use CampaignStatus;
use PromotionRetailer;
use NewsMerchant;
use ProductTagObject;
use BaseStoreProductTag;
use BaseMerchantProductTag;
use CouponRetailerRedeem;
use BaseStoreTranslation;

class StoreSynchronization
{
    protected $debug = FALSE;

    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data [sync_type => store, sync_data=>array(), user]
     */
    public function fire($job, $data)
    {
        $type = $data['sync_type'];

        switch ($type) {
            default:
            case 'store':
                $this->syncStore($data, 'store', $job);
                break;

            case 'merchant':
                $this->syncStore($data, 'merchant', $job);
                break;
        }
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function syncStore($data, $type, $job) {

        try {
            $prefix = DB::getTablePrefix();
            $sync_data = $data['sync_data'];
            $user_id = $data['user'];
            $chunk = Config::get('orbit.mdm.synchronization.chunk', 50);

            $user = User::where('user_id', $user_id)->firstOrFail();

            $stores = BaseStore::getAllPreSyncStore();

            if (is_array($sync_data)) {
                switch ($type) {
                    case 'merchant':
                        $filter = 'base_merchants.base_merchant_id';
                        break;

                    default:
                    case 'store':
                        $filter = 'base_stores.base_store_id';
                        break;
                }

                $stores->whereIn($filter, $sync_data);
            }

            DB::beginTransaction();

            $newSync = new Sync;
            $newSync->user_id = $user_id;
            $newSync->sync_type = 'store';
            $newSync->total_sync = RecordCounter::create($stores)->count();
            $newSync->finish_sync = 0;
            $newSync->save();

            $sync_id = $newSync->sync_id;

            DB::commit();

            Event::fire('orbit.basestore.sync.begin', $newSync);

            $pre_stores = clone $stores;

            $_stores = clone $stores;

            $pre_stores->chunk($chunk, function($_pre_stores) use ($sync_id, $user)
            {
                DB::beginTransaction();
                // insert table presync
                $presyncData = array();
                foreach ($_pre_stores as $pre)
                {
                    $presyncData[] = [ "pre_sync_id" => ObjectID::make(),
                                       "sync_id" => $sync_id,
                                       "object_id" => $pre->base_store_id,
                                       "object_type" => 'store',
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s") ];

                    $message = sprintf('*** Store synchronization, pre_sync -- sync_id: %s, store_id: %s; store_name: %s, location_id: %s, location_name: %s, user_email: %s ***',
                                    $sync_id,
                                    $pre->base_store_id,
                                    $pre->name,
                                    $pre->merchant_id,
                                    $pre->location_name,
                                    $user->user_email);
                    $this->debug($message . "\n");
                    \Log::info($message);
                }

                if (! empty($presyncData)) {
                    DB::table('pre_syncs')->insert($presyncData);
                }

                DB::commit();
            });

            $storeName = '';
            $countryName = '';
            $stores->chunk($chunk, function($_stores) use ($sync_id, $user, &$storeName, &$countryName)
            {
                foreach ($_stores as $store) {
                    $this->debug(sprintf("memory usage: %s\n", memory_get_peak_usage() / 1024));

                    // Begin database transaction
                    DB::beginTransaction();
                    $base_store_id = $store->base_store_id;
                    $base_merchant_id = $store->base_merchant_id;

                    //save to orb_merchant
                    $tenant = Tenant::where('merchant_id', $base_store_id)->first();
                    if (! is_object($tenant)) {
                        $tenant = new Tenant;
                    }

                    $baseMerchant = BaseMerchant::where('base_merchant_id', $base_merchant_id)->first();

                    if (! is_object($baseMerchant)) {
                        $baseMerchant = new stdclass();
                        $baseMerchant->name = 'store';
                        $baseMerchant->gender = 'A';
                    }


                    // Push notification
                    $queueName = Config::get('queue.connections.gtm_notification.queue', 'gtm_notification');

                    Queue::push('Orbit\\Queue\\Notification\\StoreSynchronizationMallNotificationQueue', [
                        'base_store_id' => $base_store_id,
                    ], $queueName);


                    //country
                    $countryId = $store->country_id;
                    $countryNames = Country::where('country_id', $countryId)->first();

                    $storeName = $store->name;
                    $countryName = $countryNames->name;
                    $baseStore = BaseStore::where('base_store_id', '=', $base_store_id)->first();

                    $tenant->merchant_id = $base_store_id;
                    $tenant->name = $store->name;
                    $tenant->description = $store->description;
                    $tenant->country_id = $countryId;
                    $tenant->country = $countryName;
                    $tenant->status = $store->status;
                    $tenant->logo = $store->path;
                    $tenant->object_type = 'tenant';
                    $tenant->parent_id = $store->merchant_id;
                    $tenant->is_mall = 'no';
                    $tenant->is_subscribed = 'Y';
                    $tenant->url = $baseStore->url;
                    $tenant->floor_id = empty($store->floor_id) ? 0 : $store->floor_id;
                    $tenant->floor = $store->object_name;
                    $tenant->unit = $store->unit;
                    $tenant->phone = $store->phone;
                    $tenant->masterbox_number = $store->verification_number;
                    $tenant->mobile_default_language = $store->mobile_default_language;
                    $tenant->is_payment_acquire = $store->is_payment_acquire;
                    $tenant->gender = $baseMerchant->gender;
                    $tenant->facebook_url = $baseStore->facebook_url;
                    $tenant->instagram_url = $baseStore->instagram_url;
                    $tenant->twitter_url = $baseStore->twitter_url;
                    $tenant->youtube_url = $baseStore->youtube_url;
                    $tenant->line_url = $baseStore->line_url;
                    $tenant->other_photo_section_title = $baseMerchant->other_photo_section_title;
                    $tenant->video_id_1 = $baseStore->video_id_1;
                    $tenant->video_id_2 = $baseStore->video_id_2;
                    $tenant->video_id_3 = $baseStore->video_id_3;
                    $tenant->video_id_4 = $baseStore->video_id_4;
                    $tenant->video_id_5 = $baseStore->video_id_5;
                    $tenant->video_id_6 = $baseStore->video_id_6;
                    $tenant->disable_ads = $baseStore->disable_ads;
                    $tenant->disable_ymal = $baseStore->disable_ymal;
                    $tenant->save();

                    // handle inactive store
                    if ($store->status === 'inactive') {

                        // Remove all key *store* in Redis
                        if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                            $redis = Cache::getRedis();
                            $keyName = array('store','home');
                            foreach ($keyName as $value) {
                                $keys = $redis->keys("*$value*");
                                if (! empty($keys)) {
                                    foreach ($keys as $key) {
                                        $redis->del($key);
                                    }
                                }
                            }
                        }

                        $prefix = DB::getTablePrefix();
                        // check campaign that linked to this inactive store
                        $news = News::select('news.news_name','news.news_id', 'news.object_type', 'news.status', 'news.campaign_status_id',
                                         DB::raw("(select COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id)
                                                    from {$prefix}news_merchant
                                                        left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                                        left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                        where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as total_location"),
                                         DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id WHERE om.merchant_id = {$prefix}news.mall_id)
                                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"))
                                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                                    ->excludeDeleted('news')
                                    ->having('campaign_status', '=', 'ongoing')
                                    ->where('news_merchant.merchant_id', '=', $base_store_id)
                                    ->groupBy('news.news_id')
                                    ->get();

                        if (!empty($news)) {
                            foreach($news as $key => $value) {
                                // if only one location, update the campaign status to paused
                                if ($value->total_location == 1) {
                                    $campaignStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', '=', 'paused')->first();
                                    $updateNews = News::where('news_id', '=', $value->news_id)->first();
                                    $updateNews->status = 'inactive';
                                    $updateNews->campaign_status_id = $campaignStatus->campaign_status_id;
                                    $updateNews->save();
                                }

                                // delete link to campaign (news_merchant)
                                $deleteLocation = NewsMerchant::where('news_id', '=', $value->news_id)
                                                              ->where('merchant_id', '=', $base_store_id)
                                                              ->where('object_type', '=', 'retailer')
                                                              ->delete();

                                // update ES
                                if ($value->object_type == 'news') {
                                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', ['news_id' => $value->news_id]);
                                }

                                if ($value->object_type == 'promotion') {
                                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', ['news_id' => $value->news_id]);
                                }
                            }
                        }


                        // coupon
                        $coupons = Coupon::select('promotions.promotion_id','promotions.promotion_name','promotions.status',
                                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                                                                FROM {$prefix}merchants om
                                                                                                                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                                                                WHERE om.merchant_id = {$prefix}promotions.merchant_id)
                                                                            THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status"),
                                            DB::raw("(select COUNT(DISTINCT {$prefix}promotion_retailer.promotion_retailer_id)
                                                                                    from {$prefix}promotion_retailer
                                                                                    inner join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}promotion_retailer.retailer_id
                                                                                    inner join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                                                                    where {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id) as total_location")
                                            )
                                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                        ->excludeDeleted('promotions')
                                        ->where('promotion_retailer.retailer_id', '=', $base_store_id)
                                        ->having('campaign_status', '=', 'ongoing')
                                        ->groupBy('promotions.promotion_id')
                                        ->get();

                        if (!empty($coupons)) {
                            foreach($coupons as $key => $value) {
                                // if only one location, update the campaign status to paused
                                if ($value->total_location == 1) {
                                    $campaignStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', '=', 'paused')->first();
                                    $updateCoupon = Coupon::where('promotion_id', '=', $value->promotion_id)->first();
                                    $updateCoupon->status = 'inactive';
                                    $updateCoupon->campaign_status_id = $campaignStatus->campaign_status_id;
                                    $updateCoupon->save();
                                }

                                // delete link to campaign (promotion_retailer)
                                $deleteLocation = PromotionRetailer::where('promotion_id', '=', $value->promotion_id)
                                                              ->where('retailer_id', '=', $base_store_id)
                                                              ->where('object_type', '=', 'tenant')
                                                              ->delete();

                                // delete redemption place
                                $deleteRetailerRedeem = CouponRetailerRedeem::where('promotion_id', '=', $value->promotion_id)
                                                                        ->where('retailer_id', '=', $base_store_id)
                                                                        ->where('object_type', '=', 'tenant')
                                                                        ->delete();

                                Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', ['coupon_id' => $value->promotion_id]);
                            }
                        }
                    }


                    // Insert the payment acquire, only chech if payment acquire = Y
                    if ($tenant->is_payment_acquire == 'Y') {
                        $objectId = $base_store_id;
                        $objectType = 'store';

                        $deleteBaseObjectFinancialDetail = ObjectFinancialDetail::where('object_id', $base_store_id)->where('object_type', 'store')->delete(true);
                        $baseObjectFinancialDetail = ObjectFinancialDetail::where('object_id', $base_store_id)->where('object_type', 'base_store')->first();
                        if (! empty($baseObjectFinancialDetail)) {
                            $newObjectFinancialDetail = new ObjectFinancialDetail;
                            $newObjectFinancialDetail->object_id = $objectId;
                            $newObjectFinancialDetail->object_type = $objectType;
                            $newObjectFinancialDetail->contact_name = $baseObjectFinancialDetail->contact_name;
                            $newObjectFinancialDetail->position = $baseObjectFinancialDetail->position;
                            $newObjectFinancialDetail->phone_number = $baseObjectFinancialDetail->phone_number;
                            $newObjectFinancialDetail->email = $baseObjectFinancialDetail->email;
                            $newObjectFinancialDetail->save();
                        }

                        $deleteBaseObjectContact = ObjectContact::where('object_id', $base_store_id)->where('object_type', 'store')->delete(true);
                        $baseObjectContact = ObjectContact::where('object_id', $base_store_id)->where('object_type', 'base_store')->first();
                        if (! empty($baseObjectContact)) {
                            $newStoreCotactPerson = new ObjectContact;
                            $newStoreCotactPerson->object_id = $objectId;
                            $newStoreCotactPerson->object_type = $objectType;
                            $newStoreCotactPerson->contact_name = $baseObjectContact->contact_name;
                            $newStoreCotactPerson->position = $baseObjectContact->position;
                            $newStoreCotactPerson->phone_number = $baseObjectContact->phone_number;
                            $newStoreCotactPerson->email = $baseObjectContact->email;
                            $newStoreCotactPerson->save();
                        }

                        $deleteBaseObjectBanks = ObjectBank::where('object_id', $base_store_id)->where('object_type', 'store')->delete(true);
                        $baseObjectBanks = ObjectBank::where('object_id', $base_store_id)->where('object_type', 'base_store')->get();
                        if (! $baseObjectBanks->isEmpty()) {
                            $objectBanks = array();
                            foreach ($baseObjectBanks as $baseObjectBank) {
                                $objectBanks[] = [
                                                    'object_bank_id' => ObjectID::make(),
                                                    'object_id' => $objectId,
                                                    'object_type' => $objectType,
                                                    'bank_id' => $baseObjectBank->bank_id,
                                                    'account_name' => $baseObjectBank->account_name,
                                                    'account_number' => $baseObjectBank->account_number,
                                                    'bank_address' => $baseObjectBank->bank_address,
                                                    'swift_code' => $baseObjectBank->swift_code
                                                ];
                            }

                            if (! empty($objectBanks)) {
                                DB::table('object_banks')->insert($objectBanks);
                            }
                        }

                        $deleteBaseMerchantStorePaymentProviders = MerchantStorePaymentProvider::where('object_id', $base_store_id)->where('object_type', 'store')->delete(true);
                        $baseMerchantStorePaymentProviders = MerchantStorePaymentProvider::where('object_id', $base_store_id)->where('object_type', 'base_store')->get();
                        if (! $baseMerchantStorePaymentProviders->isEmpty()) {
                            $MerchantStorePaymentProviders = array();
                            foreach ($baseMerchantStorePaymentProviders as $baseMerchantStorePaymentProvider) {
                                $MerchantStorePaymentProviders[] = [
                                                    'payment_provider_store_id' => ObjectID::make(),
                                                    'payment_provider_id' => $baseMerchantStorePaymentProvider->payment_provider_id,
                                                    'object_id' => $objectId,
                                                    'object_type' => $objectType,
                                                    'phone_number_for_sms' => $baseMerchantStorePaymentProvider->phone_number_for_sms,
                                                    'mdr' => $baseMerchantStorePaymentProvider->mdr
                                                ];
                            }

                            if (! empty($MerchantStorePaymentProviders)) {
                                DB::table('merchant_store_payment_provider')->insert($MerchantStorePaymentProviders);
                            }
                        }
                    }

                    // save to table merchant_translation
                    // delete translation
                    $delete_translation = MerchantTranslation::where('merchant_id', $base_store_id)->delete(true);
                    // insert translation
                    $base_translations = BaseStoreTranslation::where('base_store_id', $base_store_id)->get();
                    $translations = array();
                    foreach ($base_translations as $base_translation) {
                        $translations[] = [ 'merchant_translation_id' => ObjectID::make(),
                                            'merchant_id' => $base_store_id,
                                            'merchant_language_id' => $base_translation->language_id,
                                            'description' => $base_translation->description,
                                            'custom_title' => $base_translation->custom_title,
                                            'created_by' => 0,
                                            'modified_by' => 0,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($translations)) {
                        DB::table('merchant_translations')->insert($translations);
                    }

                    // save to keyword_object
                    // delete keyword
                    $delete_keyword = KeywordObject::where('object_id', $base_store_id)->delete(true);
                    // insert keyword
                    $base_keywords = BaseMerchantKeyword::where('base_merchant_id', $base_merchant_id)->get();
                    $keywords = array();
                    foreach ($base_keywords as $base_keyword) {
                        $keywords[] = [ 'keyword_object_id' => ObjectID::make(),
                                        'keyword_id' => $base_keyword->keyword_id,
                                        'object_type' => 'tenant',
                                        'object_id' => $base_store_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($keywords)) {
                        DB::table('keyword_object')->insert($keywords);
                    }

                    // save to product_tag
                    $delete_product_tag = ProductTagObject::where('object_id', '=', $base_store_id)->delete(true);
                    $base_product_tags = BaseMerchantProductTag::where('base_merchant_id', '=', $base_merchant_id)->get();
                    $productTags = array();
                    foreach ($base_product_tags as $base_product_tag) {
                        $productTags[] = [ 'product_tag_object_id' => ObjectID::make(),
                                           'product_tag_id' => $base_product_tag->product_tag_id,
                                           'object_type' => 'tenant',
                                           'object_id' => $base_store_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s")];
                    }
                    if (! empty($productTags)) {
                        DB::table('product_tag_object')->insert($productTags);
                    }

                    // save to category
                    // delete category
                    $delete_category = CategoryMerchant::where('merchant_id', $base_store_id)->delete(true);
                    // insert category
                    $base_categories = BaseMerchantCategory::join('categories', 'categories.category_id', '=', 'base_merchant_category.category_id')
                                                            ->where('base_merchant_category.base_merchant_id', $base_merchant_id)
                                                            ->where('categories.status', '!=', 'deleted')->get();
                    $categories = array();
                    foreach ($base_categories as $base_category) {
                        $categories[] = [ 'category_merchant_id' => ObjectID::make(),
                                          'category_id' => $base_category->category_id,
                                          'merchant_id' => $base_store_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($categories)) {
                        DB::table('category_merchant')->insert($categories);
                    }

                    // save to object_partner
                    // delete object_partner
                    $delete_object_partner = ObjectPartner::where('object_id', $base_store_id)->where('object_type', 'tenant')->delete(true);
                    // insert object_partner
                    $base_object_partners = BaseObjectPartner::join('partners', 'partners.partner_id', '=', 'base_object_partner.partner_id')
                                                            ->where('base_object_partner.object_id', $base_merchant_id)
                                                            ->where('base_object_partner.object_type', 'tenant')->get();
                    $object_partner = array();
                    foreach ($base_object_partners as $base_object_partner) {
                        $object_partner[] = [ 'object_partner_id' => ObjectID::make(),
                                          'object_id' => $base_store_id,
                                          'object_type' => 'tenant',
                                          'partner_id' => $base_object_partner->partner_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($object_partner)) {
                        DB::table('object_partner')->insert($object_partner);
                    }

                    // save to media
                    // delete media (logo, image, map)
                    $oldMedia = Media::where('object_name', 'retailer')->where('object_id', $base_store_id)->get();
                    $realpath = array();
                    $oldPath = array();
                    $isUpdate = false;

                    foreach ($oldMedia as $file) {
                        $isUpdate = true;
                        $realpath[] = $file->realpath;

                        //get old path before delete
                        $oldPath[$file->media_id]['path'] = $file->path;
                        $oldPath[$file->media_id]['cdn_url'] = $file->cdn_url;
                        $oldPath[$file->media_id]['cdn_bucket_name'] = $file->cdn_bucket_name;

                        // No need to check the return status, just delete and forget
                        @unlink($file->realpath);
                    }

                    // queue for data amazon s3
                    $fakeJob = new FakeJob();
                    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);
                    $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                    $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

                    if ($usingCdn) {
                        $data = [
                            'object_id'     => $base_store_id,
                            'media_name_id' => null,
                            'old_path'      => $oldPath,
                            'es_type'       => 'store',
                            'es_id'         => $storeName,
                            'bucket_name'   => $bucketName
                        ];

                        $esQueue = new \Orbit\Queue\CdnUpload\CdnUploadDeleteQueue();
                        $response = $esQueue->fire($fakeJob, $data);
                    }
                    $delete_media = Media::where('object_name', 'retailer')->where('object_id', $base_store_id)->delete(true);

                    // copy logo from base_store directory to retailer directory
                    $logo = Media::where('object_name', 'base_merchant')
                                ->where('media_name_id', 'base_merchant_logo')
                                ->where('object_id', $base_merchant_id)
                                ->get();
                    $logo_image = $this->updateMedia('logo', $logo, $base_store_id);

                    // copy picture from base_store directory to retailer directory
                    $pic = Media::where('object_name', 'base_store')
                                ->where('media_name_id', 'base_store_image')
                                ->where('object_id', $base_store_id)
                                ->get();
                    $pic_image = $this->updateMedia('picture', $pic, $base_store_id);

                    // copy map from base_store directory to retailer directory
                    $map = Media::where('object_name', 'base_store')
                                ->where('media_name_id', 'base_store_map')
                                ->where('object_id', $base_store_id)
                                ->get();
                    $map_image = $this->updateMedia('map', $map, $base_store_id);

                    // copy banner from base_store directory to retailer directory
                    $bannerStore = Media::where('object_name', 'base_store')
                                        ->where('media_name_id', 'base_store_banner')
                                        ->where('object_id', $base_store_id)
                                        ->get();

                    $bannerMerchant = Media::where('object_name', 'base_merchant')
                                        ->where('media_name_id', 'base_merchant_banner')
                                        ->where('object_id', $base_merchant_id)
                                        ->get();

                    // if banner store exist use banner store, if not use merchant banner
                    if (count($bannerStore)) {
                        $banner = $bannerStore;
                    } else {
                        $banner = $bannerMerchant;
                    }
                    $banner_image = $this->updateMedia('banner', $banner, $base_store_id);

                    $images = array_merge($logo_image, $pic_image, $map_image, $banner_image);

                    // get presync data
                    $presync = PreSync::where('sync_id', $sync_id)
                                    ->where('object_type', 'store')
                                    ->where('object_id', $base_store_id)
                                    ->first();
                    // move to post_sync
                    $postsync = $presync->moveToPostSync();
                    // increment finish_sync value
                    $sync = $postsync->incrementSyncCounter();

                    if ($sync->isCompleted()) {
                        // if total_sync === finish_sync, update status to done
                        $sync->done();
                    }

                    DB::commit();

                    $message = sprintf('*** Store synchronization success, store_id: %s; store_name: %s, location_id: %s, location_name: %s, user_email: %s ***',
                                    $base_store_id,
                                    $store->name,
                                    $store->merchant_id,
                                    $store->location_name,
                                    $user->user_email);
                    $this->debug($message . "\n");
                    \Log::info($message);

                    $delete_images = array_diff($realpath, $images);
                    foreach ($delete_images as $rp) {
                        $this->debug(sprintf("Starting to unlink in: %s\n", $rp));
                        if (! @unlink($rp)) {
                            $this->debug("Failed to unlink\n");
                        }
                    }

                    // queue for data amazon s3
                    if ($usingCdn) {
                        Queue::push('Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue', [
                            'object_id'     => $base_store_id,
                            'media_name_id' => null,
                            'old_path'      => null,
                            'es_type'       => 'store',
                            'es_id'         => $storeName,
                            'es_country'    => $countryName,
                            'bucket_name'   => $bucketName
                        ], $queueName);
                    }
                }
            });

            $_stores = $_stores->groupBy('base_merchants.name')->groupBy('base_merchants.country_id')->get();
            if (count($_stores) > 0) {
                foreach ($_stores as $storeCountry) {
                    //country
                    $countryIds = $storeCountry->country_id;
                    $countryNameList = Country::where('country_id', $countryIds)->first();

                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
                        'name' => $storeCountry->name,
                        'country' => $countryNameList->name
                    ]);
                }
            }

            Event::fire('orbit.basestore.sync.complete', $newSync);

            $message = sprintf('[Job ID: `%s`] Store synchronization; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (InvalidArgsException $e) {
            $message = '*** Store synchronization error, messge: ' . $e->getMessage() . '***';
            \Log::error($message);
            DB::rollBack();
        } catch (QueryException $e) {
            $message = '*** Store synchronization error, messge: ' . $e->getMessage() . '***';
            \Log::error($message);
            DB::rollBack();
        } catch (Exception $e) {
            $message = '*** Store synchronization error, messge: ' . $e->getMessage() . '***';
            \Log::error($message);
            DB::rollBack();
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

    protected function updateMedia($type, $data, $store_id) {
        $path = public_path();

        $baseConfig = Config::get('orbit.upload.base_store');
        $retailerConfig = Config::get('orbit.upload.retailer');
        $images = array();

        foreach ($data as $dt) {
            $filename = $dt->file_name;
            switch ($type) {
                case 'logo':
                    $filename = $store_id . '-' . $dt->file_name;
                    $nameid = "retailer_logo";
                    break;

                case 'picture':
                    $nameid = "retailer_image";
                    break;

                case 'map':
                    $nameid = "retailer_map";
                    break;

                case 'banner':
                    $filename = $store_id . '-' . $dt->file_name;
                    $nameid = "retailer_banner";
                    break;

                default:
                    $nameid = "";
                    break;
            }

            $sourceMediaPath = $path . DS . $baseConfig[$type]['path'] . DS . $dt->file_name;
            $destMediaPath = $path . DS . $retailerConfig[$type]['path'] . DS . $filename;
            $this->debug(sprintf("Starting to copy from: %s to %s\n", $sourceMediaPath, $destMediaPath));
            if (! @copy($sourceMediaPath, $destMediaPath)) {
                $this->debug("Failed to copy\n");
            }

            if ($dt->object_name === 'base_merchant') {
                $name_long = str_replace('base_merchant_', 'retailer_', $dt->media_name_long);
            } else {
                $name_long = str_replace('base_store_', 'retailer_', $dt->media_name_long);
                $name_long = str_replace('base_', 'retailer_', $name_long);
            }

            $newMedia = new Media;
            $newMedia->media_name_id = $nameid;
            $newMedia->media_name_long = $name_long;
            $newMedia->object_id = $store_id;
            $newMedia->object_name = 'retailer';
            $newMedia->file_name = $filename;
            $newMedia->file_extension = $dt->file_extension;
            $newMedia->file_size = $dt->file_size;
            $newMedia->mime_type = $dt->mime_type;
            $newMedia->path = $retailerConfig[$type]['path'] . DS . $filename;
            $newMedia->realpath = $destMediaPath;
            $newMedia->metadata = $dt->metadata;
            $newMedia->modified_by = $dt->modified_by;
            $newMedia->created_at = $dt->created_at;
            $newMedia->updated_at = $dt->updated_at;
            $newMedia->save();

            $images[] = $newMedia->realpath;
        }

        return $images;
    }

    protected function debug($message = '')
    {
        if ($this->debug) {
            echo $message;
        }
    }

    public function setDebug($value)
    {
        $this->debug = $value;
    }
}
