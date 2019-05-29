<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when mall has been updated.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Mall;
use TotalObjectPageView;
use ObjectPartner;
use DB;
use MerchantGeofence;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Queue;
use Orbit\FakeJob;
use Orbit\Helper\MongoDB\Client as MongoClient;

class ESMallUpdateQueue
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
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data[
     *                    'merchant_id' => NUM // Mall ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $mallId = $data['mall_id'];
        $prefix = DB::getTablePrefix();
        $mongoConfig = Config::get('database.mongodb');
        $mall = Mall::with('country', 'mediaMapOrig')
                    ->leftJoin(DB::raw("(select * from {$prefix}media where media_name_long = 'mall_logo_orig') as med"), DB::raw("med.object_id"), '=', 'merchants.merchant_id')
                    ->where('merchants.status', '!=', 'deleted')
                    ->where('merchants.merchant_id', $mallId)
                    ->first();

        $object_partner = ObjectPartner::where('object_type', 'mall')->where('object_id', $mall->merchant_id)->lists('partner_id');

        if (! is_object($mall)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Mall ID %s is not found.', $job->getJobId(), $mallId)
            ];
        }

        $maps_url = $mall->mediaMapOrig->lists('path');
        $maps_cdn_url = $mall->mediaMapOrig->lists('cdn_url');

        $esConfig = Config::get('orbit.elasticsearch');
        $geofence = MerchantGeofence::getDefaultValueForAreaAndPosition($mallId);
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
        $updateRelated = (empty($data['update_related']) ? FALSE : $data['update_related']);

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $mall->merchant_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'id' => $mall->merchant_id,
                'body' => []
            ];

            // get rating by location
            $locationRating = array();
            $queryString = [
                'object_id'   => $mall->merchant_id,
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

            $esBody = [
                'merchant_id'     => $mall->merchant_id,
                'name'            => $mall->name,
                'description'     => $mall->description,
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
                'lowercase_name'  => str_replace(" ", "_", strtolower($mall->name))
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

            // update suggestion
            $fakeJob = new FakeJob();
            $esQueue = new \Orbit\Queue\Elasticsearch\ESMallSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['mall_id' => $mallId]);

            // Update advert
            $esAdvertQueue = new \Orbit\Queue\Elasticsearch\ESAdvertMallUpdateQueue();
            $advertUpdate = $esAdvertQueue->fire($fakeJob, ['mall_id' => $mallId]);

            if ($updateRelated) {
                // update es coupon, news, and promotion
                $this->updateESCoupon($mall);
                $this->updateESNews($mall);
                $this->updateESPromotion($mall);
                $this->updateESStore($mall, $mall->Country->name);
            }

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type']);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type'],
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

    protected function updateESCoupon($mall) {
        $fakeJob = new FakeJob();

        // find coupon relate with mall to update ESCoupon
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
                    ->join('promotion_retailer as pr', DB::raw('pr.promotion_id'), '=', 'promotions.promotion_id')
                    ->leftJoin('merchants as mp', function ($q) {
                        $q->on(DB::raw('mp.merchant_id'), '=', DB::raw('pr.retailer_id'))
                          ->on(DB::raw('mp.object_type'), '=', DB::raw("'tenant'"));
                    })
                    ->whereRaw("CASE WHEN pr.object_type = 'mall' THEN pr.retailer_id ELSE mp.parent_id END = '{$mall->merchant_id}'")
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
            } else {
                // Notify the queueing system to update Elasticsearch document
                $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponUpdateQueue();
                $response = $esQueue->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);
            }
        }
    }

    protected function updateESNews($mall) {
        $fakeJob = new FakeJob();

        // find news relate with mall to update ESnews
        // check news before update elasticsearch
        $prefix = DB::getTablePrefix();
        // check news data related to the mall, for update or delete elasticsearch news
        $news = \News::select(DB::raw("
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
                ->excludeDeleted('news')
                ->join('news_merchant as nm', DB::raw('nm.news_id'), '=', 'news.news_id')
                ->leftJoin('merchants as mp', function ($q) {
                        $q->on(DB::raw('mp.merchant_id'), '=', DB::raw('nm.merchant_id'))
                          ->on(DB::raw('mp.object_type'), '=', DB::raw("'tenant'"));
                  })
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                ->whereRaw("CASE WHEN nm.object_type = 'mall' THEN nm.merchant_id ELSE mp.parent_id END = '{$mall->merchant_id}'")
                ->where('news.object_type', '=', 'news')
                ->get();

        if (!(count($news) < 1)) {
            foreach ($news as $_news) {

                if ($_news->campaign_status === 'stopped' || $_news->campaign_status === 'expired') {
                    // Notify the queueing system to delete Elasticsearch document
                    $esQueue = new \Orbit\Queue\Elasticsearch\ESNewsDeleteQueue();
                    $response = $esQueue->fire($fakeJob, ['news_id' => $_news->news_id]);
                } else {
                    // Notify the queueing system to delete Elasticsearch document
                    $esQueue = new \Orbit\Queue\Elasticsearch\ESNewsUpdateQueue();
                    $response = $esQueue->fire($fakeJob, ['news_id' => $_news->news_id]);
                }
            }
        }
    }

    protected function updateESPromotion($mall) {
        $fakeJob = new FakeJob();

        // find news relate with mall to update ESnews
        // check news before update elasticsearch
        $prefix = DB::getTablePrefix();
        // check promotions data related to the mall, for update or delete elasticsearch promotions
        $promotions = \News::select(DB::raw("
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
                ->excludeDeleted('news')
                ->join('news_merchant as nm', DB::raw('nm.news_id'), '=', 'news.news_id')
                ->leftJoin('merchants as mp', function ($q) {
                        $q->on(DB::raw('mp.merchant_id'), '=', DB::raw('nm.merchant_id'))
                          ->on(DB::raw('mp.object_type'), '=', DB::raw("'tenant'"));
                  })
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                ->whereRaw("CASE WHEN nm.object_type = 'mall' THEN nm.merchant_id ELSE mp.parent_id END = '{$mall->merchant_id}'")
                ->where('news.object_type', '=', 'promotion')
                ->get();

        if (!(count($promotions) < 1)) {
            foreach ($promotions as $_promotions) {

                if ($_promotions->campaign_status === 'stopped' || $_promotions->campaign_status === 'expired') {
                    // Notify the queueing system to delete Elasticsearch document
                    $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionDeleteQueue();
                    $response = $esQueue->fire($fakeJob, ['news_id' => $_promotions->news_id]);
                } else {
                    // Notify the queueing system to delete Elasticsearch document
                    $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionUpdateQueue();
                    $response = $esQueue->fire($fakeJob, ['news_id' => $_promotions->news_id]);
                }
            }
        }
    }

    protected function updateESStore($mall, $country) {
        $fakeJob = new FakeJob();

        $prefix = DB::getTablePrefix();

        // check all store that belongs to the mall and then update store index on es
        $store = \Tenant::select('merchants.name')
                        ->excludeDeleted('merchants')
                        ->join(DB::raw("(
                                select merchant_id
                                from {$prefix}merchants
                                where status = 'active'
                                and object_type = 'mall'
                            ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->where('merchants.status', '=', 'active')
                        ->where(DB::raw('oms.merchant_id'), '=', $mall->merchant_id)
                        ->get();


        if (!$store->isEmpty()) {
            foreach ($store as $_store) {
                // Notify the queueing system to delete Elasticsearch document
                $esQueue = new \Orbit\Queue\Elasticsearch\ESStoreUpdateQueue();
                $response = $esQueue->fire($fakeJob, ['name' => $_store->name, 'country' => $country]);
            }
        }
    }
}