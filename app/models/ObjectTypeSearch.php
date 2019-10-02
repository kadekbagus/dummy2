<?php

use Orbit\Helper\Elasticsearch\Search;
use Log;
use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for campaign...
*/
abstract class ObjectTypeSearch extends Search
{
    protected $objectType = null;
    protected $objectTypeAlias = null;

    public function __construct($ESConfig = [])
    {
        parent::__construct($ESConfig);
        $this->initialize($ESConfig);
    }

    protected function initialize($ESConfig = [])
    {
        $this->setDefaultSearchParam();
        $this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices'][$this->objectType]['index']);
        $this->setType($this->esConfig['indices'][$this->objectType]['type']);
    }

    /**
     * Implement filter by keyword...
     *
     * @param  string $keyword [description]
     * @return [type]          [description]
     */
    abstract public function filterByKeyword($keyword = '');

    /**
     * Filte by Country and Cities...
     *
     * @param  array  $area [description]
     * @return [type]       [description]
     */
    abstract public function filterByCountryAndCities($area = []);

    abstract public function filterByPartner($partnerId = '');


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

    protected function filterAdvertCampaign($options = [])
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

    abstract public function filterWithAdvert($options = []);

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
    abstract public function sortByName($language = 'id', $sortMode = 'asc');

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
            $scriptFieldRating = $scriptFieldRating . " " .
            "if (doc.containsKey('mall_rating.rating_" . $params['mallId'] . "')) {
                if (! doc['mall_rating.rating_" . $params['mallId'] . "'].empty) {
                    counter = counter + doc['mall_rating.review_" . $params['mallId'] . "'].value;
                    rating = rating + (doc['mall_rating.rating_" . $params['mallId'] . "'].value * doc['mall_rating.review_" . $params['mallId'] . "'].value);
                }
            };";
            $scriptFieldReview = $scriptFieldReview . " " .
            "if (doc.containsKey('mall_rating.review_" . $params['mallId'] . "')) {
                if (! doc['mall_rating.review_" . $params['mallId'] . "'].empty) {
                    review = review + doc['mall_rating.review_" . $params['mallId'] . "'].value;
                }
            };";
        } else if (! empty($params['cityFilters'])) {
            $countryId = $params['countryData']->country_id;
            foreach ((array) $params['cityFilters'] as $cityFilter) {
                $scriptFieldRating = $scriptFieldRating . " " .
                "if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) {
                    if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) {
                        counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;
                        rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);
                    }
                }; ";
                $scriptFieldReview = $scriptFieldReview . " " .
                "if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) {
                    if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) {
                        review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;
                    }
                }; ";
            }
        } else if (! empty($params['countryFilter'])) {
            $countryId = $params['countryData']->country_id;
            $scriptFieldRating = $scriptFieldRating . " " .
            "if (doc.containsKey('location_rating.rating_" . $countryId . "')) {
                if (! doc['location_rating.rating_" . $countryId . "'].empty) {
                    counter = counter + doc['location_rating.review_" . $countryId . "'].value;
                    rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);
                }
            };";
            $scriptFieldReview = $scriptFieldReview . " " .
            "if (doc.containsKey('location_rating.review_" . $countryId . "')) {
                if (! doc['location_rating.review_" . $countryId . "'].empty) {
                    review = review + doc['location_rating.review_" . $countryId . "'].value;
                }
            }; ";
        } else {
            $mallCountry = Mall::groupBy('country')->lists('country');
            $countries = Country::select('country_id')->whereIn('name', $mallCountry)->get();

            foreach ($countries as $country) {
                $countryId = $country->country_id;
                $scriptFieldRating = $scriptFieldRating . " " .
                "if (doc.containsKey('location_rating.rating_" . $countryId . "')) {
                    if (! doc['location_rating.rating_" . $countryId . "'].empty) {
                        counter = counter + doc['location_rating.review_" . $countryId . "'].value;
                        rating = rating + (doc['location_rating.rating_" . $countryId . "'].value * doc['location_rating.review_" . $countryId . "'].value);
                    }
                }; ";
                $scriptFieldReview = $scriptFieldReview . " " .
                "if (doc.containsKey('location_rating.review_" . $countryId . "')) {
                    if (! doc['location_rating.review_" . $countryId . "'].empty) {
                        review = review + doc['location_rating.review_" . $countryId . "'].value;
                    }
                }; ";
            }
        }

        $scriptFieldRating = $scriptFieldRating . " " .
        "if (counter == 0 || rating == 0) {
            return 0;
        } else {
            return rating/counter;
        }; ";
        $scriptFieldReview = $scriptFieldReview . " " .
        "if (review == 0) {
            return 0;
        } else {
            return review;
        }; ";

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

    public function getResult($resultMapperClass = '')
    {
        $res = parent::getResult($resultMapperClass);
        $last = $this->client->transport->getLastConnection()->getLastRequestInfo();
        Log::info(print_r($last, true));
        return $res;
    }
}
