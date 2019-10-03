<?php

use Orbit\Helper\Elasticsearch\Search;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Stores...
*/
class StoreSearch extends CampaignSearch
{
    protected $objectType = 'stores';
    protected $objectTypeAlias = 'tenant';

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

    protected function setPriorityForLinkToTenant($objType, $keyword)
    {
        $priorityCountry = isset($this->esConfig['priority'][$objType]['country']) ?
            $this->esConfig['priority'][$objType]['country'] : '';

        $priorityProvince = isset($this->esConfig['priority'][$objType]['province']) ?
            $this->esConfig['priority'][$objType]['province'] : '';

        $priorityCity = isset($this->esConfig['priority'][$objType]['city']) ?
            $this->esConfig['priority'][$objType]['city'] : '';

        $priorityMallName = isset($this->esConfig['priority'][$objType]['mall_name']) ?
            $this->esConfig['priority'][$objType]['mall_name'] : '';

        $this->should([
            'nested' => [
                'path' => 'link_to_tenant',
                'query' => [
                    'query_string' => [
                        'query' => '*' . $keyword . '*',
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
     * Implement filter by keyword...
     *
     * @param  string $keyword [description]
     * @return [type]          [description]
     */
    public function filterByKeyword($keyword = '')
    {
        //TODO: remove this implementation
        //this is required because ESConfig['priority'] not using
        //consistent key for store (other using plural). To remove need to
        //override we should change key
        //ESConfig['priority']['store] to ESConfig['priority']['stores']
        $this->setPriorityForQueryStr('store', $keyword);
        $this->setPriorityForLinkToTenant('store', $keyword);
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
     * Add filter to advert_stores index.
     *
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public function filterAdvertStores($options = [])
    {
        $this->must([
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

    /**
     * Filter store with advert...
     *
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public function filterWithAdvert($options = [])
    {
        $esAdvertStoreIndex = $this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_stores']['index'];
        $advertStoreSearch = new AdvertSearch($this->esConfig, 'advert_stores');

        $advertStoreSearch->setPaginationParams(['from' => 0, 'size' => 100]);

        $advertStoreSearch->filterStores($options);

        $this->filterAdvertStores($options);

        $advertStoreSearchResult = $advertStoreSearch->getResult();

        if ($advertStoreSearchResult['hits']['total'] > 0) {
            $advertList = $advertStoreSearchResult['hits']['hits'];
            $excludeId = array();
            $withPreferred = array();

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
                if ($options['list_type'] === 'featured') {
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
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

            // We need to sort (or put) the advertised items in the beginning of the result set.
            $this->sortBy($options['advertStoreOrdering']);

            // Add advert_stores to the main query indices.
            $this->setIndex($this->getIndex() . ',' . $esAdvertStoreIndex);
        }
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
        $scripts = $this->getReviewRatingScript($params);

        $scriptFieldFollow = "int follow = 0;";
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
            'average_rating' => $scripts['scriptFieldRating'],
            'total_review' => $scripts['scriptFieldReview'],
            'is_follow' => $scriptFieldFollow
        ]);

        return array_merge($scripts, compact('scriptFieldFollow', 'objectFollow'));

        //////// END RATING & FOLLOW SCRIPTS /////
    }

    /**
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    public function sortByNearest($ul = null)
    {
        $this->nearestSort('tenant_detail', 'tenant_detail.position', $ul);
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

    /**
     * filter by gender
     *
     * @return void
     */
    public function filterByGender($gender = '')
    {
        switch ($gender){
            case 'male':
                 $this->mustNot(['match' => ['gender' => 'F']]);
                 break;
            case 'female':
                 $this->mustNot(['match' => ['gender' => 'M']]);
                 break;
            default:
                // do nothing
        }
    }
}
