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
use Mall;
use MerchantGeofence;
use ObjectPartner;
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

class ESAdvertMallUpdateQueue
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
        $prefix = DB::getTablePrefix();
        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
        $updateRelated = (empty($data['update_related']) ? FALSE : $data['update_related']);
        $mongoConfig = Config::get('database.mongodb');

        $mallId = $data['mall_id'];

        //Get now time
        $timezone = 'Asia/Jakarta'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

        $advertData = Advert::select('adverts.advert_id', 'advert_placements.placement_type', 'advert_placements.placement_order', 'adverts.link_object_id', 'adverts.start_date', 'adverts.end_date', 'adverts.status', 'adverts.is_all_location')
                                ->join('advert_link_types', 'adverts.advert_link_type_id', '=', 'advert_link_types.advert_link_type_id')
                                ->join('advert_placements', 'advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id')
                                ->whereIn('advert_placements.placement_type', ['top_banner', 'footer_banner', 'preferred_list_regular', 'preferred_list_large', 'featured_list'])
                                ->where('advert_link_types.advert_type', 'mall')
                                ->where('adverts.end_date', '>=', date("Y-m-d", strtotime($dateTime)))
                                ->whereRaw("{$prefix}adverts.link_object_id IN (SELECT {$prefix}merchants.merchant_id
                                    FROM {$prefix}merchants
                                    WHERE {$prefix}merchants.object_type = 'mall'
                                        AND {$prefix}merchants.merchant_id = '{$mallId}'
                                        AND {$prefix}merchants.status = 'active')")
                                ->groupBy('adverts.advert_id')
                                ->orderBy('adverts.advert_id')
                                ->get();

        if ($advertData->isEmpty()) {
            $job->delete();
            $message = sprintf('[Job ID: `%s`] Advert for Mall ID %s is not found.', $job->getJobId(), $mallId);
            Log::info($message);
            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Advert for Mall ID %s is not found.', $job->getJobId(), $mallId)
            ];
        }

        try {

            foreach($advertData as $adverts) {
                $mall = Mall::select(DB::raw("{$prefix}merchants.*, med.*, social_media_code, social_media_uri"))
                    ->with('country', 'mediaMapOrig')
                    ->leftJoin(DB::raw("(select * from {$prefix}media where media_name_long = 'mall_logo_orig') as med"), DB::raw("med.object_id"), '=', 'merchants.merchant_id')
                    ->leftJoin('merchant_social_media','merchant_social_media.merchant_id','=','merchants.merchant_id')
                    ->leftJoin('social_media', function($q){
                            $q->on('social_media.social_media_id', '=', 'merchant_social_media.social_media_id')
                              ->on('social_media.social_media_name', '=', DB::raw('"facebook"'));
                      })
                    ->where('merchants.status', '!=', 'deleted')
                    ->where('merchants.merchant_id', $mallId)
                    ->first();

                $geofence = MerchantGeofence::getDefaultValueForAreaAndPosition($mallId);

                $object_partner = ObjectPartner::where('object_type', 'mall')->where('object_id', $mallId)->lists('partner_id');

                if (! is_object($mall)) {
                    $job->delete();

                    return [
                        'status' => 'fail',
                        'message' => sprintf('[Job ID: `%s`] Mall ID %s is not found.', $job->getJobId(), $mallId)
                    ];
                }

                $maps_url = $mall->mediaMapOrig->lists('path');
                $maps_cdn_url = $mall->mediaMapOrig->lists('cdn_url');

                // check exist elasticsearch index
                $params_search = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_malls.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.advert_malls.type'),
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
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_malls.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.advert_malls.type'),
                    'id' => $adverts->advert_id,
                    'body' => []
                ];

                $featuredGtmScore = 0;
                $preferredGtmScore = 0;
                $featuredGtmType = '';
                $preferredGtmType = '';

                //advert slot for featured advert
                $featuredSlotGTM = array();
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
                $advertLocation = AdvertLocation::select('location_id')
                                            ->where('advert_id', $adverts->advert_id)
                                            ->get();

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
                    }

                    $advertLocationIds[] = $location->location_id;
                }

                // get rating by location
                $locationRating = array();
                $queryString = [
                    'object_id'   => $mallId,
                    'object_type' => 'mall'
                ];

                $mongoClient = MongoClient::create($mongoConfig);
                $endPoint = "review-counters";
                $response = $mongoClient->setQueryString($queryString)
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

                // Query for get total page view per location id
                $totalObjectPageViews = TotalObjectPageView::where('object_id', $mallId)
                                            ->where('object_type', 'mall')
                                            ->sum('total_view');

                // fb_url
                $fb_url = '';
                if (! empty($mall->social_media_code) && ! empty($mall->social_media_uri)) {
                    $fb_url = 'https://www.facebook.com/' . $mall->social_media_uri;
                }

                $esBody = [
                    'merchant_id'     => $mallId,
                    'name'            => $mall->name,
                    'description'     => $mall->description,
                    'website_url'     => $mall->url,
                    'fb_url'          => $fb_url,
                    'address_line'    => trim(implode("\n", [$mall->address_line1, $mall->address_line2, $mall->address_line2])),
                    'city'            => $mall->city,
                    'province'        => $mall->province,
                    'country'         => $mall->Country->name,
                    'phone'           => $mall->phone,
                    'operating_hours' => $mall->operating_hours,
                    'object_type'     => $mall->object_type,
                    'logo_url'        => $mall->path,
                    'logo_cdn_url'    => $mall->cdn_url,
                    'maps_url'        => $maps_url,
                    'maps_cdn_url'    => $maps_cdn_url,
                    'status'          => $mall->status,
                    'ci_domain'       => $mall->ci_domain,
                    'is_subscribed'   => $mall->is_subscribed,
                    'disable_ads'     => $mall->disable_ads,
                    'disable_ymal'    => $mall->disable_ymal,
                    'updated_at'      => date('Y-m-d', strtotime($mall->updated_at)) . 'T' . date('H:i:s', strtotime($mall->updated_at)) . 'Z',
                    'keywords'        => '',
                    'postal_code'     => $mall->postal_code,
                    'position'        => [
                        'lon' => $geofence->longitude,
                        'lat' => $geofence->latitude
                    ],
                    'area' => [
                        'type'        => 'polygon',
                        'coordinates' => $geofence->area
                    ],
                    'gtm_page_views'  => $totalObjectPageViews,
                    'location_rating' => $locationRating,
                    'lowercase_name'  => str_replace(" ", "_", strtolower($mall->name)),

                    // Advert related data...
                    'advert_start_date'    => date('Y-m-d', strtotime($adverts->start_date)) . 'T' . date('H:i:s', strtotime($adverts->start_date)) . 'Z',
                    'advert_end_date'      => date('Y-m-d', strtotime($adverts->end_date)) . 'T' . date('H:i:s', strtotime($adverts->end_date)) . 'Z',
                    'advert_status'        => $adverts->status,
                    'advert_location_ids'  => $advertLocationIds,
                    'advert_type'          => $adverts->placement_type,

                    'featured_gtm_score'   => $featuredGtmScore,
                    'featured_gtm_type'    => $featuredGtmType,
                    'preferred_gtm_score'  => $preferredGtmScore,
                    'preferred_gtm_type'   => $preferredGtmType,

                    'featured_slot_gtm'    => $featuredSlotGTM,
                ];

                if (! empty($object_partner)) {
                    $esBody['partner_ids'] = $object_partner;
                }

                // validation geofence
                if (empty($geofence->area) || empty($geofence->latitude) || empty($geofence->longitude)) {
                    unset($esBody['position']);
                    unset($esBody['area']);
                }

                if ($response_search['hits']['total'] > 0) {
                    $params['body'] = [
                        'doc' => $esBody
                    ];
                    $response = $this->poster->update($params);
                } else {
                    $params['body'] = $esBody;
                    $response = $this->poster->index($params);
                }

                ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

                // Safely delete the object
                $job->delete();

                $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                    $job->getJobId(),
                                    $esConfig['indices']['advert_malls']['index'],
                                    $esConfig['indices']['advert_malls']['type']);
                Log::info($message);
            }

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['advert_malls']['index'],
                                $esConfig['indices']['advert_malls']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::info($message);
            print_r($esBody);
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
