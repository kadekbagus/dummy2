<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Update Elasticsearch index when mall has been updated.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Mall;
use DB;
use MerchantGeofence;
use Orbit\Queue\Elasticsearch\ESMallCreateQueue as ESMallCreateQueue;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESMallUpdateQueue extends ESMallCreateQueue
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    protected $poster = NULL;
    protected $object = NULL;

    /**
     * Class constructor.
     *
     * @param string $poster Object used to post the data.
     * @return void
     */
    public function __construct($poster = 'default')
    {
        $this->object = new ESMallCreateQueue($poster);
        $this->poster = $this->object->poster;
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
        $response = $this->object->fire($job,$data);

        if ($response['status'] === 'ok') {
            $response['message'] = str_replace("Create", "Update", $response['message']);
        } else {
            $response['message'] = str_replace("Create", "Update", $response['message']);
        }

        return [
            'status' => $response['status'],
            'message' => $response['message']
        ];
    }
}