<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch news suggestion index when news has been updated.
 *
 * @author shelgi <shelgi@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use News;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;

class ESNewsSuggestionUpdateQueue
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

        $newsId = $data['news_id'];
        $news = News::with('country', 'city', 'translations')
                    ->select(DB::raw("
                        {$prefix}news.*,
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
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where('news.news_id', $newsId)
                    ->where('news.object_type', 'news')
                    ->where('news.status', 'active')
                    ->having('campaign_status', '=', 'ongoing')
                    ->orderBy('news.news_id', 'asc')
                    ->first();

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.news_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.news_suggestions.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $newsId
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete the news suggestion document if the status inactive
            if ($response_search['hits']['total'] > 0 && count($news) === 0) {
                $paramsDelete = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.news_suggestions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.news_suggestions.type'),
                    'id' => $newsId
                ];
                $responseDelete = $this->poster->delete($paramsDelete);

                ElasticsearchErrorChecker::throwExceptionOnDocumentError($responseDelete);

                $job->delete();

                $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Doucment in Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['news_suggestions']['index'],
                                $esConfig['indices']['news_suggestions']['type']);
                Log::info($message);

                return [
                    'status' => 'ok',
                    'message' => $message
                ];
            } elseif (count($news) === 0) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] News ID %s is not found.', $job->getJobId(), $newsId)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.news_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.news_suggestions.type'),
                'id' => $news->news_id,
                'body' => []
            ];

            $country = array();
            foreach($news->country as $data) {
                $country[] = $data->country;
            }

            $city = array();
            foreach($news->city as $data) {
                $city[] = $data->city;
            }

            $body = [
                'name'    => $news->news_name,
                'country' => $country,
                'city'    => $city,
                'begin_date' => date('Y-m-d', strtotime($news->begin_date)) . 'T' . date('H:i:s', strtotime($news->begin_date)) . 'Z',
                'end_date' => date('Y-m-d', strtotime($news->end_date)) . 'T' . date('H:i:s', strtotime($news->end_date)) . 'Z'
            ];

            // echo print_r(json_encode($body));

            foreach ($news->translations as $translationCollection) {
                $suggest = array();

                if (! empty($translationCollection->news_name) || $translationCollection->news_name != '') {
                    // generate input
                    $textName = $translationCollection->news_name;
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

                    $payloadType = 'news';
                    if ($news->is_having_reward === 'Y') {
                        $payloadType = 'promotional_event';
                    }

                    $suggest = [
                        'input'   => $input,
                        'output'  => $translationCollection->news_name,
                        'payload' => ['id' => $news->news_id, 'type' => $payloadType]
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

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.news_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; News ID: %s; News Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['news_suggestions']['index'],
                                $esConfig['indices']['news_suggestions']['type'],
                                $news->news_id,
                                $news->news_name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['news_suggestions']['index'],
                                $esConfig['indices']['news_suggestions']['type'],
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