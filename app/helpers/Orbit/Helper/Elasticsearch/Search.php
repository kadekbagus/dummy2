<?php 

namespace Orbit\Helper\Elasticsearch;

use Elasticsearch\ClientBuilder;

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

	// ES connection config
	protected $esConfig = [];

	function __construct($ESConfig = [])
	{
		$this->esConfig = $ESConfig;

		$this->client = new ClientBuilder;
		$this->client = $this->client->create()->setHosts($this->esConfig['hosts'])->build();

		$this->setDefaultSearchParam();
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

	public function getESClient()
	{
		return $this->client;
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


	// Below are ES grammar supported at the moment.

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
	 * Run the search, and get the result.
	 * 
	 * @return [type] [description]
	 */
	public function getResult($resultMapperClass = '')
	{
		$this->searchParam['body'] = json_encode($this->searchParam['body']);

		// if ($resultMapperClass == '')
			return $this->client->search($this->searchParam);
		// else
			// return new $resultMapperClass($this->client->search($this->searchParam))->map();
	}

	/// ----------------------------------------------------------------------
	// Bellow are some common methods that will be used by the child classes.
	// Let the child classes take care of the implementation/override them.
	// ----------------------------------------------------------------------
	
	/**
	 * Filter by Mall
	 * @param  string $mallId [description]
	 * @return [type]         [description]
	 */
	public function filterByMall($mallId = '') {}

	/**
	 * Filter by User's GeoLocation
	 * @param  array  $location [description]
	 * @return [type]           [description]
	 */
	public function filterByLocation($location = []) {}

	/**
	 * Filter by Keyword.
	 * 
	 * @param  string $keyword [description]
	 * @return [type]          [description]
	 */
	public function filterByKeyword($keyword = '') {}

	/**
	 * Filter by selected categories.
	 * 
	 * @param  array  $categories [description]
	 * @return [type]             [description]
	 */
	public function filterByCategories($categories = []) {}

	/**
	 * Filter by Partner.
	 * 
	 * @param  array  $partners [description]
	 * @return [type]           [description]
	 */
	public function filterByPartner($partners = []) {}

	/**
	 * Filter by Country and/or Cities.
	 * 
	 * @param  array  $partners [description]
	 * @return [type]           [description]
	 */
	public function filterByCountryAndCities($partners = []) {}

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
	 * Sort by relevance..
	 * 
	 * @return [type] [description]
	 */
	public function sortByRelevance()
	{
		$this->sort(['_score' => ['order' => 'desc']]);
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
				'aggs' => [
					'count' => [
						'nested' => [
							'path' => 'tenant_detail'
						],
						'aggs' => [
							'top_reverse_nested' => [
								'reverse_nested' => new \stdClass()
							]
						]
					],
				],
				'query' => [],
				'track_scores' => true,
				'sort' => []
			]
		];
	}
}