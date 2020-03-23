<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when store/tenant has been updated.
 *
 * @author kadek <kadek@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Tenant;
use Advert;
use BaseMerchant;
use BaseStore;
use TotalObjectPageView;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Queue;
use Orbit\FakeJob;
use Orbit\Helper\MongoDB\Client as MongoClient;

class ESStoreUpdateQueue
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    protected $poster = NULL;

    /**
     * Class constructor.
     *
     * @param string $poster Object used to post the data.
     * @return void
     */
    public function __construct($poster = 'default')
    {
        if ($poster === 'default') {
            $this->poster = ESBuilder::create()
                                     ->setHosts(Config::get('orbit.elasticsearch.hosts'))
                                     ->build();
        } else {
            $this->poster = $poster;
        }
    }

    /**
     * Laravel main method to fire a job on a queue.
     */
    public function fire($job, $data)
    {
        try {
            $prefix = DB::getTablePrefix();
            $esConfig = Config::get('orbit.elasticsearch');
            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $updateRelated = (empty($data['update_related']) ? FALSE : $data['update_related']);
            $mongoConfig = Config::get('database.mongodb');

            $storeName = $data['name'];
            $countryName = $data['country'];
            $store = Tenant::with('keywords','translations','adverts','campaignObjectPartners', 'categories', 'product_tags')
                            ->select(
                                'merchants.merchant_id',
                                'merchants.name',
                                'merchants.description',
                                'merchants.phone',
                                'merchants.floor',
                                'merchants.unit',
                                'merchants.url',
                                'merchants.object_type',
                                'merchants.created_at',
                                'merchants.updated_at',
                                'merchants.mobile_default_language',
                                'merchants.gender',
                                'merchants.disable_ads',
                                'merchants.disable_ymal',
                                'merchants.gender',
                                'media.path',
                                'media.cdn_url',
                                DB::raw("x({$prefix}merchant_geofences.position) as latitude"),
                                DB::raw("y({$prefix}merchant_geofences.position) as longitude"),
                                DB::raw('oms.merchant_id as mall_id'),
                                DB::raw('oms.name as mall_name'),
                                DB::raw('oms.city'),
                                DB::raw('oms.province'),
                                DB::raw('oms.country'),
                                DB::raw('oms.address_line1 as address'),
                                DB::raw('oms.operating_hours'))
                            ->join(DB::raw("(
                                select merchant_id, name, status, parent_id, city,
                                       province, country, address_line1, operating_hours
                                from {$prefix}merchants
                                where status = 'active'
                                    and object_type = 'mall'
                                ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.media_name_long', '=', DB::raw("'retailer_logo_orig'"));
                                $q->on('media.object_id', '=', 'merchants.merchant_id');
                            })
                            ->leftJoin('merchant_geofences', function($q) {
                                $q->on('merchant_geofences.merchant_id', '=', 'merchants.parent_id');
                            })
                            ->whereRaw("{$prefix}merchants.status = 'active'")
                            ->whereRaw("oms.status = 'active'")
                            ->where('merchants.name', '=', $storeName)
                            ->whereRaw("oms.country = '{$countryName}'")
                            ->orderBy('merchants.created_at', 'asc')
                            ->get();

            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index'),
                'type' => Config::get('orbit.elasticsearch.indices.stores.type'),
                'body' => [
                    'query' => [
                        'filtered' => [
                            'filter' => [
                                'and' => [
                                    [
                                        'match' => [
                                            'name.raw' => $storeName
                                        ]
                                    ],
                                    [
                                        'nested' => [
                                            'path' => 'tenant_detail',
                                            'query' => [
                                                'match' => [
                                                    'tenant_detail.country.raw' => $countryName
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete the store document if exist
            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.stores.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);

                // update suggestion
                $fakeJob = new FakeJob();
                $esQueue = new \Orbit\Queue\Elasticsearch\ESStoreSuggestionUpdateQueue();
                $suggestion = $esQueue->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

                // Update suggestion for mall level
                $esSuggestionMallLevelQueue = new \Orbit\Queue\Elasticsearch\ESStoreMallLevelSuggestionUpdateQueue();
                $suggestionMallLevel = $esSuggestionMallLevelQueue->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

                // update detail
                $esDetail = new \Orbit\Queue\Elasticsearch\ESStoreDetailUpdateQueue();
                $detail = $esDetail->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

                // advert store
                $esAdvert = new \Orbit\Queue\Elasticsearch\ESAdvertStoreUpdateQueue();
                $esAdvertUpdate = $esAdvert->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

            }

            if ($store->isEmpty()) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Store Name %s is not found.', $job->getJobId(), $storeName)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index'),
                'type' => Config::get('orbit.elasticsearch.indices.stores.type'),
                'id' => $store[0]->merchant_id,
                'body' => []
            ];

            $categoryIds = array();
            foreach ($store[0]->categories as $category) {
                 $categoryIds[] = $category->category_id;
            }

            $keywords = array();
            foreach ($store[0]->keywords as $keyword) {
                 $keywords[] = $keyword->keyword;
            }

            $productTags = array();
            foreach ($store[0]->product_tags as $product_tag) {
                 $productTags[] = $product_tag->product_tag;
            }

            $partnerIds = array();
            foreach ($store[0]->campaignObjectPartners as $campaignObjectPartner) {
                $partnerIds[] = $campaignObjectPartner->partner_id;
            }

            $translations = array();
            foreach ($store[0]->translations as $translation) {
                $trans = array(
                                "description" => $translation->description,
                                "language_id" => $translation->language_id,
                                "language_code" => $translation->name
                              );
                $translations[] = $trans;
            }

            $featuredGtmScore = 0;
            $preferredGtmScore = 0;
            $featuredGtmType = '';
            $preferredGtmType = '';

            $featuredMallScore = 0;
            $preferredMallScore = 0;
            $featuredMallType = '';
            $preferredMallType = '';

            $tenantDetails = array();
            $storeIds = [];
            foreach ($store as $_store) {

                $advertIds = array();
                foreach ($_store->adverts as $advert) {
                     $advertIds[] = $advert->advert_id;
                }

               $tenantDetail = array(
                    "merchant_id" => $_store->merchant_id,
                    "mall_id"     => $_store->mall_id,
                    "mall_name"   => $_store->mall_name,
                    "city"        => $_store->city,
                    "province"    => $_store->province,
                    "country"     => $_store->country,
                    "advert_ids"  => $advertIds,
                    "address"     => $_store->address,
                    "position"    => [
                        'lon' => $_store->longitude,
                        'lat' => $_store->latitude
                    ],
                    "floor"                => $_store->floor,
                    "unit"                 => $_store->unit,
                    "operating_hours"      => $_store->operating_hours,
                    "logo"                 => $_store->path,
                    "logo_cdn"             => $_store->cdn_url,
                    "url"                  => $_store->url
                );

                if (! in_array($_store->merchant_id, $storeIds)) {
                    $storeIds[] = $_store->merchant_id;
                }

                $tenantDetails[] = $tenantDetail;
            }

            // Query for get total page view per location id
            $mallPageViews = array();
            $gtmPageViews = 0;

            $baseMerchant = BaseMerchant::join('countries', 'countries.country_id', '=', 'base_merchants.country_id')
                                ->where('base_merchants.name' , $storeName)
                                ->where('countries.name', $countryName)
                                ->first();

            if (! empty($baseMerchant)) {
                $totalObjectPageViews = TotalObjectPageView::where('object_id', $baseMerchant->base_merchant_id)
                                            ->where('object_type', 'tenant')
                                            ->get();

                foreach($totalObjectPageViews as $pageView) {
                    if ($pageView->location_id != '0') {
                        $mallPageViews[] = array(
                            "total_views" => $pageView->total_view,
                            "location_id" => $pageView->location_id
                        );
                    } else {
                        $gtmPageViews = $pageView->total_view;
                    }
                }
            }

            $locationRating = array();
            $mallRating = array();

            $queryString['object_type'] = 'store';
            $_storeIds = '?object_id[]=' . implode('&object_id[]=', $storeIds);

            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "review-counters" . $_storeIds;
            $response = $mongoClient->setCustomQuery(TRUE)
                                    ->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecLocation = $response->data;

            if (! empty($listOfRecLocation->records)) {
                // Get city
                $cities = array();
                foreach ($listOfRecLocation->records as $val) {
                    $cities[$val->city] = $val->city;
                }

                $countryRating = array();
                foreach ($listOfRecLocation->records as $rating) {
                    // by country
                    $countryId = $rating->country_id;
                    $countryRating[$countryId]['total'] = (! empty($countryRating[$countryId]['total'])) ? $countryRating[$countryId]['total'] : 0;
                    $countryRating[$countryId]['review'] = (! empty($countryRating[$countryId]['review'])) ? $countryRating[$countryId]['review'] : 0;

                    $countryRating[$countryId]['total'] = $countryRating[$countryId]['total'] + ((double) $rating->average * (double) $rating->counter);
                    $countryRating[$countryId]['review'] = $countryRating[$countryId]['review'] + $rating->counter;

                    $locationRating['rating_' . $countryId] = ((double) $countryRating[$countryId]['total'] / (double) $countryRating[$countryId]['review']) + 0.00001;
                    $locationRating['review_' . $countryId] = (double) $countryRating[$countryId]['review'];
                }

                // by country and city
                if (! empty($cities)) {
                    foreach ($cities as $key => $city) {
                        $totalReview = 0;
                        $totalRating = 0;
                        $averageRating = 0;
                        foreach ($listOfRecLocation->records as $record) {
                            if ($city == $record->city) {
                                $totalReview = $totalReview + $record->counter;
                                $totalRating = $totalRating + ((double) $record->average * (double) $record->counter);

                                // Prevent for division by zero
                                if ($totalReview != 0) {
                                    $averageRating = $totalRating / $totalReview;
                                }

                                $locationRating['review_' . $record->country_id . '_' . str_replace(" ", "_", trim(strtolower($city), " "))] = $totalReview;
                                $locationRating['rating_' . $record->country_id . '_' . str_replace(" ", "_", trim(strtolower($city), " "))] = $averageRating;
                            }
                        }
                    }
                }
            }

            // get rating by mall
            $endPoint = "mall-review-counters" . $_storeIds;
            $response = $mongoClient->setCustomQuery(TRUE)
                                    ->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecMall = $response->data;
            if(! empty($listOfRecMall->records)) {
                foreach ($listOfRecMall->records as $rating) {
                    $mallRating['rating_' . $rating->location_id] = $rating->average + 0.00001;
                    $mallRating['review_' . $rating->location_id] = $rating->counter;
                }
            }

            $baseStore = BaseStore::where('base_store_id', $store[0]->merchant_id)->first();
            $baseMerchantId = null;
            if (! empty($baseStore)) {
                $baseMerchantId = $baseStore->base_merchant_id;
            }

            $body = [
                'merchant_id'          => $store[0]->merchant_id,
                'name'                 => $store[0]->name,
                'description'          => $store[0]->description,
                'phone'                => $store[0]->phone,
                'logo'                 => $store[0]->path,
                'logo_cdn'             => $store[0]->cdn_url,
                'object_type'          => $store[0]->object_type,
                'default_lang'         => $store[0]->mobile_default_language,
                'gender'               => $store[0]->gender,
                'disable_ads'          => $store[0]->disable_ads,
                'disable_ymal'         => $store[0]->disable_ymal,
                'category'             => $categoryIds,
                'keywords'             => $keywords,
                'product_tags'         => $productTags,
                'partner_ids'          => $partnerIds,
                'created_at'           => date('Y-m-d', strtotime($store[0]->created_at)) . 'T' . date('H:i:s', strtotime($store[0]->created_at)) . 'Z',
                'updated_at'           => date('Y-m-d', strtotime($store[0]->updated_at)) . 'T' . date('H:i:s', strtotime($store[0]->updated_at)) . 'Z',
                'tenant_detail_count'  => count($store),
                'translation'          => $translations,
                'tenant_detail'        => $tenantDetails,
                'gtm_page_views'       => $gtmPageViews,
                'mall_page_views'      => $mallPageViews,
                'featured_gtm_score'   => $featuredGtmScore,
                'featured_mall_score'  => $featuredMallScore,
                'preferred_gtm_score'  => $preferredGtmScore,
                'preferred_mall_score' => $preferredMallScore,
                'featured_gtm_type'    => $featuredGtmType,
                'featured_mall_type'   => $featuredMallType,
                'preferred_gtm_type'   => $preferredGtmType,
                'preferred_mall_type'  => $preferredMallType,
                'location_rating'      => $locationRating,
                'mall_rating'          => $mallRating,
                'base_merchant_id'     => $baseMerchantId,
                'lowercase_name'       => str_replace(" ", "_", strtolower($store[0]->name))
            ];

            $params['body'] = $body;
            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // update suggestion
            $fakeJob = new FakeJob();
            $esSuggetion = new \Orbit\Queue\Elasticsearch\ESStoreSuggestionUpdateQueue();
            $suggestion = $esSuggetion->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

            // update detail
            $esDetail = new \Orbit\Queue\Elasticsearch\ESStoreDetailUpdateQueue();
            $detail = $esDetail->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

            $esAdvertQueue = new \Orbit\Queue\Elasticsearch\ESAdvertStoreUpdateQueue();
            $advertUpdate = $esAdvertQueue->fire($fakeJob, ['name' => $storeName, 'country' => $countryName]);

            if ($updateRelated) {
                // update es coupon, news, and promotion
                $this->updateESCoupon($store);
                $this->updateESNews($store);
                $this->updateESPromotion($store);
            }

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Store ID : %s; Store Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
                                $store[0]->merchant_id,
                                $store[0]->name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::info($message);
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

    protected function updateESCoupon($stores) {
        foreach ($stores as $key => $store) {
            $fakeJob = new FakeJob();

            // find coupon relate with tenant to update ESCoupon
            // check coupon before update elasticsearch
            $prefix = DB::getTablePrefix();
            $coupons = \Coupon::excludeDeleted('promotions')
                        ->select(DB::raw("
                            {$prefix}promotions.promotion_id,
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                FROM {$prefix}promotion_retailer opt
                                                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                            )
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END)
                            END AS campaign_status,
                            COUNT({$prefix}issued_coupons.issued_coupon_id) as available
                        "))
                        ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                        ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                        ->leftJoin('issued_coupons', function($q) {
                            $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                                ->where('issued_coupons.status', '=', "available");
                        })
                        ->join('promotion_retailer', function($q) {
                            $q->on('promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                              ->on('promotion_retailer.object_type', '=', DB::raw("'tenant'"));
                        })
                        ->where('promotion_retailer.retailer_id', '=', $store->merchant_id)
                        ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                        ->whereRaw("{$prefix}promotions.is_visible = 'Y'")
                        ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                        ->groupBy('promotions.promotion_id')
                        ->get();

            foreach ($coupons as $key => $coupon) {
                if ($coupon->campaign_status === 'stopped' || $coupon->campaign_status === 'expired' || $coupon->available === 0) {
                    // Notify the queueing system to delete Elasticsearch document
                    $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponDeleteQueue();
                    $response = $esQueue->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);

                    $esAdvertCouponQueue = new \Orbit\Queue\Elasticsearch\ESAdvertCouponDeleteQueue();
                    $response = $esAdvertCouponQueue->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);

                    $esCouponSuggestionDelete = new \Orbit\Queue\Elasticsearch\ESCouponSuggestionDeleteQueue();
                    $doESCouponSuggestionDelete = $esCouponSuggestionDelete->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);
                } else {
                    // Notify the queueing system to update Elasticsearch document
                    $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponUpdateQueue();
                    $response = $esQueue->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);
                }
            }
        }
    }

    protected function updateESNews($stores) {
        foreach ($stores as $key => $store) {
            $fakeJob = new FakeJob();

            // find news relate with tenant to update ESNews
            // check news before update elasticsearch
            $prefix = DB::getTablePrefix();

            $news = \News::excludeDeleted('news')
                    ->select(DB::raw("
                        {$prefix}news.news_id,
                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                        THEN {$prefix}campaign_status.campaign_status_name
                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}news_merchant onm
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE onm.news_id = {$prefix}news.news_id)
                       THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                    "))
                    ->join('news_merchant', function($q) {
                            $q->on('news_merchant.news_id', '=', 'news.news_id')
                              ->on('news_merchant.object_type', '=', DB::raw("'retailer'"));
                      })
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where('news_merchant.merchant_id', '=', $store->merchant_id)
                    ->where('news.object_type', '=', 'news')
                    ->get();

            if (!(count($news) < 1)) {
                foreach ($news as $_news) {

                    if ($_news->campaign_status === 'stopped' || $_news->campaign_status === 'expired') {
                        // Notify the queueing system to delete Elasticsearch document
                        $esQueue = new \Orbit\Queue\Elasticsearch\ESNewsDeleteQueue();
                        $response = $esQueue->fire($fakeJob, ['news_id' => $_news->news_id]);
                    } else {
                        // Notify the queueing system to update Elasticsearch document
                        $esQueue = new \Orbit\Queue\Elasticsearch\ESNewsUpdateQueue();
                        $response = $esQueue->fire($fakeJob, ['news_id' => $_news->news_id]);
                    }
                }
            }
        }
    }

    protected function updateESPromotion($stores) {
        foreach ($stores as $key => $store) {
            $fakeJob = new FakeJob();

            // find promotion relate with tenant to update ESPromotion
            // check promotion before update elasticsearch
            $prefix = DB::getTablePrefix();

            $promotions = \News::excludeDeleted('news')
                    ->select(DB::raw("
                        {$prefix}news.news_id,
                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                        THEN {$prefix}campaign_status.campaign_status_name
                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}news_merchant onm
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE onm.news_id = {$prefix}news.news_id)
                       THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                    "))
                    ->join('news_merchant', function($q) {
                            $q->on('news_merchant.news_id', '=', 'news.news_id')
                              ->on('news_merchant.object_type', '=', DB::raw("'retailer'"));
                      })
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where('news_merchant.merchant_id', '=', $store->merchant_id)
                    ->where('news.object_type', '=', 'promotion')
                    ->get();

            if (!(count($promotions) < 1)) {
                foreach ($promotions as $_promotions) {

                    if ($_promotions->campaign_status === 'stopped' || $_promotions->campaign_status === 'expired') {
                        // Notify the queueing system to delete Elasticsearch document
                        $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionDeleteQueue();
                        $response = $esQueue->fire($fakeJob, ['news_id' => $_promotions->news_id]);
                    } else {
                        // Notify the queueing system to update Elasticsearch document
                        $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionUpdateQueue();
                        $response = $esQueue->fire($fakeJob, ['news_id' => $_promotions->news_id]);
                    }
                }
            }
        }
    }
}
