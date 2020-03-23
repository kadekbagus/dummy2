<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when coupon has been updated.
 *
 * @author shelgi <shelgi@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Coupon;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;

class ESCouponSuggestionUpdateQueue
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
        $coupon = Coupon::with('translations', 'country', 'city')
                    ->select(DB::raw("
                        {$prefix}promotions.*,
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
                        $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    })
                    ->where('promotions.promotion_id', $couponId)
                    ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                    ->whereRaw("{$prefix}promotions.is_visible = 'Y'")
                    ->where(function($q) use($prefix) {
                        $q->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                            ->orWhereNull('promotion_rules.rule_type');
                    })
                    ->whereRaw("{$prefix}promotions.status = 'active'")
                    ->having('campaign_status', '=', 'ongoing')
                    ->having('available', '>', 0)
                    ->orderBy('promotions.promotion_id', 'asc')
                    ->first();

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupon_suggestions.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $couponId
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete the coupon suggestion document if the status inactive
            if ($response_search['hits']['total'] > 0 && count($coupon) === 0) {
                $paramsDelete = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.coupon_suggestions.type'),
                    'id' => $couponId
                ];
                $responseDelete = $this->poster->delete($paramsDelete);

                ElasticsearchErrorChecker::throwExceptionOnDocumentError($responseDelete);

                $job->delete();

                $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Doucment in Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type']);
                Log::info($message);

                return [
                    'status' => 'ok',
                    'message' => $message
                ];
            } elseif (count($coupon) === 0) {

                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Coupon ID %s is not found.', $job->getJobId(), $couponId)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupon_suggestions.type'),
                'id' => $coupon->promotion_id,
                'body' => []
            ];

            $country = array();
            foreach($coupon->country as $data) {
                $country[] = $data->country;
            }

            $city = array();
            foreach($coupon->city as $data) {
                $city[] = $data->city;
            }

            $body = [
                'name'    => $coupon->promotion_name,
                'country' => $country,
                'city'    => $city,
                'begin_date' => date('Y-m-d', strtotime($coupon->begin_date)) . 'T' . date('H:i:s', strtotime($coupon->begin_date)) . 'Z',
                'end_date' => date('Y-m-d', strtotime($coupon->end_date)) . 'T' . date('H:i:s', strtotime($coupon->end_date)) . 'Z'
            ];

            foreach ($coupon->translations as $translationCollection) {
                $suggest = array();

                if (! empty($translationCollection->promotion_name) || $translationCollection->promotion_name != '') {
                    // generate input
                    $textName = $translationCollection->promotion_name;
                    $explode = explode(' ', $textName);
                    $count = count($explode);

                    $input = array();
                    for($a = 0; $a < $count; $a++) {
                        $textName = '';
                        for($b = $a; $b < $count; $b++) {
                            $textName .= $explode[$b] . ' ';
                        }
                        $input[] = substr($textName, 0, -1);
                    }

                    $suggest = [
                        'input'   => $input,
                        'output'  => $translationCollection->promotion_name,
                        'payload' => ['id' => $coupon->promotion_id, 'type' => 'coupon']
                    ];

                    switch ($translationCollection->name) {
                        case 'id':
                            $body['suggest_id'] = $suggest;
                            break;

                        case 'en':
                            $body['suggest_en'] = $suggest;
                            break;

                        case 'zh':
                            $body['suggest_zh'] = $suggest;
                            break;

                        case 'ms':
                            $body['suggest_ms'] = $suggest;
                            break;
                    }
                }
            }

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

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Suggestion Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Coupon ID: %s; Coupon Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupon_suggestions']['index'],
                                $esConfig['indices']['coupon_suggestions']['type'],
                                $coupon->promotion_id,
                                $coupon->promotion_name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Suggestion Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupon_suggestions']['index'],
                                $esConfig['indices']['coupon_suggestions']['type'],
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
