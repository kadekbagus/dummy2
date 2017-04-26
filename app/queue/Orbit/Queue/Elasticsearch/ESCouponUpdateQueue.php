<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when coupon has been updated.
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Coupon;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Orbit\FakeJob;
use Log;

class ESCouponUpdateQueue
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

        $couponId = $data['coupon_id'];
        $coupon = Coupon::with(
                            'translations.media_orig',
                            'campaignLocations.categories',
                            'esCampaignLocations.geofence',
                            'campaignObjectPartners',
                            'adverts.media_orig'
                        )
                    ->with (['keywords' => function ($q) {
                                $q->groupBy('keyword');
                            }])
                    ->select(DB::raw("
                        {$prefix}promotions.*,
                        {$prefix}campaign_account.mobile_default_language,
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
                    ->leftJoin('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                    ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                    ->leftJoin('issued_coupons', function($q) {
                        $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                            ->where('issued_coupons.status', '=', "available");
                    })
                    ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                    ->where('promotions.promotion_id', $couponId)
                    ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                    ->whereRaw("{$prefix}promotions.is_visible = 'Y'")
                    ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                    ->orderBy('promotions.promotion_id', 'asc')
                    ->first();

        if (! is_object($coupon)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Coupon ID %s is not found.', $job->getJobId(), $couponId)
            ];
        }

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupons.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $coupon->promotion_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupons.type'),
                'id' => $coupon->promotion_id,
                'body' => []
            ];

            $categoryIds = array();
            foreach ($coupon->campaignLocations as $campaignLocation) {
                foreach ($campaignLocation->categories as $category) {
                    $categoryIds[] = $category->category_id;
                }
            }

            $linkToTenants = array();
            foreach($coupon->esCampaignLocations as $esCampaignLocation) {
                $linkToTenant = array(
                    "merchant_id" => $esCampaignLocation->merchant_id,
                    "parent_id" => $esCampaignLocation->parent_id,
                    "name" => $esCampaignLocation->name,
                    "mall_name" => $esCampaignLocation->mall_name,
                    "object_type" => $esCampaignLocation->object_type,
                    "city" => $esCampaignLocation->city,
                    "province" => $esCampaignLocation->province,
                    "country" => $esCampaignLocation->country,
                    "position" => [
                        'lon' => $esCampaignLocation->geofence->longitude,
                        'lat' => $esCampaignLocation->geofence->latitude
                    ]
                );

                $linkToTenants[] = $linkToTenant;
            }

            $keywords = array();
            foreach ($coupon->keywords as $keyword) {
                $keywords[] = $keyword->keyword;
            }

            $partnerIds = array();
            $partnerTokens = array();
            foreach ($coupon->campaignObjectPartners as $campaignObjectPartner) {
                $partnerIds[] = $campaignObjectPartner->partner_id;
                if (! empty($campaignObjectPartner->token)) {
                    $partnerTokens[] = $campaignObjectPartner->token;
                }
            }

            $advertIds = array();
            foreach ($coupon->adverts as $advertCollection) {
                $advertIds[] = $advertCollection->advert_id;
            }

            $translations = array();
            foreach ($coupon->translations as $translationCollection) {
                $translation = array(
                    'name' => $translationCollection->promotion_name,
                    'description' => $translationCollection->description,
                    'language_id' => $translationCollection->merchant_language_id,
                    'language_code' => $translationCollection->name,
                    'image_url' => NULL
                );

                foreach ($translationCollection->media_orig as $media) {
                    $translation['image_url'] = $media->path;
                    $translation['image_cdn_url'] = $media->cdn_url;
                }
                $translations[] = $translation;
            }

            $body = [
                'promotion_id'    => $coupon->promotion_id,
                'name'            => $coupon->promotion_name,
                'description'     => $coupon->description,
                'object_type'     => 'coupon',
                'begin_date'      => date('Y-m-d', strtotime($coupon->begin_date)) . 'T' . date('H:i:s', strtotime($coupon->begin_date)) . 'Z',
                'end_date'        => date('Y-m-d', strtotime($coupon->end_date)) . 'T' . date('H:i:s', strtotime($coupon->end_date)) . 'Z',
                'updated_at'      => date('Y-m-d', strtotime($coupon->updated_at)) . 'T' . date('H:i:s', strtotime($coupon->updated_at)) . 'Z',
                'status'          => $coupon->status,
                'available'       => $coupon->available,
                'campaign_status' => $coupon->campaign_status,
                'is_all_gender'   => $coupon->is_all_gender,
                'is_all_age'      => $coupon->is_all_age,
                'default_lang'    => $coupon->mobile_default_language,
                'category_ids'    => $categoryIds,
                'translation'     => $translations,
                'keywords'        => $keywords,
                'partner_ids'     => $partnerIds,
                'partner_tokens'  => $partnerTokens,
                'advert_ids'      => $advertIds,
                'link_to_tenant'  => $linkToTenants,
                'is_exclusive'    => ! empty($coupon->is_exclusive) ? $coupon->is_exclusive : 'N',
            ];

            if ($response_search['hits']['total'] > 0) {
                $params['body'] = [
                    'doc' => $body
                ];
                $response = $this->poster->update($params);
            } else {
                $params['body'] = $body;
                $response = $this->poster->index($params);
            }

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $fakeJob = new FakeJob();
            $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['coupon_id' => $couponId]);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Coupon ID: %s; Coupon Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'],
                                $coupon->promotion_id,
                                $coupon->promotion_name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'],
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
}