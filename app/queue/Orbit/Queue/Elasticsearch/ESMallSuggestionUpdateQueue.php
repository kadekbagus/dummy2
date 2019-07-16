<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when mall suggestion has been updated.
 *
 * @author shelgi prasetyo <shelgi@dominopos.com>
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
use Queue;
use Orbit\FakeJob;

class ESMallSuggestionUpdateQueue
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
     *                    'merchant_id' => NUM // Mall ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $mallId = $data['mall_id'];
        $prefix = DB::getTablePrefix();
        $mall = Mall::with('country')->where('merchants.status', '=', 'active')
                    ->where('merchants.is_subscribed', 'Y')
                    ->where('merchants.merchant_id', $mallId)
                    ->first();

        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.mall_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.mall_suggestions.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $mallId
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete the mall suggestion document if the status inactive
            if ($response_search['hits']['total'] > 0 && count($mall) === 0) {
                $paramsDelete = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.mall_suggestions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.mall_suggestions.type'),
                    'id' => $mallId
                ];
                $responseDelete = $this->poster->delete($paramsDelete);

                ElasticsearchErrorChecker::throwExceptionOnDocumentError($responseDelete);

                $job->delete();

                $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Doucment in Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                    $job->getJobId(),
                                    $esConfig['indices']['malldata']['index'],
                                    $esConfig['indices']['malldata']['type']);
                Log::info($message);

                return [
                    'status' => 'ok',
                    'message' => $message
                ];
            } else if (count($mall) === 0) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Merchant_id %s is not found.', $job->getJobId(), $mallId)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.mall_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.mall_suggestions.type'),
                'id' => $mall->merchant_id,
                'body' => []
            ];

            // generate input
            $strings = $mall->name;
            $strings = preg_replace('/[^A-Za-z0-9 ] /', '', $strings);
            $strings = str_replace(['"', "'"], '', $strings);

            $words = explode(" ", $strings);

            $num = count($words);

            // The total number of possible combinations
            $total = pow(2, $num);

            $combo = [];
            // Loop through each possible combination
            // Warning, higher word counts will also increase CPU usage.
            for ($i = 0; $i < $total; $i++) {
                    //For each combination check if each bit is set
                $save = '';
                for ($j = 0; $j < $total; $j++) {
                    //Is bit $j set in $i?
                    if (pow(2, $j) & $i) {
                        // echo $words[$j] . ' ';
                        $save = $save . $words[$j] . ' ';
                    }
                }
                $combo[] = trim($save);
                // echo "\n";
            }

            // remove first empty element
            $combo = array_splice($combo, 1, count($combo));

            // sort by most word counts and leftest word occurence first
            // @todo: there is a little bit issue on "leftest word occurence first"
            //     some cases it is not sorting as expected
            usort($combo, function($a, $b) use ($words) {
                $totalA = 0;
                $totalB = 0;

                $componentA = explode(" ", $a);
                foreach($componentA as $ca) {
                    $totalA = $totalA + pow(array_search($ca, $words), 2);
                }

                $componentB = explode(" ", $b);
                foreach($componentB as $cb) {
                    $totalB = $totalB + pow(array_search($cb, $words), 2);
                }

                $wordCountA = str_word_count($a);
                $wordCountB = str_word_count($b);

                if ($wordCountB == $wordCountA) {
                    // print_r([$totalA, $totalB]);
                    return $totalA - $totalB;
                } elseif ($wordCountB > $wordCountA) {
                    return 1;
                } elseif ($wordCountB < $wordCountA) {
                    return -1;
                }
            });

            $suggest = [
                'input'   => $combo,
                'output'  => $mall->name,
                'payload' => ['id' => $mall->merchant_id, 'type' => 'mall']
            ];

            $esBody = [
                'name'       => $mall->name,
                'country'    => $mall->Country->name,
                'city'       => $mall->city,
                'mall_ids'   => [$mallId],
                'suggest_id' => $suggest,
                'suggest_en' => $suggest,
                'suggest_zh' => $suggest,
                'suggest_ms' => $suggest
            ];

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

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.mall_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

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
}