<?php

use Orbit\Helper\Elasticsearch\Search;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Stores...
*/
class CouponSearch extends Search
{
    function __construct($ESConfig = [])
    {
        parent::__construct($ESConfig);

        $this->setDefaultSearchParam();

        $this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices']['coupons']['index']);
        $this->setType($this->esConfig['indices']['coupons']['type']);
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
        $arrCategories = [];

        foreach($categories as $category) {
            $arrCategories[] = ['match' => ['category_ids' => $category]];
        }

        $this->must([
            'bool' => [
                'should' => $arrCategories
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

    }

    /**
     * Implement filter by keyword...
     *
     * @param  string $keyword [description]
     * @return [type]          [description]
     */
    public function filterByKeyword($keyword = '')
    {
        $priorityName = isset($this->esConfig['priority']['coupons']['name']) ?
            $this->esConfig['priority']['coupons']['name'] : '^6';

        $priorityObjectType = isset($this->esConfig['priority']['coupons']['object_type']) ?
            $this->esConfig['priority']['coupons']['object_type'] : '^5';

        $priorityDescription = isset($this->esConfig['priority']['coupons']['description']) ?
            $this->esConfig['priority']['coupons']['description'] : '^4';

        $priorityKeywords = isset($this->esConfig['priority']['coupons']['keywords']) ?
            $this->esConfig['priority']['coupons']['keywords'] : '^3';

        $priorityProductTags = isset($this->esConfig['priority']['coupons']['product_tags']) ?
            $this->esConfig['priority']['coupons']['product_tags'] : '^3';

        $this->must([
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'query' => '*' . $keyword . '*',
                            'fields' => [
                                'name' . $priorityName,
                                'object_type' . $priorityObjectType,
                                'description' . $priorityDescription,
                                'keywords' . $priorityKeywords,
                                'product_tags' . $priorityProductTags,
                            ]
                        ]
                    ],
                    [
                        'nested' => [
                            'path' => 'translation',
                            'query' => [
                                'match' => [
                                    'translation.description' . $priorityDescription => $keyword
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ]);

        $priorityCountry = isset($this->esConfig['priority']['coupons']['country']) ?
            $this->esConfig['priority']['coupons']['country'] : '';

        $priorityProvince = isset($this->esConfig['priority']['coupons']['province']) ?
            $this->esConfig['priority']['coupons']['province'] : '';

        $priorityCity = isset($this->esConfig['priority']['coupons']['city']) ?
            $this->esConfig['priority']['coupons']['city'] : '';

        $priorityMallName = isset($this->esConfig['priority']['coupons']['mall_name']) ?
            $this->esConfig['priority']['coupons']['mall_name'] : '';

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
     * Filte by Country and Cities...
     *
     * @param  array  $area [description]
     * @return [type]       [description]
     */
    public function filterByCountryAndCities($area = [])
    {
        if (! empty($area['country'])) {
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

    public function filterAdvertCoupons($options = [])
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
     * Filter normal coupons and the advertised ones.
     *
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public function filterWithAdvert($options = [])
    {
        // Get Advert_Store...
        $esAdvertIndex = $this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_coupons']['index'];
        $advertSearch = new AdvertSearch($this->esConfig, 'advert_coupons');

        $advertSearch->setPaginationParams(['from' => 0, 'size' => 100]);

        $advertSearch->filterCoupons($options);

        $this->filterAdvertCoupons($options);

        $advertSearchResult = $advertSearch->getResult();

        if ($advertSearchResult['hits']['total'] > 0) {
            $advertList = $advertSearchResult['hits']['hits'];
            $excludeId = array();
            $withPreferred = array();

            foreach ($advertList as $adverts) {
                $advertId = $adverts['_id'];
                $newsId = $adverts['_source']['promotion_id'];
                if(! in_array($newsId, $excludeId)) {
                    $excludeId[] = $newsId;
                } else {
                    $excludeId[] = $advertId;
                }

                // if featured list_type check preferred too
                if ($options['list_type'] === 'featured') {
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                        if (empty($withPreferred[$newsId]) || $withPreferred[$newsId] != 'preferred_list_large') {
                            $withPreferred[$newsId] = 'preferred_list_regular';
                            if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                $withPreferred[$newsId] = 'preferred_list_large';
                            }
                        }
                    }
                }
            }

            $this->exclude($excludeId);

            $this->sortBy($options['advertSorting']);

            $this->setIndex($this->getIndex() . ',' . $esAdvertIndex);
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
        $partnerFilter = '';
        if (! empty($partnerId)) {
            $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                             ->where('affected_group_names.group_type', '=', 'coupon');
                                                    })
                                                    ->where('partner_id', $partnerId)
                                                    ->first();

            if (is_object($partnerAffected)) {
                $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                if (in_array($partnerId, $exception)) {
                    $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');

                    $this->mustNot([
                        'terms' => [
                            'partner_ids' => $partnerIds
                        ]
                    ]);
                }
                else {
                    $this->must([
                        'match' => [
                            'partner_ids' => $partnerId
                        ]
                    ]);
                }
            }
        }
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

    /**
     * Filter by Sponsors...
     *
     * @param  array  $sponsorProviderIds [description]
     * @return [type]                     [description]
     */
    public function filterBySponsors($sponsorProviderIds = [])
    {
        $sponsorProviderIds = array_values($sponsorProviderIds);

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
     * Sort by name..
     *
     * @return [type] [description]
     */
    public function sortByName($language = 'id', $sortMode = '')
    {
        $sortScript =  "if(doc['name_" . $language . "'].value != null) { return doc['name_" . $language . "'].value } else { doc['name_default'].value }";

        $this->sort([
            '_script' => [
                'script' => $sortScript,
                'type' => 'string',
                'order' => $sortMode
            ]
        ]);
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
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    public function sortByNearest($ul = null)
    {
        // Get user location ($ul), latitude and longitude.
        // If latitude and longitude doesn't exist in query string, the code will be read cookie to get lat and lon
        if ($ul == null) {
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');

            $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
            if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                $longitude = $userLocationCookieArray[0];
                $latitude = $userLocationCookieArray[1];
            }
        } else {
            $loc = explode('|', $ul);
            $longitude = $loc[0];
            $latitude = $loc[1];
        }

        if (isset($longitude) && isset($latitude))  {
            $this->sort(
                        [
                          '_geo_distance'=> [
                            'nested_path'=> 'link_to_tenant',
                            'link_to_tenant.position'=> [
                              'lon' => $longitude,
                              'lat' => $latitude
                            ],
                            'order'=> 'asc',
                            'unit'=> 'km',
                            'distance_type'=> 'plane'
                          ]
                        ]
                    );
        }

        $this->sortByName();
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
     * filter by gender
     *
     * @return void
     */
    public function filterByGender($gender = '')
    {
        switch ($gender){
            case 'male':
                 $this->mustNot(['match' => ['is_all_gender' => 'F']]);
                 break;
            case 'female':
                 $this->mustNot(['match' => ['is_all_gender' => 'M']]);
                 break;
            default:
                // do nothing
        }
    }

    public function filterPromotionType($type = '')
    {
        if ($type !== '') {
            $this->must(['match' => ['promotion_type' => $type]]);
        }
    }
}