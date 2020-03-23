<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when new mall has been created.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Mall;
use ObjectPartner;
use DB;
use MerchantGeofence;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Orbit\FakeJob;

class ESMallCreateQueue
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

        try {
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'id' => $mall->merchant_id,
                'body' => [
                    'merchant_id'     => $mall->merchant_id,
                    'name'            => $mall->name,
                    'description'     => $mall->description,
                    'address_line'    => trim(implode("\n", [$mall->address_line1, $mall->address_line2, $mall->address_line3])),
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
                    'keywords'        => '',
                    'postal_code'     => $mall->postal_code,
                    'position'        => [
                        'lon' => $geofence->longitude,
                        'lat' => $geofence->latitude
                    ],
                    'area' => [
                        'type'        => 'polygon',
                        'coordinates' => $geofence->area
                    ]
                ]
            ];

            if (! empty($object_partner)) {
                $esBody['partner_ids'] = $object_partner;
            }

            // validation geofence
            if (empty($geofence->area) || empty($geofence->latitude) || empty($geofence->longitude)) {
                unset($params['body']['position']);
                unset($params['body']['area']);
            }

            $response = $this->poster->index($params);

            // Example response when document created:
            // {
            //   "_index": "malls",
            //   "_type": "basic",
            //   "_id": "abc123",
            //   "_version": 1,
            //   "_shards": {
            //     "total": 2,
            //     "successful": 1,
            //     "failed": 0
            //   },
            //   "created": true
            // }
            //
            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // update suggestion
            $fakeJob = new FakeJob();
            $esQueue = new \Orbit\Queue\Elasticsearch\ESMallSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['mall_id' => $mallId]);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type']);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
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
}