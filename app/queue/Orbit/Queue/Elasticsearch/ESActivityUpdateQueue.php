<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Update Elasticsearch index when activity has been updated.
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Mall;
use DB;
use Activity;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\UserAgent;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;

class ESActivityUpdateQueue
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
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data[
     *                    'activity_id' => NUM // Mall ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $activityId = $data['activity_id'];
        $activity = Activity::excludeDeleted()
                    ->where('activity_id', $activityId)
                    ->where('group', 'mobile-ci')
                    ->first();

        if (! is_object($activity)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Activity ID %s is not found.', $job->getJobId(), $activity)
            ];
        }

        $detect = new UserAgent;
        $detect->setUserAgent($activity->user_agent);

        // device
        $deviceType = $detect->deviceType();
        $deviceModel = $detect->deviceModel();

        // os
        $osName = $detect->platform();
        $osVersion = $detect->version($osName);

        // browser
        $browserName = $detect->browser();
        $browserVersion = $detect->version($browserName);

        // get location based on ip address
        $addr = $activity->ip_address;
        $addr_type = "ipv4";
        if (ip2long($addr) !== false) {
            $addr_type = "ipv4";
        } else if (preg_match('/^[0-9a-fA-F:]+$/', $addr) && @inet_pton($addr)) {
            $addr_type = "ipv6";
        }

        $findIp = DB::connection(Config::get('orbit.dbip.connection_id'))
                    ->table(Config::get('orbit.dbip.table'))
                    ->where('ip_start', '<=', $addr)
                    ->where('ip_end', '>=', $addr)
                    ->where('addr_type', '=', $addr_type)
                    ->first();

        $esConfig = Config::get('orbit.elasticsearch');

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => Config::get('orbit.elasticsearch.indices.activities.index'),
                'type' => Config::get('orbit.elasticsearch.indices.activities.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $activity->activity_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $pos = ['lon' => $activity->longitude, 'lat' => $activity->latitude];
            if (empty($activity->longitude) || empty($activity->latitude)) {
                $pos = null;
            }

            $response = NULL;
            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => Config::get('orbit.elasticsearch.indices.activities.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.activities.type'),
                    'id' => $activity->activity_id,
                    'body' => [
                        'doc' => [
                            'activity_name' =>  $activity->activity_name,
                            'activity_name_long' =>  $activity->activity_name_long,
                            'activity_type' =>  $activity->activity_type,
                            'module_name' =>  $activity->module_name,
                            'user_id' =>  $activity->user_id,
                            'user_email' =>  $activity->user_email,
                            'full_name' =>  $activity->full_name,
                            'gender' =>  $activity->gender,
                            'group' =>  $activity->group,
                            'role' =>  $activity->role,
                            'role_id' =>  $activity->role_id,
                            'object_id' =>  $activity->object_id,
                            'object_name' =>  $activity->object_name,
                            'location_id' =>  $activity->location_id,
                            'location_name' =>  $activity->location_name,
                            'ip_address' =>  $activity->ip_address,
                            'from_wifi' =>  $activity->from_wifi,
                            'session_id' =>  $activity->session_id,
                            'user_agent' =>  $activity->user_agent,
                            'staff_id' =>  $activity->staff_id,
                            'staff_name' =>  $activity->staff_name,
                            'notes' =>  $activity->notes,
                            'status' =>  $activity->status,
                            'parent_id' =>  $activity->parent_id,
                            'response_status' =>  $activity->response_status,
                            'created_at' => $activity->created_at->format("Y-m-d") . 'T' . $activity->created_at->format("H:i:s") . 'Z',
                            'object_display_name' =>  $activity->object_display_name,
                            'browser_name' => $browserName,
                            'browser_version' => $browserVersion,
                            'os_name' => $osName,
                            'os_version' => $osVersion,
                            'device_type' => $deviceType,
                            'device_vendor' => null,
                            'device_model' => $deviceModel,
                            'country' =>  $findIp->country,
                            'city' =>  $findIp->city,
                            'position' => $pos
                        ]
                    ]
                ];

                $response = $this->poster->update($params);
            } else {
                $params = [
                    'index' => Config::get('orbit.elasticsearch.indices.activities.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.activities.type'),
                    'id' => $activity->activity_id,
                    'body' => [
                        'activity_name' =>  $activity->activity_name,
                        'activity_name_long' =>  $activity->activity_name_long,
                        'activity_type' =>  $activity->activity_type,
                        'module_name' =>  $activity->module_name,
                        'user_id' =>  $activity->user_id,
                        'user_email' =>  $activity->user_email,
                        'full_name' =>  $activity->full_name,
                        'gender' =>  $activity->gender,
                        'group' =>  $activity->group,
                        'role' =>  $activity->role,
                        'role_id' =>  $activity->role_id,
                        'object_id' =>  $activity->object_id,
                        'object_name' =>  $activity->object_name,
                        'location_id' =>  $activity->location_id,
                        'location_name' =>  $activity->location_name,
                        'ip_address' =>  $activity->ip_address,
                        'from_wifi' =>  $activity->from_wifi,
                        'session_id' =>  $activity->session_id,
                        'user_agent' =>  $activity->user_agent,
                        'staff_id' =>  $activity->staff_id,
                        'staff_name' =>  $activity->staff_name,
                        'notes' =>  $activity->notes,
                        'status' =>  $activity->status,
                        'parent_id' =>  $activity->parent_id,
                        'response_status' =>  $activity->response_status,
                        'created_at' => $activity->created_at->format("Y-m-d") . 'T' . $activity->created_at->format("H:i:s") . 'Z',
                        'object_display_name' =>  $activity->object_display_name,
                        'browser_name' => $browserName,
                        'browser_version' => $browserVersion,
                        'os_name' => $osName,
                        'os_version' => $osVersion,
                        'device_type' => $deviceType,
                        'device_vendor' => null,
                        'device_model' => $deviceModel,
                        'country' =>  $findIp->country,
                        'city' =>  $findIp->city,
                        'position' => $pos
                    ]
                ];
                $response = $this->poster->index($params);
            }

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
            //   "created": false
            // }
            //
            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['activities']['index'],
                                $esConfig['indices']['activities']['type']);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['activities']['index'],
                                $esConfig['indices']['activities']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::info($message);

            return [
                'status' => 'fail',
                'message' => $message
            ];
        }
    }
}