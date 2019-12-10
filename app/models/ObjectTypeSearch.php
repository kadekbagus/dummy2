<?php

use Orbit\Helper\Elasticsearch\Search;
use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for campaign...
*/
abstract class ObjectTypeSearch extends Search
{
    protected $objectType = null;
    protected $objectTypeAlias = null;

    private $mallCountryList = null;

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
     * Add items into excludedIds list.
     * The list will be added into the search param body query later.
     *
     * @param  array  $excludedId [description]
     * @return void
     */
    public function exclude($excludedIds = null)
    {
        if ( empty($excludedIds)) return;

        if (! is_array($excludedIds)) {
            $excludedIds = [$excludedIds];
        }

        $this->excludedIds = array_unique(array_merge($this->excludedIds, $excludedIds));
    }

    /**
     * Sort by name..
     *
     * @return [type] [description]
     */
    public function sortByName($language = 'id', $sortMode = 'asc')
    {
        $this->sort(['lowercase_name' => ['order' => $sortMode]]);
    }

    /**
     * Sort store by rating.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByRating($sortingScript = '', $sortMode = 'desc')
    {
        $this->sort([
            '_script' => [
                'script' => $sortingScript,
                'type' => 'number',
                'order' => $sortMode
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

    protected function getMallCountryList()
    {
        //TODO : cache this call database call as we may need to call it several times
        if (empty($this->mallCountryList)) {
            $mallCountry = Mall::groupBy('country')->lists('country');
            $countries = Country::select('country_id')->whereIn('name', $mallCountry)->get();
            $this->mallCountryList = $countries;
        } else {
            $countries = $this->mallCountryList;
        }
        return $countries;
    }

    protected function buildRatingReviewCalcScriptByCountryCities($countryId, $cityFilters)
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        // count total review and average rating based on city filter
        foreach ($cityFilters as $cityFilter) {
            $cities = str_replace(" ", "_", trim(strtolower($cityFilter), " "));
            $ratingKey = "location_rating.rating_{$countryId}_{$cities}";
            $reviewKey = "location_rating.review_{$countryId}_{$cities}";
            $scriptFieldRating = $scriptFieldRating . ' ' .
            "if (doc.containsKey('{$ratingKey}')) {
                if (! doc['{$ratingKey}'].empty) {
                    counter = counter + doc['{$reviewKey}'].value;
                    rating = rating + (doc['{$ratingKey}'].value * doc['{$reviewKey}'].value);
                }
            }; ";
            $scriptFieldReview = $scriptFieldReview . ' ' .
            "if (doc.containsKey('{$reviewKey}')) {
                if (! doc['{$reviewKey}'].empty) {
                    review = review + doc['{$reviewKey}'].value;
                }
            }; ";
        }
        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    protected function buildRatingReviewCalcScriptByMallId($mallId)
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        $ratingKey = "mall_rating.rating_{$mallId}";
        $reviewKey = "mall_rating.review_{$mallId}";
        $scriptFieldRating = $scriptFieldRating . " " .
        "if (doc.containsKey('{$ratingKey}')) {
            if (! doc['{$ratingKey}'].empty) {
                counter = counter + doc['{$reviewKey}'].value;
                rating = rating + (doc['{$ratingKey}'].value * doc['{$reviewKey}'].value);
            }
        };";
        $scriptFieldReview = $scriptFieldReview . " " .
        "if (doc.containsKey('{$reviewKey}')) {
            if (! doc['{$reviewKey}'].empty) {
                review = review + doc['{$reviewKey}'].value;
            }
        };";
        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    protected function buildRatingReviewCalcScriptByCountries($countryIds)
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        foreach ($countryIds as $countryId) {
            // count total review and average rating based on country filter
            $ratingKey = "location_rating.rating_{$countryId}";
            $reviewKey = "location_rating.review_{$countryId}";
            $scriptFieldRating = $scriptFieldRating . ' ' .
            "if (doc.containsKey('{$ratingKey}')) {
                if (! doc['{$ratingKey}'].empty) {
                    counter = counter + doc['{$reviewKey}'].value;
                    rating = rating + (doc['{$ratingKey}'].value * doc['{$reviewKey}'].value);
                }
            }; ";
            $scriptFieldReview = $scriptFieldReview . ' ' .
            "if (doc.containsKey('{$reviewKey}')) {
                if (! doc['{$reviewKey}'].empty) {
                    review = review + doc['{$reviewKey}'].value;
                }
            }; ";
        }

        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    protected function buildRatingReviewCalcScriptByMallCountries()
    {
        $countries = $this->getMallCountryList();
        $countryIds = [];
        foreach ($countries as $country) {
            $countryIds[] = $country->country_id;
        }
        return $this->buildRatingReviewCalcScriptByCountries($countryIds);
    }

    protected function buildRatingReviewCalcScript($params = [])
    {
        if (! empty($params['mallId'])) {
            return $this->buildRatingReviewCalcScriptByMallId($params['mallId']);
        } else if (! empty($params['countryData']) && ! empty($params['cityFilters'])) {
            $countryId = $params['countryData']->country_id;
            return $this->buildRatingReviewCalcScriptByCountryCities($countryId, $params['cityFilters']);
        } else if (! empty($params['countryData']) && empty($params['cityFilter'])) {
            $countryId = $params['countryData']->country_id;
            return $this->buildRatingReviewCalcScriptByCountries([$countryId]);
        } else {
            return $this->buildRatingReviewCalcScriptByMallCountries();
        }
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

        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    protected function getRatingFilterScript($params = [])
    {
        $scripts = $this->buildRatingReviewCalcScript($params);
        return $scripts['scriptFieldRating'] . " " .
        "return (counter == 0 && rateLow == 0) || ".
        "((counter>0) && (rating/counter >= rateLow) && (rating/counter <= rateHigh));";
    }

    public function addReviewFollowScript($params = [])
    {
        $scripts = $this->getReviewRatingScript($params);
        // Add script fields into request body...
        $this->scriptFields([
            'average_rating' => $scripts['scriptFieldRating'],
            'total_review' => $scripts['scriptFieldReview'],
        ]);

        return $scripts;
    }

    /**
     * filter by using a script
     *
     * @return void
     */
    public function filterByScript($aScript, $params = [])
    {
        $scriptData = [
            'script' => $aScript
        ];
        if (!empty($params)) {
            $scriptData['params'] = $params;
        }
        $this->filter(['script' => $scriptData ]);
    }

    public function filterByRating($ratingLow, $ratingHigh, $params)
    {
        $rateLow = (double) $ratingLow;
        //TODO: +0.01 is hack because they way rating is added in update queue
        //for example ESNewsUpdateQueue.php (line 189)
        //need to fix them.
        $rateHigh = (double) $ratingHigh + 0.001;

        $this->filterByScript(
             $this->getRatingFilterScript($params),
             compact('rateLow', 'rateHigh')
        );
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
    protected function nearestSort($item, $itemPos, $ul = null)
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
            $geoData = [
                '_geo_distance'=> [
                    $itemPos => [
                        'lon' => $longitude,
                        'lat' => $latitude
                    ],
                    'order'=> 'asc',
                    'unit'=> 'km',
                    'distance_type'=> 'plane'
                ]
            ];
            if (! empty($item)) {
                $geoData['_geo_distance']['nested_path'] = $item;
            }
            $this->sort($geoData);
        }

        $this->sortByName();
    }

    /**
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    abstract public function sortByNearest($ul = null);

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

}
