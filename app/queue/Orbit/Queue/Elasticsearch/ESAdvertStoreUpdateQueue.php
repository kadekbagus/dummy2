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
use AdvertLocation;
use BaseMerchant;
use BaseStore;
use TotalObjectPageView;
use CampaignLocation;
use AdvertSlotLocation;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Queue;
use Orbit\FakeJob;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;

class ESAdvertStoreUpdateQueue
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

            //Get now time
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

            $advertData = Advert::select('adverts.advert_id', 'advert_placements.placement_type', 'advert_placements.placement_order', 'adverts.link_object_id', 'adverts.start_date', 'adverts.end_date', 'adverts.status', 'adverts.is_all_location')
                                    ->join('advert_link_types', 'adverts.advert_link_type_id', '=', 'advert_link_types.advert_link_type_id')
                                    ->join('advert_placements', 'advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id')
                                    ->whereIn('advert_placements.placement_type', ['preferred_list_regular', 'preferred_list_large', 'featured_list'])
                                    ->where('advert_link_types.advert_type', 'store')
                                    ->where('adverts.end_date', '>=', date("Y-m-d", strtotime($dateTime)))
                                    ->whereRaw("{$prefix}adverts.link_object_id IN (SELECT {$prefix}merchants.merchant_id
                                        FROM {$prefix}merchants
                                        INNER JOIN
                                            ( SELECT merchant_id, name, status, parent_id, city, province, country, address_line1, operating_hours FROM {$prefix}merchants where status = 'active' AND object_type = 'mall' ) AS oms ON oms.merchant_id = {$prefix}merchants.parent_id
                                        WHERE {$prefix}merchants.object_type = 'tenant'
                                            AND {$prefix}merchants.name = {$this->quote($storeName)}
                                            AND oms.country = {$this->quote($countryName)})")
                                    ->groupBy('adverts.advert_id')
                                    ->orderBy('adverts.advert_id')
                                    ->get();

            if ($advertData->isEmpty()) {
                $job->delete();
                $message = sprintf('[Job ID: `%s`] Advert for Store name %s is not found.', $job->getJobId(), $storeName);
                Log::info($message);
                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Advert for Store name %s is not found.', $job->getJobId(), $storeName)
                ];
            }

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
                                'merchants.disable_ads',
                                'merchants.disable_ymal',
                                'merchants.mobile_default_language',
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

            // Delete ES advert when $store not exist and ES store advert is exist
            if (! $advertData->isEmpty() && $store->isEmpty() ) {
                foreach ($advertData as $adverts) {
                    $params_search = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_stores.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.advert_stores.type'),
                        'body' => [
                            'query' => [
                                'match' => [
                                    '_id' => $adverts->advert_id
                                ]
                            ]
                        ]
                    ];

                    $response_search = $this->poster->search($params_search);

                    if ($response_search['hits']['total'] > 0) {
                        $params = [
                            'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_stores.index'),
                            'type' => Config::get('orbit.elasticsearch.indices.advert_stores.type'),
                            'id' => $response_search['hits']['hits'][0]['_id']
                        ];

                        $response = $this->poster->delete($params);
                    }

                }
            }

            if ($store->isEmpty()) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Store Name %s is not found.', $job->getJobId(), $storeName)
                ];
            }

            foreach ($advertData as $adverts) {
                // check exist elasticsearch index
                $params_search = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_stores.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.advert_stores.type'),
                    'body' => [
                        'query' => [
                            'match' => [
                                '_id' => $adverts->advert_id
                            ]
                        ]
                    ]
                ];

                $response_search = $this->poster->search($params_search);

                $response = NULL;
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_stores.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.advert_stores.type'),
                    'id' => $adverts->advert_id,
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

                //advert slot for featured advert
                $featuredSlotGTM = array();
                $featuredSlotMall = array();
                if ($adverts->placement_type === 'featured_list') {
                    $slots = AdvertSlotLocation::where('advert_id', $adverts->advert_id)->where('status', 'active')->get();
                    foreach ($slots as $slot) {
                        if ($slot->location_id === '0') {
                            $cityName = $slot->country_id . '_' . str_replace(" ", "_", trim(strtolower($slot->city), " "));
                            $featuredSlotGTM[$cityName] = $slot->slot_number;
                        } else {
                            $featuredSlotMall[$slot->location_id] = $slot->slot_number;
                        }
                    }
                }

                //advert location
                $advertLocationIds = array();
                if ($adverts->is_all_location === 'Y') {
                    $advertLocation = CampaignLocation::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as location_id"))
                                               ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', 'merchants.parent_id')
                                               ->where('merchants.object_type', 'tenant')
                                               ->where('merchants.status', '!=', 'deleted')
                                               ->where('merchants.name', '=', $storeName)
                                               ->groupBy('location_id')
                                               ->get();

                    // add gtm location manually
                    $advertLocationIds[] = '0';
                    if ($adverts->placement_type === 'featured_list') {
                        if ($adverts->placement_order > $featuredGtmScore) {
                            $featuredGtmScore = $adverts->placement_order;
                            $featuredGtmType = $adverts->placement_type;
                        }
                    } else {
                        if ($adverts->placement_order > $preferredGtmScore) {
                            $preferredGtmScore = $adverts->placement_order;
                            $preferredGtmType = $adverts->placement_type;
                        }
                    }
                } else {
                    $advertLocation = AdvertLocation::select('location_id')
                                                ->where('advert_id', $adverts->advert_id)
                                                ->get();
                }

                $tenantDetails = array();
                foreach ($advertLocation as $location) {
                    if ($location->location_id === '0') {
                        // gtm
                        if ($adverts->placement_type === 'featured_list') {
                            if ($adverts->placement_order > $featuredGtmScore) {
                                $featuredGtmScore = $adverts->placement_order;
                                $featuredGtmType = $adverts->placement_type;
                            }
                        } else {
                            if ($adverts->placement_order > $preferredGtmScore) {
                                $preferredGtmScore = $adverts->placement_order;
                                $preferredGtmType = $adverts->placement_type;
                            }
                        }
                    } else {
                        // mall
                        if ($adverts->placement_type === 'featured_list') {
                            if ($adverts->placement_order > $featuredMallScore) {
                                $featuredMallScore = $adverts->placement_order;
                                $featuredMallType = $adverts->placement_type;
                            }
                        } else {
                            if ($adverts->placement_order > $preferredMallScore) {
                                $preferredMallScore = $adverts->placement_order;
                                $preferredMallType = $adverts->placement_type;
                            }
                        }
                    }

                    $advertLocationIds[] = $location->location_id;
                }

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

                        // by country and city
                        $locationRating['rating_' . $rating->country_id . '_' . str_replace(" ", "_", trim(strtolower($rating->city), " "))] = $rating->average + 0.00001;
                        $locationRating['review_' . $rating->country_id . '_' . str_replace(" ", "_", trim(strtolower($rating->city), " "))] = $rating->counter;
                    }
                }

                // get rating by mall
                $endPoint = "mall-review-counters" . $_storeIds;
                $response = $mongoClient->setCustomQuery(TRUE)
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
                    'disable_ads'          => $store[0]->disable_ads,
                    'disable_ymal'         => $store[0]->disable_ymal,
                    'category'             => $categoryIds,
                    'keywords'             => $keywords,
                    'product_tags'         => $productTags,
                    'partner_ids'          => $partnerIds,
                    'created_at'           => date('Y-m-d', strtotime($store[0]->created_at)) . 'T' . date('H:i:s', strtotime($store[0]->created_at)) . 'Z',
                    'updated_at'           => date('Y-m-d', strtotime($store[0]->updated_at)) . 'T' . date('H:i:s', strtotime($store[0]->updated_at)) . 'Z',
                    'advert_start_date'    => date('Y-m-d', strtotime($adverts->start_date)) . 'T' . date('H:i:s', strtotime($adverts->start_date)) . 'Z',
                    'advert_end_date'      => date('Y-m-d', strtotime($adverts->end_date)) . 'T' . date('H:i:s', strtotime($adverts->end_date)) . 'Z',
                    'advert_status'        => $adverts->status,
                    'tenant_detail_count'  => count($store),
                    'translation'          => $translations,
                    'tenant_detail'        => $tenantDetails,
                    'gtm_page_views'       => $gtmPageViews,
                    'mall_page_views'      => $mallPageViews,
                    'featured_gtm_score'   => $featuredGtmScore,
                    'preferred_gtm_score'  => $preferredGtmScore,
                    'featured_gtm_type'    => $featuredGtmType,
                    'preferred_gtm_type'   => $preferredGtmType,
                    'featured_mall_score'  => $featuredMallScore,
                    'preferred_mall_score' => $preferredMallScore,
                    'featured_mall_type'   => $featuredMallType,
                    'preferred_mall_type'  => $preferredMallType,
                    'advert_location_ids'  => $advertLocationIds,
                    'advert_type'          => $adverts->placement_type,
                    'location_rating'      => $locationRating,
                    'mall_rating'          => $mallRating,
                    'featured_slot_gtm'    => $featuredSlotGTM,
                    'featured_slot_mall'   => $featuredSlotMall,
                    'base_merchant_id'     => $baseMerchantId,
                    'lowercase_name'       => str_replace(" ", "_", strtolower($store[0]->name))
                ];

                $params['body'] = $body;
                $response = $this->poster->index($params);

                // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}