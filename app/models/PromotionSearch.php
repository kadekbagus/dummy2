<?php

use Orbit\Helper\Elasticsearch\Search;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Stores...
*/
class PromotionSearch extends Search
{
	function __construct($ESConfig = [])
	{
		parent::__construct($ESConfig);

		$this->setDefaultSearchParam();

		$this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices']['promotions']['index']);
		$this->setType($this->esConfig['indices']['promotions']['type']);
	}
	
	/**
	 * Make sure the promotion is active.
	 * 
	 * @return [type] [description]
	 */
	public function isActive($params = [])
	{
		$this->must([ 'match' => ['status' => 'active'] ]);
		$this->must([ 'range' => ['begin_date' => ['lte' => $params['dateTimeEs']]] ]);
		$this->must([ 'range' => ['end_date' => ['gte' => $params['dateTimeEs']]] ]);
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
				'path' => 'link_to_tenant',
				'query' => [
					'match' => [
						'link_to_tenant.parent_id' => $mallId
					]
				],
				// 'inner_hits' => new \stdClass(),
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
		// filter by category_id
        // OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
        //     $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.category', '');
        //     $searchFlag = $searchFlag || TRUE;
        //     if (! is_array($categoryIds)) {
        //         $categoryIds = (array)$categoryIds;
        //     }

        //     foreach ($categoryIds as $key => $value) {
        //         $categoryFilter['bool']['should'][] = array('match' => array('category_ids' => $value));
        //     }

        //     if ($shouldMatch != '') {
        //         $categoryFilter['bool']['minimum_should_match'] = $shouldMatch;
        //     }
        //     $jsonQuery['query']['bool']['filter'][] = $categoryFilter;
        // });

		foreach($categories as $category) {
			$this->should(['match' => ['category_ids' => $category]]);
		}
	}

	/**
	 * Filter by user's geo-location...
	 * 
	 * @param  string $location [description]
	 * @return [type]           [description]
	 */
	public function filterByLocation($location = [])
	{
		// get user lat and lon
        // if ($sort_by == 'location' || $location == 'mylocation') {
        //     if (! empty($ul)) {
        //         $position = explode("|", $ul);
        //         $lon = $position[0];
        //         $lat = $position[1];
        //     } else {
        //         // get lon lat from cookie
        //         $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
        //         if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
        //             $lon = $userLocationCookieArray[0];
        //             $lat = $userLocationCookieArray[1];
        //         }
        //     }
        // }

		// filter by location (city or user location)
        // OrbitInput::get('location', function($location) use (&$jsonQuery, &$searchFlag, &$withScore, $lat, $lon, $distance, &$withCache)
        // {
        //     if (! empty($location)) {
        //         $searchFlag = $searchFlag || TRUE;
        //         $withCache = FALSE;
        //         if ($location === 'mylocation' && $lat != '' && $lon != '') {
        //             $locationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat)))))));
        //             $jsonQuery['query']['bool']['filter'][] = $locationFilter;
        //         } elseif ($location !== 'mylocation') {
        //             $locationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.city.raw' => $location))))));
        //             $jsonQuery['query']['bool']['filter'][] = $locationFilter;
        //         }
        //     }
        // });

		// if (! empty($location)) {
		// 	$this->should([
		// 		'nested' => [
		// 			'path' => 'link_to_tenant',
		// 			'query' => [
		// 				'bool' => [
		// 					'must' => [
		// 						'geo_distance' => [
		// 							'distance' => "10km",
		// 							'distance_type' => 'plane',
		// 							'link_to_tenant.position' => $location
		// 						]
		// 					]
		// 				]
		// 			]
		// 		]
		// 	]);
		// }
	}

