<?php namespace Orbit\Controller\API\v1\Pub\News;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use News;
use Advert;
use NewsMerchant;
use Language;
use Validator;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;

class NewsFeaturedListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get active news in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     * @author Rio Astamal <rio@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getFeaturedNews()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;
        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'featured-event-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');
        $advertCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-adverts');

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'created_date');
            $sort_mode = OrbitInput::get('sortmode','desc');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', []);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $category_id = OrbitInput::get('category_id');
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $partnerToken = OrbitInput::get('token', null);
            $viewType = OrbitInput::get('view_type', 'grid');

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $newsHelper = NewsHelper::create();
            $newsHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date,updated_date,rating',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sort_by, 'sort_mode' => $sort_mode, 'language' => $language,
                'location' => $location,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'with_premium' => $withPremium, 'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
                'country' => $countryFilter, 'cities' => $cityFilters,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $newsHelper->getValidLanguage();
            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $withScore = false;
            $esTake = $take;
            if ($list_type === 'featured') {
                $esTake = 50;
            }

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $jsonQuery = array('fields' => array("_source"), 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('status' => 'active'))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

            // get user lat and lon
            if ($sort_by == 'location' || $location == 'mylocation') {
                if (! empty($ul)) {
                    $position = explode("|", $ul);
                    $lon = $position[0];
                    $lat = $position[1];
                } else {
                    // get lon lat from cookie
                    $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
                    if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                        $lon = $userLocationCookieArray[0];
                        $lat = $userLocationCookieArray[1];
                    }
                }
            }

            $withKeywordSearch = false;
            $filterKeyword = [];
            // OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$cacheKey, &$filterKeyword, &$withKeywordSearch)
            // {
            //     $cacheKey['keyword'] = $keyword;

            //     if ($keyword != '') {
            //         $searchFlag = $searchFlag || TRUE;
            //         $withKeywordSearch = true;
            //         $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.keyword', '');

            //         $priority['name'] = Config::get('orbit.elasticsearch.priority.news.name', '^6');
            //         $priority['object_type'] = Config::get('orbit.elasticsearch.priority.news.object_type', '^5');
            //         $priority['keywords'] = Config::get('orbit.elasticsearch.priority.news.keywords', '^4');
            //         $priority['description'] = Config::get('orbit.elasticsearch.priority.news.description', '^3');
            //         $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.news.mall_name', '^3');
            //         $priority['city'] = Config::get('orbit.elasticsearch.priority.news.city', '^2');
            //         $priority['province'] = Config::get('orbit.elasticsearch.priority.news.province', '^2');
            //         $priority['country'] = Config::get('orbit.elasticsearch.priority.news.country', '^2');

            //         $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.name'.$priority['name'], 'translation.description'.$priority['description'])))));

            //         $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'link_to_tenant', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('link_to_tenant.city'.$priority['city'], 'link_to_tenant.province'.$priority['province'], 'link_to_tenant.country'.$priority['country'], 'link_to_tenant.mall_name'.$priority['mall_name'])))));

            //         $filterKeyword['bool']['should'][] = array('multi_match' => array('query' => $keyword, 'fields' => array('object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));

            //         if ($shouldMatch != '') {
            //             $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
            //         }

            //         $jsonQuery['query']['bool']['filter'][] = $filterKeyword;
            //     }
            // });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.parent_id' => $mallId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withMallId;
                }
             });

            // filter by category_id
            // OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
            //     $searchFlag = $searchFlag || TRUE;
            //     $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.category', '');
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

            // OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery, $prefix, &$searchFlag, &$cacheKey) {
            //     $cacheKey['partner_id'] = $partnerId;

            //     $partnerFilter = '';
            //     if (! empty($partnerId)) {
            //         $searchFlag = $searchFlag || TRUE;
            //         $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
            //                                                     $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
            //                                                          ->where('affected_group_names.group_type', '=', 'news');
            //                                                 })
            //                                                 ->where('partner_id', $partnerId)
            //                                                 ->first();

            //         if (is_object($partnerAffected)) {
            //             $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);
            //             $partnerFilter = array('query' => array('match' => array('partner_ids' => $partnerId)));

            //             if (in_array($partnerId, $exception)) {
            //                 $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');
            //                 $partnerFilter = array('query' => array('not' => array('terms' => array('partner_ids' => $partnerIds))));
            //             }
            //             $jsonQuery['query']['bool']['filter'][] = $partnerFilter;
            //         }
            //     }
            // });

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$jsonQuery, &$searchFlag, &$withScore, $lat, $lon, $distance, &$withCache)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;

                    if ($location === 'mylocation' && $lat != '' && $lon != '') {
                        $withCache = FALSE;
                        $locationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat)))))));
                        $jsonQuery['query']['bool']['filter'][] = $locationFilter;
                    } elseif ($location !== 'mylocation') {
                        $locationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.city.raw' => $location))))));
                        $jsonQuery['query']['bool']['filter'][] = $locationFilter;
                    }
                }
            });

            $countryCityFilterArr = [];
            $countryData = null;
            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$countryCityFilterArr, &$countryData) {
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();

                $countryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];

                $countryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, $countryFilter, &$countryCityFilterArr) {
                if (! empty($countryFilter)) {
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.city', '');
                    $cityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
                    }

                    if ($shouldMatch != '') {
                        if (count((array) $cityFilters) === 1) {
                            // if user just filter with one city, value of should match must be 100%
                            $shouldMatch = '100%';
                        }
                        $countryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                    }
                    $countryCityFilterArr['nested']['query']['bool']['should'] = $cityFilterArr;
                }
            });

            if (! empty($countryCityFilterArr)) {
                $jsonQuery['query']['bool']['filter'][] = $countryCityFilterArr;
            }

            // calculate rating and review based on location/mall
            $scriptFieldRating = "double counter = 0; double rating = 0;";
            $scriptFieldReview = "double review = 0;";

            if (! empty($mallId)) {
                $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('mall_rating.rating_" . $mallId . "')) { if (! doc['mall_rating.rating_" . $mallId . "'].empty) { counter = counter + doc['mall_rating.review_" . $mallId . "'].value; rating = rating + (doc['mall_rating.rating_" . $mallId . "'].value * doc['mall_rating.review_" . $mallId . "'].value);}};";
                $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('mall_rating.review_" . $mallId . "')) { if (! doc['mall_rating.review_" . $mallId . "'].empty) { review = review + doc['mall_rating.review_" . $mallId . "'].value;}}; ";
            } else if (! empty($cityFilters)) {
                $countryId = $countryData->country_id;
                foreach ((array) $cityFilters as $cityFilter) {
                    $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
                    $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
                }
            } else if (! empty($countryFilter)) {
                $countryId = $countryData->country_id;
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

            $jsonQuery['script_fields'] = array('average_rating' => array('script' => $scriptFieldRating), 'total_review' => array('script' => $scriptFieldReview));

            // query to get featured list
            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $locationId = ! empty($mallId) ? $mallId : 0;

            $sortByPageType = array();
            $pageTypeScore = 'featured_gtm_score';
            $sortByPageType = array('featured_gtm_score' => array('order' => 'desc'));
            if (! empty($mallId)) {
                $pageTypeScore = 'featured_mall_score';
                $sortByPageType = array('featured_mall_score' => array('order' => 'desc'));
            }

            $esFeaturedQuery = $jsonQuery;
            $esFeaturedQuery['query']['bool']['filter'][] = array('query' => array('match' => array('advert_status' => 'active')));
            $esFeaturedQuery['query']['bool']['filter'][] = array('range' => array('advert_start_date' => array('lte' => $dateTimeEs)));
            $esFeaturedQuery['query']['bool']['filter'][] = array('range' => array('advert_end_date' => array('gte' => $dateTimeEs)));
            $esFeaturedQuery['query']['bool']['filter'][] = array('match' => array('advert_location_ids' => $locationId));
            $esFeaturedQuery['query']['bool']['filter'][] = array('match' => array('advert_type' => 'featured_list'));

            $esFeaturedQuery["sort"] = $sortByPageType;

            $esFeaturedParam = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.advert_news.type'),
                'body'  => json_encode($esFeaturedQuery)
            ];
            $featuredResponse = $client->search($esFeaturedParam);

            if ($featuredResponse['hits']['total'] > 0) {
                $slotKey = ! empty($mallId) ? 'featured_slot_mall' : 'featured_slot_gtm';
                $slot = array();

                $filterCity = [];
                if (empty($mallId)) {
                    if (! empty($countryData)) {
                        foreach ($cityFilters as $key => $value) {
                            $filterCity[] = $countryData->country_id . "_" . str_replace(" ", "_", trim(strtolower($value), " "));
                        }
                    }
                }

                $featuredlists = $featuredResponse['hits']['hits'];
                foreach ($featuredlists as $featured) {
                    $newsAdvertId = $featured['_source']['news_id'] . "|" . $featured['_id'];

                    if (! empty($featured['_source'][$slotKey])) { // grouping
                        if (! empty($mallId)) { // featured in mall page
                            if (! empty($featured['_source'][$slotKey][$mallId])) {
                                $slotNumber = (int) $featured['_source'][$slotKey][$mallId];
                                $slot[$slotNumber][] = $newsAdvertId;
                            }
                        } else { // featured in gtm page
                            // looping by city
                            $minimSlot = null;
                            $minimSlotNewsId = null;
                            foreach ($featured['_source'][$slotKey] as $city => $value) {
                                if (! empty($filterCIty)) { // if filter by city
                                    if (in_array($city, $filterCity)) {
                                        $slotNumber = (int) $featured['_source'][$slotKey][$city];
                                        if (empty($minimSlot) || $slotNumber <= $minimSlot) {
                                            $minimSlot = $slotNumber;
                                            $minimSlotNewsId = $newsAdvertId;
                                        }
                                    }
                                } else {
                                    // get minim slot (if news has 2 slot 1 and 3, we choose 1)
                                    if (! empty($countryData)) { // filter by country with all city
                                        if (strpos($city, $countryData->country_id) !== false) {
                                            $slotNumber = (int) $featured['_source'][$slotKey][$city];
                                            if (empty($minimSlot) || $slotNumber <= $minimSlot) {
                                                $minimSlot = $slotNumber;
                                                $minimSlotNewsId = $newsAdvertId;
                                            }
                                        }
                                    } else { // filter by all country (all locations)
                                        $slotNumber = (int) $featured['_source'][$slotKey][$city];
                                        if (empty($minimSlot) || $slotNumber <= $minimSlot) {
                                            $minimSlot = $slotNumber;
                                            $minimSlotNewsId = $newsAdvertId;
                                        }
                                    }
                                }
                            }

                            if (! empty($minimSlot)) {
                                $idCampaign = explode('|', $minimSlotNewsId);
                                $isFound = false;
                                for ($i = 1; $i <= 4; $i++) {
                                    if (! empty($slot[$i])) {
                                        foreach ($slot[$i] as $key => $value) {
                                            $idCampaignInSlot = explode('|', $value);
                                            if ($idCampaignInSlot[0] === $idCampaign[0]) {
                                                $isFound = true;
                                                if ($i > $minimSlot) {
                                                    unset($slot[$i][$key]);
                                                    $slot[$minimSlot][] = $minimSlotNewsId;
                                                }
                                            }
                                        }
                                    }
                                }

                                if (! $isFound) {
                                    $slot[$minimSlot][] = $minimSlotNewsId;
                                }
                            }
                        }
                    }
                }
            }

            // random per slot
            // slot 1
            $slotAdvertId = array();
            $slotNewsId = array();
            if (! empty($slot[1])) {
                $randKeys = array_rand($slot[1], 1);
                $ids = explode('|', $slot[1][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotNewsId[] = $ids[0];
            }
            // slot 2
            if (! empty($slot[2])) {
                $randKeys = array_rand($slot[2], 1);
                $ids = explode('|', $slot[2][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotNewsId[] = $ids[0];
            }
            // slot 3
            if (! empty($slot[3])) {
                $randKeys = array_rand($slot[3], 1);
                $ids = explode('|', $slot[3][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotNewsId[] = $ids[0];
            }
            // slot 4
            if (! empty($slot[4])) {
                $randKeys = array_rand($slot[4], 1);
                $ids = explode('|', $slot[4][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotNewsId[] = $ids[0];
            }

            $advertOnly = (count($slotNewsId) >= 4) ? TRUE : FALSE;

            // get preferred reguler and large and other featured
            $advertTypeQuery = ['featured_list', 'preferred_list_regular', 'preferred_list_large'];
            $esAdvertQuery = array('query' => array('bool' => array('filter' => array( array('query' => array('match' => array('advert_status' => 'active'))), array('range' => array('advert_start_date' => array('lte' => $dateTimeEs))), array('range' => array('advert_end_date' => array('gte' => $dateTimeEs))), array('match' => array('advert_location_ids' => $locationId)), array('terms' => array('advert_type' => $advertTypeQuery))))), 'sort' => $sortByPageType);

            if ($advertOnly) {
                $esAdvertQuery['query']['bool']['filter'][] = array('terms' => ['news_id' => $slotNewsId]);
            }

            // $jsonQuery['query']['bool']['filter'][] = array('bool' => array('should' => array($esAdvertQuery['query'], array('bool' => array('must_not' => array(array('exists' => array('field' => 'advert_status'))))))));

            $esAdvertParam = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.advert_news.type'),
                'body'  => json_encode($esAdvertQuery)
            ];

            $advertResponse = $client->search($esAdvertParam);

            if ($advertResponse['hits']['total'] > 0) {
                $advertList = $advertResponse['hits']['hits'];
                $excludeId = array();
                $withPreferred = array();
                $featuredId = array();

                foreach ($advertList as $adverts) {
                    $advertId = $adverts['_id'];
                    $newsId = $adverts['_source']['news_id'];
                    if ($adverts['_source']['advert_type'] === 'featured_list') {
                        if (! in_array($newsId, $slotNewsId)) {
                            $featuredId[] = $newsId;
                            $jsonQuery['query']['bool']['should'][] = array('match' => array('news_id' => array('query' => $newsId, 'boost' => 100)));
                        }
                    }

                    // if featured list_type check preferred too
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                        if (empty($withPreferred[$newsId]) || $withPreferred[$newsId] != 'preferred_list_large') {
                            $withPreferred[$newsId] = 'preferred_list_regular';
                            if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                $withPreferred[$newsId] = 'preferred_list_large';
                            }
                        }
                    }
                }

                $jsonQuery['query']['bool']['must_not'][] = array('terms' => ['_id' => $excludeId]);
            }

            $defaultSort = $sort = array('begin_date' => array('order' => 'desc'));
            $sortPageScript = "if (doc.containsKey('" . $pageTypeScore . "')) { if(! doc['" . $pageTypeScore . "'].empty) { return doc['" . $pageTypeScore . "'].value } else { return 0}} else {return 0}";
            $sortPage = array('_script' => array('script' => $sortPageScript, 'type' => 'string', 'order' => 'desc'));

            $sortby = array("_score", $sortPage, $defaultSort);
            $jsonQuery['sort'] = $sortby;
            $jsonQuery['size'] = 4;

            // boost slot
            $boost = [500, 400, 300, 200];
            $i = 0;
            foreach ($slotNewsId as $newsIdBoost) {
                $jsonQuery['query']['bool']['should'][] = array('match' => array('news_id' => array('query' => $newsIdBoost, 'boost' => $boost[$i])));
                $i += 1;
            }

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.news.index') . ',' . $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.news.type'),
                'body' => json_encode($jsonQuery)
            ];

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() use ($client, &$esParam) {
                    return $client->search($esParam);
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $client->search($esParam);
            }

            $records = $response['hits'];

            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($records['hits'] as $record) {
                $data = array();
                $default_lang = '';
                $partnerTokens = isset($record['_source']['partner_tokens']) ? $record['_source']['partner_tokens'] : [];
                $pageView = 0;
                $data['placement_type'] = null;
                $data['placement_type_orig'] = null;
                $isHavingReward = 'N';
                $avgGeneralRating = 0;
                $totalGeneralReviews = 0;
                $data['is_featured'] = false;
                foreach ($record['_source'] as $key => $value) {
                    $campaignId = $record['_source']['news_id'];

                    if ($key === 'is_having_reward') {
                        $isHavingReward = $value;
                    }

                    if ($key === "name") {
                        $key = "news_name";
                    }

                    $default_lang = (empty($record['_source']['default_lang']))? '' : $record['_source']['default_lang'];
                    $data[$key] = $value;

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        $data['image_url'] = '';

                        foreach ($record['_source']['translation'] as $dt) {
                            $localPath = (! empty($dt['image_url'])) ? $dt['image_url'] : '';
                            $cdnPath = (! empty($dt['image_cdn_url'])) ? $dt['image_cdn_url'] : '';

                            if ($dt['language_code'] === $language) {
                                // name
                                if (! empty($dt['name'])) {
                                    $data['news_name'] = $dt['name'];
                                }

                                // desc
                                if (! empty($dt['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url'])) {
                                    $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                }
                            } elseif ($dt['language_code'] === $default_lang) {
                                // name
                                if (! empty($dt['name']) && empty($data['news_name'])) {
                                    $data['news_name'] = $dt['name'];
                                }

                                // description
                                if (! empty($dt['description']) && empty($data['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (empty($data['image_url'])) {
                                    $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                }
                            }
                        }
                    }

                    // advert type
                    if ($list_type === 'featured') {
                        if (in_array($campaignId, $slotNewsId) || in_array($campaignId, $featuredId)) {
                            $data['is_featured'] = true;
                        }

                        if (! empty($withPreferred[$campaignId])) {
                            $data['placement_type'] = $withPreferred[$campaignId];
                            $data['placement_type_orig'] = $withPreferred[$campaignId];
                        }
                    }

                    if ($key === "is_exclusive") {
                        $data[$key] = ! empty($data[$key]) ? $data[$key] : 'N';
                        // disable is_exclusive if token is sent and in the partner_tokens
                        if ($data[$key] === 'Y' && in_array($partnerToken, $partnerTokens)) {
                            $data[$key] = 'N';
                        }
                    }

                    if (empty($mallId)) {
                        if ($key === 'gtm_page_views') {
                            $pageView = $value;
                        }
                    } else {
                        if (isset($record['_source']['mall_page_views'])) {
                            foreach ($record['_source']['mall_page_views'] as $dt) {
                                if ($dt['location_id'] === $mallId) {
                                    $pageView = $dt['total_views'];
                                }
                            }
                        }
                    }

                    if ($key === 'avg_general_rating') {
                        $avgGeneralRating = $value;
                    }

                    if ($key === 'total_general_reviews') {
                        $totalGeneralReviews = $value;
                    }
                }

                $data['average_rating'] = (! empty($record['fields']['average_rating'][0])) ? number_format(round($record['fields']['average_rating'][0], 1), 1) : 0;
                $data['total_review'] = (! empty($record['fields']['total_review'][0])) ? round($record['fields']['total_review'][0], 1) : 0;
                if ($isHavingReward === 'Y') {
                    $data['average_rating'] = ($avgGeneralRating != 0) ? number_format(round($avgGeneralRating, 1), 1) : 0;
                    $data['total_review'] = round($totalGeneralReviews, 1);
                }

                $data['page_view'] = $pageView;
                $data['score'] = $record['_score'];
                unset($data['created_by'], $data['creator_email'], $data['partner_tokens']);
                $listOfRec[] = $data;
            }

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $records['total'];
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
                $data->mall_city = $mall->city;
            }
            $data->records = $listOfRec;

            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall event list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_event_list')
                            ->setActivityNameLong('View mall event list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('News')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: News list');
                        $activity->setUser($user)
                            ->setActivityName('view_news_main_page')
                            ->setActivityNameLong('View News Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('News')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
                            ->responseOK()
                            ->save();
                    }
                }
            }

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}