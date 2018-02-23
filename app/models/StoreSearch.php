<?php

use Orbit\Helper\Elasticsearch\Search;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Stores...
*/
class StoreSearch extends Search
{
	function __construct($ESConfig = [])
	{
		parent::__construct($ESConfig);

		$this->setDefaultSearchParam();

		$this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices']['stores']['index']);
		$this->setType($this->esConfig['indices']['stores']['type']);
	}
	
	/**
	 * Make sure Stores has at least 1 tenant.
	 * 
	 * @return [type] [description]
	 */
	public function hasAtLeastOneTenant()
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
				],
				'inner_hits' => new \stdClass(),
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
	public function filterByKeyword($keyword = '')
	{
		$priorityName = isset($this->esConfig['priority']['store']['name']) ? 
			$this->esConfig['priority']['store']['name'] : '^6';

		$priorityDescription = isset($this->esConfig['priority']['store']['description']) ? 
			$this->esConfig['priority']['store']['description'] : '^5';

		$priorityKeywords = isset($this->esConfig['priority']['store']['keywords']) ? 
			$this->esConfig['priority']['store']['keywords'] : '^4';

		// $priorityProductTags = isset($this->esConfig['priority']['store']['product_tags']) ? 
		// 	$this->esConfig['priority']['store']['product_tags'] : '';

		$this->must([
			'bool' => [
				'should' => [
					[
						'query_string' => [
							'query' => $keyword . '*',
							'fields' => [
								'lowercase_name' . $priorityName, 
								'description' . $priorityDescription, 
								'keyword' . $priorityKeywords,
								// 'product_tags' . $priorityProductTags,
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
					],
				]
			]
		]);

		$priorityCountry = isset($this->esConfig['store']['priority']['country']) ?
			$this->esConfig['store']['priority']['country'] : '';

		$priorityProvince = isset($this->esConfig['store']['priority']['province']) ?
			$this->esConfig['store']['priority']['province'] : '';

		$priorityCity = isset($this->esConfig['store']['priority']['city']) ?
			$this->esConfig['store']['priority']['city'] : '';

		$priorityMallName = isset($this->esConfig['store']['priority']['mall_name']) ?
			$this->esConfig['store']['priority']['mall_name'] : '';

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
					],
					'inner_hits' => [
						'name' => 'country_city_hits'
					],
				]
			]);
		}

		if (isset($area['cities'])) {

			if (count($area['cities']) > 0) {

				$citiesQuery['bool']['should'] = [];

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

	/**
	 * Exclude some stores from the result.
	 * 
	 * @param  array  $excludedId [description]
	 * @return [type]             [description]
	 */
	public function excludeStores($excludedId = [])
	{
		$this->mustNot([
			'terms' => [
				'_id' => $excludedId,
			]
		]);
	}

	/**
	 * Exclude the partner competitors from the result.
	 * 
	 * @param  array  $competitors [description]
	 * @return [type]              [description]
	 */
	public function excludePartnerCompetitors($competitors = [])
	{
		$this->mustNot([
            'terms' => [
                'partner_ids' => $partnerIds
            ]
        ]);
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

	/**
	 * Sort store by rating.
	 * 
	 * @param  string $sortingScript [description]
	 * @return [type]                [description]
	 */
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

	/**
	 * Sort store by favorite.
	 * 
	 * @param  string $sortingScript [description]
	 * @return [type]                [description]
	 */
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

	public function addReviewFollowScript($params = [])
	{
		// calculate rating and review based on location/mall
        ///// RATING AND FOLLOW SCRIPT //////
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        $scriptFieldFollow = "int follow = 0;";

        if (! empty($params['mallId'])) {
            // count total review and average rating for store inside mall
            $scriptFieldRating = $scriptFieldRating . 
                " if (doc.containsKey('mall_rating.rating_" . $params['mallId'] . "')) { 
                    if (! doc['mall_rating.rating_" . $params['mallId'] . "'].empty) { 
                        counter = counter + doc['mall_rating.review_" . $params['mallId'] . "'].value; 
                        rating = rating + (doc['mall_rating.rating_" . $params['mallId'] . "'].value * doc['mall_rating.review_" . $params['mallId'] . "'].value);
                    }
                };";

            $scriptFieldReview = $scriptFieldReview . 
                " if (doc.containsKey('mall_rating.review_" . $params['mallId'] . "')) { 
                    if (! doc['mall_rating.review_" . $params['mallId'] . "'].empty) { 
                        review = review + doc['mall_rating.review_" . $params['mallId'] . "'].value;
                    }
                }; ";

        } else if (! empty($params['cityFilters'])) {
            // count total review and average rating based on city filter
            $countryId = $params['countryData']->country_id;
            foreach ((array) $params['cityFilters'] as $cityFilter) {
                $scriptFieldRating = $scriptFieldRating . 
                    " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { 
                        if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { 
                            counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; 
                            rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);
                        }
                    }; ";

                $scriptFieldReview = $scriptFieldReview . 
                    " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { 
                        if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { 
                            review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;
                        }
                    }; ";
            }

        } else if (! empty($params['countryFilter'])) {
            // count total review and average rating based on country filter
            $countryId = $params['countryData']->country_id;
            $scriptFieldRating = $scriptFieldRating . 
                " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { 
                    if (! doc['location_rating.rating_" . $countryId . "'].empty) { 
                        counter = counter + doc['location_rating.review_" . $countryId . "'].value; 
                        rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);
                    }
                }; ";

            $scriptFieldReview = $scriptFieldReview . 
                " if (doc.containsKey('location_rating.review_" . $countryId . "')) { 
                    if (! doc['location_rating.review_" . $countryId . "'].empty) { 
                        review = review + doc['location_rating.review_" . $countryId . "'].value;
                    }
                }; ";

        } else {
            // count total review and average rating based in all location
            $mallCountry = Mall::groupBy('country')->lists('country');
            $countries = Country::select('country_id')->whereIn('name', $mallCountry)->get();

            foreach ($countries as $country) {
                $countryId = $country->country_id;
                $scriptFieldRating = $scriptFieldRating . 
                    " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { 
                        if (! doc['location_rating.rating_" . $countryId . "'].empty) { 
                            counter = counter + doc['location_rating.review_" . $countryId . "'].value; 
                            rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);
                        }
                    }; ";

                $scriptFieldReview = $scriptFieldReview . " 
                    if (doc.containsKey('location_rating.review_" . $countryId . "')) { 
                        if (! doc['location_rating.review_" . $countryId . "'].empty) { 
                            review = review + doc['location_rating.review_" . $countryId . "'].value;
                        }
                    }; ";
            }
        }

        $scriptFieldRating = $scriptFieldRating . " if(counter == 0 || rating == 0) {return 0;} else {return rating/counter;}; ";
        $scriptFieldRating = str_replace("\n", '', $scriptFieldRating);

        $scriptFieldReview = $scriptFieldReview . " if(review == 0) {return 0;} else {return review;}; ";
        $scriptFieldReview = str_replace("\n", '', $scriptFieldReview);

        $role = $params['user']->role->role_name;
        $objectFollow = [];
        if (strtolower($role) === 'consumer') {
            $objectFollow = $this->getUserFollow($params['user'], $params['mallId'], $params['cityFilters']); // return array of base_merchant_id

            if (! empty($objectFollow)) {
                if ($params['sortBy'] === 'followed') {
                    foreach ($objectFollow as $followId) {
                        $scriptFieldFollow = $scriptFieldFollow . 
                            " if (doc.containsKey('base_merchant_id')) { 
                                if (! doc['base_merchant_id'].empty) { 
                                    if (doc['base_merchant_id'].value.toLowerCase() == '" . strtolower($followId) . "') { 
                                        follow = 1; 
                                    }
                                }
                            };";
                    }

                    $scriptFieldFollow = $scriptFieldFollow . " if(follow == 0) {return 0;} else {return follow;}; ";
                    $scriptFieldFollow = str_replace("\n", '', $scriptFieldFollow);
                }
            }
        }

        // Add script fields into request body...
        $this->scriptFields([
            'average_rating' => $scriptFieldRating,
            'total_review' => $scriptFieldReview,
            'is_follow' => $scriptFieldFollow
        ]);

        return compact('scriptFieldRating', 'scriptFieldReview', 'scriptFieldFollow');

        //////// END RATING & FOLLOW SCRIPTS /////
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

	// check user follow
    public function getUserFollow($user, $mallId, $city=array())
    {
        $follow = FollowStatusChecker::create()
                                    ->setUserId($user->user_id)
                                    ->setObjectType('store');

        if (! empty($mallId)) {
            $follow = $follow->setMallId($mallId);
        }

        if (! empty($city)) {
            if (! is_array($city)) {
                $city = (array) $city;
            }
            $follow = $follow->setCity($city);
        }

        $follow = $follow->getFollowStatus();

        return $follow;
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