	/**
	 * Implement filter by keyword...
	 * 
	 * @param  string $keyword [description]
	 * @return [type]          [description]
	 */
	public function filterByKeyword($keyword = '')
	{
		// $withKeywordSearch = false;
        // $filterKeyword = [];
        // OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$withKeywordSearch, &$cacheKey, &$filterKeyword)
        // {
        //     $cacheKey['keyword'] = $keyword;
        //     if ($keyword != '') {
        //         $searchFlag = $searchFlag || TRUE;
        //         $withKeywordSearch = true;
        //         $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.keyword', '');

        //         $priority['name'] = Config::get('orbit.elasticsearch.priority.promotions.name', '^6');
        //         $priority['object_type'] = Config::get('orbit.elasticsearch.priority.promotions.object_type', '^5');
        //         $priority['keywords'] = Config::get('orbit.elasticsearch.priority.promotions.keywords', '^4');
        //         $priority['description'] = Config::get('orbit.elasticsearch.priority.promotions.description', '^3');
        //         $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.promotions.mall_name', '^3');
        //         $priority['city'] = Config::get('orbit.elasticsearch.priority.promotions.city', '^2');
        //         $priority['province'] = Config::get('orbit.elasticsearch.priority.promotions.province', '^2');
        //         $priority['country'] = Config::get('orbit.elasticsearch.priority.promotions.country', '^2');

        //         $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.name'.$priority['name'], 'translation.description'.$priority['description'])))));

        //         $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'link_to_tenant', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('link_to_tenant.city'.$priority['city'], 'link_to_tenant.province'.$priority['province'], 'link_to_tenant.country'.$priority['country'], 'link_to_tenant.mall_name'.$priority['mall_name'])))));

        //         $filterKeyword['bool']['should'][] = array('multi_match' => array('query' => $keyword, 'fields' => array('object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));

        //         if ($shouldMatch != '') {
        //             $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
        //         }

        //         $jsonQuery['query']['bool']['filter'][] = $filterKeyword;
        //     }
        // });

        // OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
        //     if (! empty($mallId)) {
        //         $withMallId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.parent_id' => $mallId))))));
        //         $jsonQuery['query']['bool']['filter'][] = $withMallId;
        //     }
        //  });

		$priorityName = isset($this->esConfig['priority']['promotions']['name']) ? 
			$this->esConfig['priority']['promotions']['name'] : '^6';

		$priorityDescription = isset($this->esConfig['priority']['promotions']['description']) ? 
			$this->esConfig['priority']['promotions']['description'] : '^5';

		$priorityKeywords = isset($this->esConfig['priority']['promotions']['keywords']) ? 
			$this->esConfig['priority']['promotions']['keywords'] : '^4';

		$priorityProductTags = isset($this->esConfig['priority']['promotions']['product_tags']) ? 
			$this->esConfig['priority']['promotions']['product_tags'] : '';

		$this->must([
			'bool' => [
				'should' => [
					[
						'query_string' => [
							'query' => $keyword . '*',
							'fields' => [
								'name' . $priorityName, 
								'description' . $priorityDescription, 
								'keyword' . $priorityKeywords,
								'product_tags' . $priorityProductTags,
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

		$priorityCountry = isset($this->esConfig['priority']['promotions']['country']) ?
			$this->esConfig['priority']['promotions']['country'] : '';

		$priorityProvince = isset($this->esConfig['priority']['promotions']['province']) ?
			$this->esConfig['priority']['promotions']['province'] : '';

		$priorityCity = isset($this->esConfig['priority']['promotions']['city']) ?
			$this->esConfig['priority']['promotions']['city'] : '';

		$priorityMallName = isset($this->esConfig['priority']['promotions']['mall_name']) ?
			$this->esConfig['priority']['store']['mall_name'] : '';

		$this->should([
			'nested' => [
				'path' => 'link_to_tenant',
				'query' => [
					'query_string' => [
						'query' => $keyword . '*',
						'fields' => [
							'link_to_tenant.country' . $priorityCountry,
							'link_to_tenant.province' . $priorityProvince,
							'link_to_tenant.city' . $priorityCity,
							'link_to_tenant.mall_name' . $priorityMallName,
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
		// $countryCityFilterArr = [];
        // $countryData = null;
        // // filter by country
        // OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$countryCityFilterArr, &$countryData) {
        //     $countryData = Country::select('country_id')->where('name', $countryFilter)->first();

        //     $countryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];

        //     $countryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];
        // });

        // filter by city, only filter when countryFilter is not empty
        // OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, $countryFilter, &$countryCityFilterArr) {
        //     if (! empty($countryFilter)) {
        //         $cityFilterArr = [];
        //         $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.city', '');
        //         foreach ((array) $cityFilters as $cityFilter) {
        //             $cityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
        //         }

        //         if ($shouldMatch != '') {
        //             if (count((array) $cityFilters) === 1) {
        //                 // if user just filter with one city, value of should match must be 100%
        //                 $shouldMatch = '100%';
        //             }
        //             $countryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
        //         }
        //         $countryCityFilterArr['nested']['query']['bool']['should'] = $cityFilterArr;
        //     }
        // });

        // if (! empty($countryCityFilterArr)) {
        //     $jsonQuery['query']['bool']['filter'][] = $countryCityFilterArr;
        // }

		if (isset($area['country'])) {
			$this->must([
				'nested' => [
					'path' => 'link_to_tenant',
					'query' => [
						'match' => [
							'link_to_tenant.country.raw' => $area['country']
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
							'path' => 'link_to_tenant',
							'query' => [
								'match' => [
									'link_to_tenant.city.raw' => $city
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
	 * Filter by CC..?
	 * 
	 * @return [type] [description]
	 */
	public function filterByMyCC($params = [])
	{
		$role = $params['user']->role->role_name;
        if (strtolower($role) === 'consumer') {
            $userId = $params['user']->user_id;
            $sponsorProviderIds = array();

            // get user ewallet
            $userEwallet = UserSponsor::select('sponsor_providers.sponsor_provider_id as ewallet_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'user_sponsor.sponsor_id')
                                      ->where('user_sponsor.sponsor_type', 'ewallet')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->get();

            if (! $userEwallet->isEmpty()) {
              foreach ($userEwallet as $ewallet) {
                $sponsorProviderIds[] = $ewallet->ewallet_id;
              }
            }

            $userCreditCard = UserSponsor::select('sponsor_credit_cards.sponsor_credit_card_id as credit_card_id')
                                      ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'user_sponsor.sponsor_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'sponsor_credit_cards.sponsor_provider_id')
                                      ->where('user_sponsor.sponsor_type', 'credit_card')
                                      ->where('sponsor_credit_cards.status', 'active')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->get();

            if (! $userCreditCard->isEmpty()) {
              foreach ($userCreditCard as $creditCard) {
                $sponsorProviderIds[] = $creditCard->credit_card_id;
              }
            }

            if (! empty($sponsorProviderIds) && is_array($sponsorProviderIds)) {
                $this->must([
                	'nested' => [
                		'path' => 'sponsor_provider',
                		'query' => [
                			'terms' => [
                				'sponsor_provider.sponsor_id' => $sponsorProviderIds
                			]
                		]
                	],
                ]);

                return $sponsorProviderIds;
            }
        }

        return [];
	}

	public function filterBySponsors($sponsorProviderIds = [])
	{
		$sponsorProviderIds = array_values($sponsorProviderIds);
        // $withSponsorProviderIds = array(
        // 	'nested' => array(
        // 		'path' => 'sponsor_provider', 
        // 		'query' => array(
        // 			'filtered' => array(
        // 				'filter' => array(
        // 					'terms' => array('sponsor_provider.sponsor_id' => $sponsorProviderIds)
        // 				)
        // 			)
        // 		)
        // 	)
        // );

        // $jsonQuery['query']['bool']['filter'][] = $withSponsorProviderIds;

        $this->must([
    		'nested' => [
    			'path' => 'sponsor_provider',
    			'query' => [
    				'terms' => [
    					'sponsor_provider.sponsor_id' => $sponsorProviderIds
    				]
    			]
    		]
        ]);
	}

	/**
	 * Exclude some stores from the result.
	 * 
	 * @param  array  $excludedId [description]
	 * @return [type]             [description]
	 */
	public function exclude($excludedId = [])
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
		$this->sort(['name' => ['order' => 'asc']]);
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
	 * Sort store by created date.
	 * 
	 * @param  string $sortingScript [description]
	 * @return [type]                [description]
	 */
	public function sortByCreatedDate($order = 'desc')
	{
		$this->sort([
			'begin_date' => [
				'order' => $order
			]
		]);
	}

	/**
	 * Sort store by updated date.
	 * 
	 * @param  string $sortingScript [description]
	 * @return [type]                [description]
	 */
	public function sortByUpdatedDate($order = 'desc')
	{
		$this->sort([
			'updated_at' => [
				'order' => $order
			]
		]);
	}

	public function addReviewFollowScript($params = [])
	{
		// calculate rating and review based on location/mall
        // $scriptFieldRating = "double counter = 0; double rating = 0;";
        // $scriptFieldReview = "double review = 0;";

        // if (! empty($mallId)) {
        //     $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('mall_rating.rating_" . $mallId . "')) { if (! doc['mall_rating.rating_" . $mallId . "'].empty) { counter = counter + doc['mall_rating.review_" . $mallId . "'].value; rating = rating + (doc['mall_rating.rating_" . $mallId . "'].value * doc['mall_rating.review_" . $mallId . "'].value);}};";
        //     $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('mall_rating.review_" . $mallId . "')) { if (! doc['mall_rating.review_" . $mallId . "'].empty) { review = review + doc['mall_rating.review_" . $mallId . "'].value;}}; ";
        // } else if (! empty($cityFilters)) {
        //     $countryId = $countryData->country_id;
        //     foreach ((array) $cityFilters as $cityFilter) {
        //         $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
        //         $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
        //     }
        // } else if (! empty($countryFilter)) {
        //     $countryId = $countryData->country_id;
        //     $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { if (! doc['location_rating.rating_" . $countryId . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);}}; ";
        //     $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "')) { if (! doc['location_rating.review_" . $countryId . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "'].value;}}; ";
        // } else {
        //     $mallCountry = Mall::groupBy('country')->lists('country');
        //     $countries = Country::select('country_id')->whereIn('name', $mallCountry)->get();

        //     foreach ($countries as $country) {
        //         $countryId = $country->country_id;
        //         $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { if (! doc['location_rating.rating_" . $countryId . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);}}; ";
        //         $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "')) { if (! doc['location_rating.review_" . $countryId . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "'].value;}}; ";
        //     }
        // }

        // $scriptFieldRating = $scriptFieldRating . " if(counter == 0 || rating == 0) {return 0;} else {return rating/counter;}; ";
        // $scriptFieldReview = $scriptFieldReview . " if(review == 0) {return 0;} else {return review;}; ";
        
        // calculate rating and review based on location/mall
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";

        if (! empty($params['mallId'])) {
            $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('mall_rating.rating_" . $params['mallId'] . "')) { if (! doc['mall_rating.rating_" . $params['mallId'] . "'].empty) { counter = counter + doc['mall_rating.review_" . $params['mallId'] . "'].value; rating = rating + (doc['mall_rating.rating_" . $params['mallId'] . "'].value * doc['mall_rating.review_" . $params['mallId'] . "'].value);}};";
            $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('mall_rating.review_" . $params['mallId'] . "')) { if (! doc['mall_rating.review_" . $params['mallId'] . "'].empty) { review = review + doc['mall_rating.review_" . $params['mallId'] . "'].value;}}; ";
        } else if (! empty($params['cityFilters'])) {
            $countryId = $params['countryData']->country_id;
            foreach ((array) $params['cityFilters'] as $cityFilter) {
                $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
                $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
            }
        } else if (! empty($params['countryFilter'])) {
            $countryId = $params['countryData']->country_id;
            $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { if (! doc['location_rating.rating_" . $countryId . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);}}; ";
            $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "')) { if (! doc['location_rating.review_" . $countryId . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "'].value;}}; ";
        } else {
            $mallCountry = Mall::groupBy('country')->lists('country');
            $countries = Country::select('country_id')->whereIn('name', $mallCountry)->get();

            foreach ($countries as $country) {
                $countryId = $country->country_id;
                $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { if (! doc['location_rating.rating_" . $countryId . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);}}; ";
                $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "')) { if (! doc['location_rating.review_" . $countryId . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "'].value;}}; ";
            }
        }

        $scriptFieldRating = $scriptFieldRating . " if(counter == 0 || rating == 0) {return 0;} else {return rating/counter;}; ";
        $scriptFieldReview = $scriptFieldReview . " if(review == 0) {return 0;} else {return review;}; ";

        // Add script fields into request body...
        $this->scriptFields([
            'average_rating' => $scriptFieldRating,
            'total_review' => $scriptFieldReview,
        ]);

        return compact('scriptFieldRating', 'scriptFieldReview');

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
}