<?php

namespace Orbit\Helper\Elasticsearch;

use Elasticsearch\ClientBuilder;
use Config;

/**
* Base ES Search helper...
*
* @todo  make it more generic like laravel's eloquent.
*/
class Search
{
    // ES Client
    protected $client = null;

    // ES Params that will be sent to ES Server..
    protected $searchParam = [];

    /**
     * Array that holds constant scoring query.
     * Any query that don't need to be added to scoring, should be put inside this var.
     *
     * Example: is_subscribed is not needed for any scoring/relevance, same as the status and city/country filters.
     * @var array
     */
    protected $constantScoring = [];

    // ES connection config
    protected $esConfig = [];

    function __construct($ESConfig = [])
    {
        if (empty($ESConfig)) {
            $ESConfig = Config::get('orbit.elasticsearch');
        }

        $this->esConfig = $ESConfig;

        $this->client = new ClientBuilder;
        $this->client = $this->client->create()->setHosts($this->esConfig['hosts'])->build();

        // $this->setDefaultSearchParam();
    }

    /**
     * Set the Indices.
     *
     * @param string $index [description]
     */
    public function setIndex($index = '')
    {
        $this->searchParam['index'] = $index;
    }

    /**
     * Get the indices.
     *
     * @return [type] [description]
     */
    public function getIndex()
    {
        return $this->searchParam['index'];
    }

    /**
     * Set the type of the Indices.
     *
     * @param string $type [description]
     */
    public function setType($type = '')
    {
        $this->searchParam['type'] = $type;
    }

    /**
     * Get (full or partial) param that will be sent to ES Server.
     *
     * @return [type] [description]
     */
    public function getRequestParam($key = '')
    {
        if ($key == '')
            return $this->searchParam;

        $keys = explode('.', $key);

        if (count($keys) == 1) {
            return $this->searchParam[$keys[0]];
        } else if (count($keys) == 2) {
            return $this->searchParam[$keys[0]][$keys[1]];
        }
    }

    /**
     * Set the pagination parameter: the start index and the amount of data that will be taken.
     *
     * @param array $param [description]
     */
    public function setPaginationParams($param = [])
    {
        $this->searchParam['body']['from'] = $param['from'];
        $this->searchParam['body']['size'] = $param['size'];
    }

    /**
     * Add query into bool "must" array.
     *
     * @param  array  $query [description]
     * @return [type]        [description]
     */
    public function must($query = [])
    {
        $this->searchParam['body']['query']['bool']['must'][] = $query;
    }

    /**
     * Add query into bool "must_not" array.
     *
     * @param  array  $query [description]
     * @return [type]        [description]
     */
    public function mustNot($query = [])
    {
        $this->searchParam['body']['query']['bool']['must_not'][] = $query;
    }

    /**
     * Add query into bool "filter" array.
     *
     * @param  array  $query [description]
     * @return [type]        [description]
     */
    public function filter($query = [])
    {
        $this->searchParam['body']['query']['bool']['filter'][] = $query;
    }

    /**
     * Add query into bool "should" array.
     *
     * @param  array  $query [description]
     * @return [type]        [description]
     */
    public function should($query = [])
    {
        $this->searchParam['body']['query']['bool']['should'][] = $query;
    }

    /**
     * Add minimum_should_match into query.
     *
     * @param  string $minimumMatch [description]
     * @return [type]               [description]
     */
    public function minimumShouldMatch($minimumMatch = '')
    {
        $this->searchParam['body']['query']['bool']['minimum_should_match'] = $minimumMatch;
    }

    /**
     * Add bool query into constant_scoring.
     *
     * @param  string $boolType [description]
     * @param  array  $query    [description]
     * @return [type]           [description]
     */
    public function constantScoring($boolType = 'must', $query = [])
    {
        $this->constantScoring[$boolType][] = $query;
    }

    /**
     * Add the constant scoring array into main body.
     */
    public function addConstantScoringToQuery()
    {
        if (! empty($this->constantScoring)) {
            $this->must([
                'query' => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => $this->constantScoring
                        ]
                    ]
                ]
            ]);
        }
    }

    /**
     * Add sort query/script into sort array.
     *
     * @param  array  $sortParams [description]
     * @return [type]             [description]
     */
    public function sort($sortParams = [])
    {
        $this->searchParam['body']['sort'][] = $sortParams;
    }

    /**
     * How to sort the result.
     *
     * @param  array  $sortParams [description]
     * @return [type]             [description]
     */
    public function sortBy($sortParams = []) {
        $this->sort($sortParams);
    }

    /**
     * Add script into script_fields array.
     *
     * @param  array  $scriptFields [description]
     * @return [type]               [description]
     */
    public function scriptFields($scriptFields = [])
    {
        foreach($scriptFields as $scriptFieldName => $scriptDetail) {
            $this->searchParam['body']['script_fields'][$scriptFieldName] = [
                'script' => $scriptDetail
            ];
        }
    }
    /**
     * Run the search, and get the result.
     *
     * @return [type] [description]
     */
    public function getResult($resultMapperClass = '')
    {
        $this->searchParam['body'] = json_encode($this->searchParam['body']);

        return $this->client->search($this->searchParam);
    }

    /**
     * Set/Override search params
     *
     * @param $params array
     * @return void
     */
    public function setParams($params = [])
    {
        foreach ($params as $param => $value) {
            $this->searchParam[$param] = $value;
        }
    }

    /**
     * Remove search params array element by key
     *
     * @param $key string (dot notation array)
     * @return void
     */
    public function removeParamItem($key='')
    {
        if (! empty($key)) {
            if (! is_null(array_get($this->searchParam, $key, null))) {
                array_forget($this->searchParam, $key);
            }
        }
    }

    /**
     * Get client
     *
     * @return $client Elasticsearch\ClientBuilder
     */
    public function getActiveClient()
    {
        return $this->client;
    }

    /**
     * Init default search params.
     *
     * @return [type] [description]
     */
    public function setDefaultSearchParam()
    {
        $this->searchParam = [
            'index' => '',
            'type' => '',
            'body' => [
                'from' => 0,
                'size' => 20,
                'fields' => [
                    '_source'
                ],
                'query' => [],
                'track_scores' => true,
                'sort' => []
            ]
        ];
    }

    /**
     * replace any forbidden character
     *
     * @param string $str, input string
     * @return string string without forbidden character
     */
    public function escape($str)
    {
        $forbiddenCharacter = array(
            '>',
            '<',
            '(',
            ')',
            '{',
            '}',
            '[',
            ']',
            '^',
            '"',
            '~',
            '/',
            ':'
        );
        return str_replace($forbiddenCharacter, '', $str);
    }
}
