<?php

namespace Orbit\Helper\Elasticsearch;

use Config;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Searchable\Helper\CacheableKeys;

/**
* Base ES Search Query Builder.
*
* @author Budi <budi@gotomalls.com>
*/
abstract class ESQueryBuilder
{
    // Indicate that this class has ability to generate a cache key
    use CacheableKeys;

    // ES Params that will be sent to ES Server..
    protected $searchParam = [];

    /**
     * Array that holds constant scoring query.
     * Any query that don't need to be added to scoring,
     * should be put inside this var.
     *
     * Example: is_subscribed is not needed for any scoring/relevance,
     * same as the status and city/country filters.
     *
     * @var array
     */
    protected $constantScoring = [];

    /**
     * List of item id that will be excluded from result.
     * @var array
     */
    protected $excludedIds = [];

    protected $esConfig = [];

    protected $countOnly = false;

    public function __construct()
    {
        $this->esConfig = Config::get('orbit.elasticsearch');
        $this->setDefaultSearchParam();

        // Set index
        $this->setIndex($this->esConfig['indices'][$this->objectType]['index']);

        // Set type
        $this->setType($this->esConfig['indices'][$this->objectType]['type']);
    }

    /**
     * Set the Indices.
     *
     * @param string $index [description]
     */
    public function setIndex($index = '')
    {
        $this->searchParam['index'] = $index;

        return $this;
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

        return $this;
    }

    /**
     * Set document id.
     *
     * @param string $id [description]
     */
    public function setId($id = '')
    {
        $this->searchParam['id'] = $id;

        return $this;
    }

    /**
     * Get (full or partial) param that will be sent to ES Server.
     *
     * @return [type] [description]
     */
    public function getRequestParam($key = '')
    {
        $this->buildExcludedIdsQuery();

        if ($key == '')
            return $this->searchParam;

        $keys = explode('.', $key);

        if (count($keys) == 1) {
            return $this->searchParam[$keys[0]];
        } else if (count($keys) == 2) {
            return $this->searchParam[$keys[0]][$keys[1]];
        }
    }

    public function setLimit($skip = 0, $take = 20)
    {
        $this->searchParam['body']['from'] = $skip;
        $this->searchParam['body']['size'] = $take;

        return $this;
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

        return $this;
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
        $this->buildExcludedIdsQuery();

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

    public function setBodyParams($params = [])
    {
        foreach($params as $param => $value) {
            $this->searchParam['body'][$param] = $value;
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
                'sort' => [],
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

    /**
     * Build query with excluded ids.
     * Basically only add must not terms into the search params body.
     *
     * @return void
     */
    public function buildExcludedIdsQuery()
    {
        if (! empty($this->excludedIds)) {
            foreach($this->excludedIds as $excludedId) {
                $this->mustNot([
                    'term' => [
                        '_id' => $excludedId,
                    ]
                ]);
            }
        }

        // Add custom excluded id parameter. Can be added
        // in each sub class as needed.
        if (method_exists($this, 'addExcludedIdsParam')) {
            $this->addExcludedIdsParam();
        }
    }

    /**
     * Not cool. Build the final search param that will be sent to ES.
     *
     * @todo  Find a cleaner way to do this.
     *
     * @return self instance
     */
    public function buildSearchParam()
    {
        $this->buildExcludedIdsQuery();

        if (empty($this->searchParam['body']['query'])) {
            unset($this->searchParam['body']['query']);
        }

        $this->buildCacheKey();

        // Encode to json
        $this->searchParam['body'] = json_encode($this->searchParam['body']);

        return $this;
    }

    public function setCountOnly($countOnly = false)
    {
        $this->countOnly = $countOnly;

        return $this;
    }

    /**
     * Basic cache key builder for es query.
     *
     * @return [type] [description]
     */
    protected function buildCacheKey()
    {
        $this->cacheKeys = $this->searchParam['body'];
    }

    // Child classes must provide implementation of build(),
    // because later it will implement DataBuilder interface (helper).
    abstract public function build();

    /**
     * Get ES query.
     *
     * @return array the ES query.
     */
    public function getQuery()
    {
        return $this->searchParam;
    }
}
