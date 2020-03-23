<?php namespace Orbit\Queue\Elasticsearch;
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
use ExtendedActivity;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\UserAgent;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Orbit\Helper\Util\CampaignSourceParser;
use Request;

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
     * @author Rio Astamal <rio@dominopos.com>
     * @param Job $job
     * @param array $data[
     *                    'activity_id' => NUM, // Activity ID
     *                    'referer' => HTTP_REFERER,
     *                    'orbit_referer' => APP_REFERER,
     *                    'current_url' => URL,
     *                    'extended_activity_id' => NUM
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        try {
            $activityId = $data['activity_id'];
            $activity = Activity::findOnWriteConnection($activityId);

            if (! is_object($activity)) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Activity ID %s is not found.', $job->getJobId(), $activity)
                ];
            }

            // This one used if the config is empty so the comparison
            // of user agent is not fail
            $fallbackUARules = ['browser' => [], 'platform' => [], 'device_model' => [], 'bot_crawler' => []];

            $detect = new UserAgent();
            $detect->setRules(Config::get('orbit.user_agent_rules', $fallbackUARules));
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

            $country = Config::get('orbit.activity.elasticsearch.lookup_country_city.default_country_value', 'LOOKUP COUNTRY DISABLED');
            $city = Config::get('orbit.activity.elasticsearch.lookup_country_city.default_city_value', 'LOOKUP CITY DISABLED');

            $lookupCountryCityEnabled = Config::get('orbit.activity.elasticsearch.lookup_country_city.enable', TRUE);
            if ($lookupCountryCityEnabled) {
                // get location based on ip address
                $addr = $activity->ip_address;

                //get default vendor from config
                $vendor = Config::get('orbit.vendor_ip_database.default', 'dbip');

                switch ($vendor) {
                    case 'dbip':
                        $addr_type = 'ipv4';
                        if (ip2long($addr) !== false) {
                            $addr_type = 'ipv4';
                        } else if (preg_match('/^[0-9a-fA-F:]+$/', $addr) && @inet_pton($addr)) {
                            $addr_type = 'ipv6';
                        }

                        $ipData = DB::connection(Config::get('orbit.vendor_ip_database.dbip.connection_id'))
                            ->table(Config::get('orbit.vendor_ip_database.dbip.table'))
                            ->where('ip_start', '<=', inet_pton($addr))
                            ->where('addr_type', '=', $addr_type)
                            ->orderBy('ip_start', 'desc')
                            ->first();
                        break;

                    case 'ip2location':
                        $findIp = explode(".", $addr);
                        $ipNumber = ((int)$findIp[0] * ( 256 * 256 * 256 )) + ((int)$findIp[1] * ( 256 * 256 )) + ((int)$findIp[2] * 256) + $findIp[3];

                        $ipData = DB::connection(Config::get('orbit.vendor_ip_database.ip2location.connection_id'))
                            ->table(Config::get('orbit.vendor_ip_database.ip2location.table'))
                            ->select('country_name as country', 'city_name as city')
                            ->where('ip_to', '>=', $ipNumber)
                            ->first();
                        break;
                }

                $country = '';
                $city = '';
                if (is_object($ipData)) {
                    // Override default value
                    $country = $ipData->country;
                    $city = $ipData->city;
                }
            }

            $esConfig = Config::get('orbit.elasticsearch');
            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.activities.index'),
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
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.activities.index'),
                'type' => Config::get('orbit.elasticsearch.indices.activities.type'),
                'id' => $activity->activity_id,
                'body' => []
            ];

            $fullCurrentUrl = $data['current_url'];
            $urlForTracking = [ $data['referer'], $fullCurrentUrl ];
            $campaignData = CampaignSourceParser::create()
                                                ->setUrls($urlForTracking)
                                                ->getCampaignSource();

            $esBody = [
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
                'country' =>  $country,
                'city' =>  $city,
                'position' => $pos,
                'page' => explode('?', $activity->request_uri)[0],
                'referer' => $data['referer'],
                'orbit_referer' => $data['orbit_referer'],
                'utm_source' => $campaignData['campaign_source'],
                'utm_medium' => $campaignData['campaign_medium'],
                'utm_term' => $campaignData['campaign_term'],
                'utm_content' => $campaignData['campaign_content'],
                'utm_campaign' => $campaignData['campaign_name']
            ];

            $this->applyExtendedActivity($data, $esBody);

            if ($response_search['hits']['total'] > 0) {
                $params['body'] = [
                    'doc' => $esBody
                ];
                $response = $this->poster->update($params);
            } else {
                $params['body'] = $esBody;
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

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Activity ID: %s; Activity Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['activities']['index'],
                                $esConfig['indices']['activities']['type'],
                                $activity->activity_id,
                                $activity->activity_name_long);
            // Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['activities']['index'],
                                $esConfig['indices']['activities']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::error($message);

            // @Todo shold be moved to helper
            $exceptionNoLine = preg_replace('/\s+/', ' ', $e->getMessage());

            // Format -> JOB_ID;ACTIVITY_ID;MESSAGE
            $dataLogFailed = sprintf("%s;%s;%s\n", $job->getJobId(), $activityId, trim($exceptionNoLine));

            // Write the error log to dedicated file so it is easy to investigate and
            // easy to replay because the log is structured
            file_put_contents(storage_path() . '/logs/activity-queue-error.log', $dataLogFailed, FILE_APPEND);
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

    /**
     * Fields used for rating
     *
     * @param array $data
     * @param array &$esBody
     * @return void
     */
    protected function applyExtendedActivity($data, &$esBody)
    {
        $esBody['tenant_id'] = '';
        $esBody['tenant_name'] = '';
        $esBody['mall_id'] = '';
        $esBody['mall_name'] = '';
        $esBody['rating'] = '0';
        $esBody['token_notification'] = '';
        $esBody['filter_cities'] = '';

        if (! isset($data['extended_activity_id'])) {
            return;
        }

        $extendedActivity = ExtendedActivity::findOnWriteConnection($data['extended_activity_id']);
        if (! is_object($extendedActivity)) {
            return;
        }

        $esBody['tenant_id'] = $extendedActivity->tenant_id ? $extendedActivity->tenant_id : '';
        $esBody['tenant_name'] = $extendedActivity->tenant_name ? $extendedActivity->tenant_name : '';
        $esBody['mall_id'] = $extendedActivity->mall_id ? $extendedActivity->mall_id : '';
        $esBody['mall_name'] = $extendedActivity->mall_name ? $extendedActivity->mall_name : '';
        $esBody['rating'] = trim($extendedActivity->rating) !== '' ? $extendedActivity->rating : '0';
        $esBody['token_notification'] = trim($extendedActivity->notification_token) !== '' ? $extendedActivity->notification_token : '';
        $esBody['filter_cities'] = trim($extendedActivity->filter_cities) !== '' ? $extendedActivity->filter_cities : '';
    }
}