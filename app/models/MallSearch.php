<?php


use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Malls...
*/
class MallSearch extends ObjectTypeSearch
{
    protected $objectType = 'malldata';
    protected $objectTypeAlias = 'malldata';

    /**
     * Basic requirements of malls that will be listed.
     *
     * @return [type] [description]
     */
    public function filterBase()
    {
        $this->constantScoring('must', [
            'match' => ['is_subscribed' => 'Y']
        ]);

        $this->constantScoring('must', [
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

        $priorityObjectType = isset($this->esConfig['priority']['mall']['object_type']) ?
            $this->esConfig['priority']['mall']['object_type'] : '^5';

        $priorityDescription = isset($this->esConfig['priority']['mall']['description']) ?
            $this->esConfig['priority']['mall']['description'] : '^3';

        $priorityAddressLine = isset($this->esConfig['priority']['mall']['address_line']) ?
            $this->esConfig['priority']['mall']['address_line'] : '';

        $this->must([
            'query_string' => [
                'query' => '*' . $keyword . '*',
                'fields' => [
                    'name' . $priorityName,
                    'object_type' . $priorityObjectType,
                    'description' . $priorityDescription,
                    'address_line' . $priorityAddressLine,
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
     * Filter by Country and Cities...
     *
     * @param  array  $area [description]
     * @return [type]       [description]
     */
    public function filterByCountryAndCities($area = [])
    {
        if (! empty($area['country'])) {
            $this->constantScoring('must', [
                'match' => [
                    'country.raw' => $area['country']
                ]
            ]);
        }

        if (! empty($area['cities'])) {
            foreach($area['cities'] as $city) {
                $this->constantScoring('should', [
                    'match' => [
                        'city.raw' => $city
                    ]
                ]);
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

    public function filterWithAdvert($params = [])
    {
        $esAdvertMallIndex = $this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_malls']['index'];
        $advertMallsSearch = new AdvertStoreSearch($this->esConfig, 'advert_malls');

        $advertMallsSearch->setPaginationParams(['from' => 0, 'size' => 100]);

        if ($params['list_type'] === 'featured') {
            $pageTypeScore = 'featured_gtm_score';
        } else {
            $pageTypeScore = 'preferred_gtm_score';
        }

        $sortPageScript = "if (doc.containsKey('" . $pageTypeScore . "')) { if(! doc['" . $pageTypeScore . "'].empty) { return doc['" . $pageTypeScore . "'].value } else { return 0}} else {return 0}";
        $params['advertSorting'] = [
            '_script' => [
                'script' => $sortPageScript,
                'type' => 'string',
                'order' => 'desc'
            ]
        ];

        // @todo add sort page in advert list..
        $advertMallsSearch->filterMalls($params);

        // return $advertMallsSearch->getRequestParam('body');

        $this->filterAdvertMalls($params);

        $advertMallsSearchResult = $advertMallsSearch->getResult();

        $withPreferred = array();
        if ($advertMallsSearchResult['hits']['total'] > 0) {
            $advertList = $advertMallsSearchResult['hits']['hits'];
            $excludeId = array();

            foreach ($advertList as $adverts) {
                $advertId = $adverts['_id'];
                $merchantId = $adverts['_source']['merchant_id'];
                if(! in_array($merchantId, $excludeId)) {
                    // record merchant_id that have advert, because we need to exclude that merchant_id in the next query
                    $excludeId[] = $merchantId;
                } else {
                    // record only 1 advert_id, so only 1 advert appear in list
                    $excludeId[] = $advertId;
                }

                // if in featured list, check also the store is have preferred adv or not
                if ($params['list_type'] === 'featured') {
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                        // $withPreferred[$merchantId] = $adverts['_source']['advert_type'];
                        if (empty($withPreferred[$merchantId]) || $withPreferred[$merchantId] != 'preferred_list_large') {
                            $withPreferred[$merchantId] = 'preferred_list_regular';
                            if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                $withPreferred[$merchantId] = 'preferred_list_large';
                            }
                        }
                    }
                }
            }

            // exclude_id useful for eliminate duplicate store in the list, because after this, we query to advert_stores and store index together
            $this->exclude($excludeId);

            // If there any advert_stores in the list, then sort by it first...
            $pageTypeScore = '';
            $sortPageScripts = [];
            if ($params['list_type'] === 'featured') {
                $pageTypeScore = 'featured_gtm_score';
            } else {
                $pageTypeScore = 'preferred_gtm_score';
            }

            $sortPageScripts[] = "if (doc.containsKey('" . $pageTypeScore . "')) { if(! doc['" . $pageTypeScore . "'].empty) { return doc['" . $pageTypeScore . "'].value } else { return 0}} else {return 0}";

            // Add secondary sort by preferred type..
            if ($params['list_type'] === 'featured') {
                $sortPageScripts[] = "if (doc.containsKey('preferred_gtm_score')) { if(! doc['preferred_gtm_score'].empty) { return doc['preferred_gtm_score'].value } else { return 0}} else {return 0}";
            }

            foreach($sortPageScripts as $sortPageScript) {
                $advertOrdering = [
                    '_script' => [
                        'script' => $sortPageScript,
                        'type' => 'string',
                        'order' => 'desc'
                    ]
                ];

                $this->sortBy($advertOrdering);
            }

            $this->setIndex($esAdvertMallIndex . ',' . $this->getIndex());
        }

        return compact('withPreferred');
    }

    /**
     * Exclude the partner competitors from the result.
     *
     * @param  array  $competitors [description]
     * @return [type]              [description]
     */
    public function excludePartnerCompetitors($partnerIds = [])
    {
        $this->mustNot([
            'terms' => [
                'partner_ids' => $partnerIds
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

    protected function buildRatingReviewCalcScript($params = [])
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        $scriptFieldFollow = "int follow = 0;";

        if (!empty($params['countryData']) && ! empty($params['cityFilters'])) {
            // count total review and average rating based on city filter
            $countryId = $params['countryData']->country_id;
            foreach ((array) $params['cityFilters'] as $cityFilter) {
                $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
                $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
            }
        } else if (! empty($params['countryData']) && ! empty($countryFilter)) {
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

        $role = $params['user']->role->role_name;
        $objectFollow = [];
        if (strtolower($role) === 'consumer') {
            $objectFollow = $this->getUserFollow($params['user']); // return array of followed mall_id

            if (! empty($objectFollow)) {
                if ($params['sortBy'] === 'followed') {
                    foreach ($objectFollow as $followId) {
                        $scriptFieldFollow = $scriptFieldFollow . " if (doc.containsKey('merchant_id')) { if (! doc['merchant_id'].empty) { if (doc['merchant_id'].value.toLowerCase() == '" . strtolower($followId) . "'){ follow = 1; }}};";
                    }

                    $scriptFieldFollow = $scriptFieldFollow . " if(follow == 0) {return 0;} else {return follow;}; ";
                }
            }
        }

        return compact('scriptFieldRating', 'scriptFieldReview', 'scriptFieldFollow', 'objectFollow');
    }

    protected function getReviewRatingScript($params = [])
    {
        $scripts = $this->buildRatingReviewCalcScript($params);
        $scriptFieldRating = $scripts['scriptFieldRating'] . " " .
        "if (counter == 0 || rating == 0) {
            return 0;
        } else {
            return rating/counter;
        }; ";
        $scriptFieldReview = $scripts['scriptFieldReview'] . " " .
        "if (review == 0) {
            return 0;
        } else {
            return review;
        }; ";

        return array_merge($scripts, compact('scriptFieldRating', 'scriptFieldReview'));
    }

    public function addReviewFollowScript($params = [])
    {
        $scripts = $this->getReviewRatingScript($params);
        $this->scriptFields([
            'average_rating' => $scripts['scriptFieldRating'],
            'total_review' => $scripts['scriptFieldReview'],
            'is_follow' => $scripts['scriptFieldFollow'],
        ]);
        return $scripts;
    }

    public function sortByUpdatedAt()
    {
        $this->sort(['updated_at' => ['order' => 'desc']]);
    }

    /**
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    public function sortByNearest($ul = null)
    {
        $this->nearestSort(nul, 'position', $ul);
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
            $mallFeaturedIds = Config::get('orbit.featured.mall_ids.' . $params['countryFilter'] . '.all', []);

            if (! empty($params['cityFilters'])) {
                $mallFeaturedIds = [];
                foreach ($params['cityFilters'] as $key => $cityName) {
                    $cityName = str_replace(' ', '_', strtolower($cityName));
                    $cityValue = Config::get('orbit.featured.mall_ids.' . $params['countryFilter'] . '.' . $cityName, []);

                    if (! empty($cityValue)) {
                        $mallFeaturedIds = array_merge($cityValue, $mallFeaturedIds);
                    }
                }
            }
        }

        $mallFeaturedIds = array_unique($mallFeaturedIds);

        if (! empty($mallFeaturedIds)) {

            // No need this, because we already have ordering in MallListNewAPIController.php
            // Make sure to sort by score first...
            // $this->sortByRelevance();

            $esFeaturedBoost = Config::get('orbit.featured.es_boost', 10);

            $this->should([
                'terms' => [
                    '_id' => $mallFeaturedIds,
                    'boost' => $esFeaturedBoost
                ]
            ]);

            $this->should([
                'match_all' => new stdClass()
            ]);
        }
    }

    // check user follow
    public function getUserFollow($user)
    {
        $follow = FollowStatusChecker::create()
                                    ->setUserId($user->user_id)
                                    ->setObjectType('mall')
                                    ->getFollowStatus();

        return $follow;
    }

    public function filterAdvertMalls($options = [])
    {
        $this->filter([
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                [
                                    'query' => [
                                        'match' => [
                                            'advert_status' => 'active'
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'advert_start_date' => [
                                            'lte' => $options['dateTimeEs']
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'advert_end_date' => [
                                            'gte' => $options['dateTimeEs']
                                        ]
                                    ]
                                ],
                                [
                                    'match' => [
                                        'advert_location_ids' => $options['locationId']
                                    ]
                                ],
                                [
                                    'terms' => [
                                        'advert_type' => $options['advertType']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'bool' => [
                            'must_not' => [
                                [
                                    'exists' => [
                                        'field' => 'advert_status'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }
}
