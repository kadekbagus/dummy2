<?php

use Search as OrbitESSearch;

/**
* Implementation of ES search for Stores...
*/
class StoreSearch extends OrbitESSearch
{
	/**
	 * Listed Stores should have at least 1 tenant.
	 * 
	 * @return [type] [description]
	 */
	public function filterMinimumTenants()
	{
		$this->must([
            'range' => [
                'tenant_detail_count' => [
                    'gt' => 0
                ]
            ]
        ]);
	}

	/**
	 * Filter by Mall...
	 * 
	 * @param  string $mallId [description]
	 * @return [type]         [description]
	 */
	public function filterByMall($mallId = '')
	{
		$this->must([
			'nested' => [
				'path' => 'tenant_detail',
				'query' => [
					'match' => [
						'tenant_detail.mall_id' => $mallId
					]
				]
			]
		]);
	}

	/**
	 * Filter by selected stores Categories..
	 * 
	 * @return [type] [description]
	 */
	public function filterByCategories($categories = [])
	{
		foreach($categories as $category) {
			$this->must(['match' => ['category' => $category]]);
		}

		// $this->must($categories);
	}

	/**
	 * Filter by user's geo-location...
	 * 
	 * @param  string $location [description]
	 * @return [type]           [description]
	 */
	public function filterByLocation($location = [])
	{
		if (! empty($location)) {
			$this->should([
				'nested' => [
					'path' => 'tenant_detail',
					'query' => [
						'bool' => [
							'must' => [
								'geo_distance' => [
									'distance' => "10km",
									'distance_type' => 'plane',
									'tenant_detail.position' => $location
								]
							]
						]
					]
				]
			]);
		}
	}

	/**
	 * Implement filter by keyword...
	 * 
	 * @param  string $keyword [description]
	 * @return [type]          [description]
	 */
	public function filterByKeyword($keyword = '', $options = [])
	{
		$priorityName = isset($options['priority']['name']) ? 
			$options['priority']['name'] : '';

		$priorityDescription = isset($options['priority']['description']) ? 
			$options['priority']['description'] : '';

		$priorityKeywords = isset($options['priority']['keywords']) ? 
			$options['priority']['keywords'] : '';

		$this->must([
			'bool' => [
				'should' => [
					[
						'query_string' => [
							'query' => $keyword . '*',
							'fields' => [
								'lowercase_name' . $priorityName, 
								'description' . $priorityDescription, 
								'keyword' . $priorityKeywords
							]
						]
					],
					[
						'nested' => [
							'path' => 'translation',
							'query' => [
								'match' => [
									'translation.description' => $keyword
								]
							]
						]
					]
				]
			]
		]);

		$priorityCountry = isset($options['priority']['country']) ?
			$options['priority']['country'] : '';

		$priorityProvince = isset($options['priority']['province']) ?
			$options['priority']['province'] : '';

		$priorityCity = isset($options['priority']['city']) ?
			$options['priority']['city'] : '';

		$priorityMallName = isset($options['priority']['mall_name']) ?
			$options['priority']['mall_name'] : '';

		$this->should([
			'nested' => [
				'path' => 'tenant_detail',
				'query' => [
					'query_string' => [
						'query' => $keyword . '*',
						'fields' => [
							'tenant_detail.country' . $priorityCountry,
							'tenant_detail.province' . $priorityProvince,
							'tenant_detail.city' . $priorityCity,
							'tenant_detail.mall_name' . $priorityMallName,
						]
					]
				]
			]
		]);
	}

	/**
	 * Filte by Country and Cities...
	 * 
	 * @param  array  $area [description]
	 * @return [type]       [description]
	 */
	public function filterByCountryAndCities($area = [])
	{
		if (isset($area['country'])) {
			$this->must([
				'nested' => [
					'path' => 'tenant_detail',
					'query' => [
						'match' => [
							'tenant_detail.country.raw' => $area['country']
						]
					]
				]
			]);
		}

		if (isset($area['cities'])) {

			$citiesQuery = [
				'bool' => [
					'should' => []
				]
			];

			foreach($area['cities'] as $city) {
				$citiesQuery['bool']['should'][] = [
					'nested' => [
						'path' => 'tenant_detail',
						'query' => [
							'match' => [
								'tenant_detail.city.raw' => $city
							]
						]
					]
				];
			}

			$this->must($citiesQuery);
		}
	}

	/**
	 * Filter by Partner...
	 * 
	 * @param  string $partnerId [description]
	 * @return [type]            [description]
	 */
	public function filterByPartner($partnerId = '')
	{
		$this->must([
			'match' => [
				'partner_ids' => $partnerId
			]
		]);
	}

	public function excludeAdvertStores($excludedId = [])
	{
		$this->mustNot([
			'terms' => [
				'_id' => $excludedId,
			]
		]);
	}

	/**
	 * Sort by...
	 * 
	 * @param  array  $sortParams [description]
	 * @return [type]             [description]
	 */
	public function sortBy($sortParams = [])
	{
		$this->sort($sortParams);
	}

	/**
	 * Sort by name..
	 * 
	 * @return [type] [description]
	 */
	public function sortByName()
	{
		$this->sort(['lowercase_name' => ['order' => 'asc']]);
	}

	public function sortByRating($sortingScript = '')
	{
		$this->sort([
			'_script' => [
				'script' => $sortingScript,
				'type' => 'number',
				'order' => 'desc'
			]
		]);
	}

	public function sortByFavorite($sortingScript = '')
	{
		$this->sort([
			'_script' => [
				'script' => $sortingScript,
				'type' => 'number',
				'order' => 'desc'
			]
		]);

		$this->sortByRelevance();
		$this->sortByName();
	}

	public function sortByRelevance()
	{
		$this->sort(['_score' => ['order' => 'desc']]);
	}
}