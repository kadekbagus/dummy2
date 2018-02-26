<?php

use Orbit\Helper\Elasticsearch\Search;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Malls...
*/
class MallSearch extends Search
{
	function __construct($ESConfig = [])
	{
		parent::__construct($ESConfig);

		$this->setDefaultSearchParam();

		$this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices']['malldata']['index']);
		$this->setType($this->esConfig['indices']['malldata']['type']);
	}
	
	public function filterBase()
	{
		$this->must([
			'match' => [
				'is_subscribed' => 'Y'
			]
		]);

		$this->must([
			'match' => ['status' => 'active']
		]);
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
		$priorityName = isset($this->esConfig['priority']['mall']['name']) ? 
			$this->esConfig['priority']['mall']['name'] : '^6';

		$priorityDescription = isset($this->esConfig['priority']['mall']['description']) ? 
			$this->esConfig['priority']['mall']['description'] : '^5';

		$priorityKeywords = isset($this->esConfig['priority']['mall']['keywords']) ? 
			$this->esConfig['priority']['mall']['keywords'] : '^4';

		$this->must([
			'bool' => [
				'should' => [
					[
						'query_string' => [
							'query' => '*' . $keyword . '*',
							'fields' => [
								'lowercase_name' . $priorityName, 
								'description' . $priorityDescription, 
								'keywords' . $priorityKeywords,
							]
						]
					]
				]
			]
		]);

		$priorityCountry = isset($this->esConfig['priority']['mall']['country']) ?
			$this->esConfig['priority']['mall']['country'] : '';

		$priorityProvince = isset($this->esConfig['priority']['mall']['province']) ?
			$this->esConfig['priority']['mall']['province'] : '';

		$priorityCity = isset($this->esConfig['priority']['mall']['city']) ?
			$this->esConfig['priority']['mall']['city'] : '';

		$this->should([
			'query_string' => [
				'query' => '*' . $keyword . '*',
				'fields' => [
					'country' . $priorityCountry,
					'province' . $priorityProvince,
					'city' . $priorityCity,
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
		if (! empty($area['country'])) {
			$this->must([
				'match' => [
					'country.raw' => $area['country']
				]
			]);
		}

		if (! empty($area['cities'])) {

			$citiesQuery['bool']['should'] = [];

			foreach($area['cities'] as $city) {
				$citiesQuery['bool']['should'][] = [
					'match' => [
						'city.raw' => $city
					]
				];
			}

			if (! empty($citiesQuery)) {
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
		$scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        $scriptFieldFollow = "int follow = 0;";

        if (! empty($params['cityFilters'])) {
            // count total review and average rating based on city filter
            $countryId = $params['countryData']->country_id;
            foreach ((array) $params['cityFilters'] as $cityFilter) {
                $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
                $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
            }
        } else if (! empty($countryFilter)) {
            // count total review and average rating based on country filter
            $countryId = $params['countryData']->country_id;
            $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "')) { if (! doc['location_rating.rating_" . $countryId . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);}}; ";
            $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "')) { if (! doc['location_rating.review_" . $countryId . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "'].value;}}; ";
        } else {
            // count total review and average rating based in all location
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

        $role = $params['user']->role->role_name;
        $objectFollow = [];
        if (strtolower($role) === 'consumer') {
            $objectFollow = $this->getUserFollow($params['user']); // return array of followed mall_id

            if (! empty($objectFollow)) {
                if ($sort_by === 'followed') {
                    foreach ($objectFollow as $followId) {
                        $scriptFieldFollow = $scriptFieldFollow . " if (doc.containsKey('merchant_id')) { if (! doc['merchant_id'].empty) { if (doc['merchant_id'].value.toLowerCase() == '" . strtolower($followId) . "'){ follow = 1; }}};";
                    }

                    $scriptFieldFollow = $scriptFieldFollow . " if(follow == 0) {return 0;} else {return follow;}; ";
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

	public function sortByUpdatedAt()
	{
		$this->sort(['updated_at' => ['order' => 'desc']]);
	}

	/**
	 * Bypass Malls ordering...
	 * 
	 * @param  array  $params [description]
	 * @return [type]         [description]
	 */
	public function bypassMallOrder($params = [])
	{
		$mallFeaturedIds =  Config::get('orbit.featured.mall_ids.all', []);

        if (! empty($params['countryFilter'])) {
            $params['countryFilter'] = strtolower($params['countryFilter']);
            $mallFeaturedIds = Config::get('orbit.featured.mall_ids.' . $countryFilter . '.all', []);

            if (! empty($params['cityFilters'])) {
                $mallFeaturedIds = [];
                foreach ($params['cityFilters'] as $key => $cityName) {
                    $cityName = str_replace(' ', '_', strtolower($cityName));
                    $cityValue = Config::get('orbit.featured.mall_ids.' . $countryFilter . '.' . $cityName, []);

                    if (! empty($cityValue)) {
                        $mallFeaturedIds = array_merge($cityValue, $mallFeaturedIds);
                    }
                }
            }
        }

        $mallFeaturedIds = array_unique($mallFeaturedIds);

        if (! empty($mallFeaturedIds)) {
            $withScore = TRUE;
            $esFeaturedBoost = Config::get('orbit.featured.es_boost', 10);
            // $mallOrder = array(array('terms' => array('_id' => $mallFeaturedIds, 'boost' => $esFeaturedBoost)), array('match_all' => new stdClass()));

            $this->should([
            	'terms' => [
            		[
	            		'_id' => $mallFeaturedIds,
	            		'boost' => $esFeaturedBoost
	            	],
            	],
            	'match_all' => new stdClass()
            ]);
        }
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
				'query' => [],
				'track_scores' => true,
				'sort' => []
			]
		];
	}
}