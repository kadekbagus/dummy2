<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Coupon;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Mall;
use Advert;
use Lang;
use Role;
use IssuedCoupon;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Helper\EloquentRecordCounter as RecordCounter;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Elasticsearch\ClientBuilder;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Country;
use Redis;
use BaseStore;

class CouponFeaturedListAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;

    /**
     * GET - get all coupon in all mall
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponFeaturedList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $mall = NULL;
        $user = NULL;
        $httpCode = 200;
        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'featured-coupon-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');
        $advertCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-adverts');

        try {
            $this->checkAuth();
            $user = $this->api->user;
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'created_date');
            $sort_mode = OrbitInput::get('sortmode','desc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', []);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('coupon');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $partnerToken = OrbitInput::get('token', null);
            $viewType = OrbitInput::get('view_type', 'grid');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                    'list_type'   => $list_type,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date,updated_date,rating',
                    'list_type'   => 'in:featured,preferred',
                ),
                array(
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

            $valid_language = $couponHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;

            $jsonQuery = array('fields' => array("_source"), 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('status' => 'active'))), array('range' => array('available' => array('gt' => 0))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

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
            // OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$withKeywordSearch, &$cacheKey, &$filterKeyword)
            // {
            //     $cacheKey['keyword'] = $keyword;
            //     if ($keyword != '') {
            //         $searchFlag = $searchFlag || TRUE;
            //         $withKeywordSearch = true;
            //         $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.coupon.keyword', '');

            //         $priority['name'] = Config::get('orbit.elasticsearch.priority.coupons.name', '^6');
            //         $priority['object_type'] = Config::get('orbit.elasticsearch.priority.coupons.object_type', '^5');
            //         $priority['keywords'] = Config::get('orbit.elasticsearch.priority.coupons.keywords', '^4');
            //         $priority['description'] = Config::get('orbit.elasticsearch.priority.coupons.description', '^3');
            //         $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.coupons.mall_name', '^3');
            //         $priority['city'] = Config::get('orbit.elasticsearch.priority.coupons.city', '^2');
            //         $priority['province'] = Config::get('orbit.elasticsearch.priority.coupons.province', '^2');
            //         $priority['country'] = Config::get('orbit.elasticsearch.priority.coupons.country', '^2');

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

            OrbitInput::get('store_id', function($storeId) use (&$jsonQuery) {
                if (! empty($storeId)) {
                    $withStoreId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.merchant_id' => $storeId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withStoreId;
                }
            });

            OrbitInput::get('sponsor_provider_ids', function($sponsorProviderIds) use (&$jsonQuery) {
                if (! empty($sponsorProviderIds) && is_array($sponsorProviderIds)) {
                    $withSponsorProviderIds = array('nested' => array('path' => 'sponsor_provider', 'query' => array('filtered' => array('filter' => array('terms' => array('sponsor_provider.sponsor_id' => $sponsorProviderIds))))));
                    $jsonQuery['query']['bool']['filter'][] = $withSponsorProviderIds;
                }
             });

            OrbitInput::get('brand_id', function($brandId) use (&$jsonQuery) {
                if (! empty($brandId)) {
                    $baseStore = BaseStore::select('base_merchant_id')->where('base_store_id', '=', $brandId)->first();
                    if ($baseStore) {
                        $tenantIds = [];
                        $stores = BaseStore::select('base_store_id')->where('base_merchant_id', '=', $baseStore->base_merchant_id)->get();
                        if (count($stores)) {
                            foreach($stores as $key=>$value) {
                                $tenantIds[] = $value->base_store_id;
                            }
                        }
                        $withBrandId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('terms' => array('link_to_tenant.merchant_id' => $tenantIds))))));
                        $jsonQuery['query']['bool']['filter'][] = $withBrandId;
                    }
                }
             });

            // filter by category_id
            // OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
            //     $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.coupon.category', '');
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

            // OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery, $prefix, &$searchFlag, &$cacheKey) {
            //     $cacheKey['partner_id'] = $partnerId;
            //     $partnerFilter = '';
            //     if (! empty($partnerId)) {
            //         $searchFlag = $searchFlag || TRUE;
            //         $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
            //                                                     $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
            //                                                          ->where('affected_group_names.group_type', '=', 'coupon');
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
                    $cityFilterArr = [];
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.coupon.city', '');
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
            } else if (! empty($countryData) && ! empty($cityFilters)) {
                $countryId = $countryData->country_id;
                foreach ((array) $cityFilters as $cityFilter) {
                    $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
                    $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
                }
            } else if (! empty($countryData) && ! empty($countryFilter)) {
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
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_coupons.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.advert_coupons.type'),
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
                    $couponAdvertId = $featured['_source']['promotion_id'] . "|" . $featured['_id'];

                    if (! empty($featured['_source'][$slotKey])) { // grouping
                        if (! empty($mallId)) { // featured in mall page
                            if (! empty($featured['_source'][$slotKey][$mallId])) {
                                $slotNumber = (int) $featured['_source'][$slotKey][$mallId];
                                $slot[$slotNumber][] = $couponAdvertId;
                            }
                        } else { // featured in gtm page
                            // looping by city
                            $minimSlot = null;
                            $minimSlotCouponId = null;
                            foreach ($featured['_source'][$slotKey] as $city => $value) {
                                if (! empty($filterCity)) { // if filter by city
                                    if (in_array($city, $filterCity)) {
                                        $slotNumber = (int) $featured['_source'][$slotKey][$city];
                                        if (empty($minimSlot) || $slotNumber <= $minimSlot) {
                                            $minimSlot = $slotNumber;
                                            $minimSlotCouponId = $couponAdvertId;
                                        }
                                    }
                                } else {
                                    // get minim slot (if coupon has 2 slot 1 and 3, we choose 1)
                                    if (! empty($countryData)) { // filter by country with all city
                                        if (strpos($city, $countryData->country_id) !== false) {
                                            $slotNumber = (int) $featured['_source'][$slotKey][$city];
                                            if (empty($minimSlot) || $slotNumber <= $minimSlot) {
                                                $minimSlot = $slotNumber;
                                                $minimSlotCouponId = $couponAdvertId;
                                            }
                                        }
                                    } else { // filter by all country (all locations)
                                        $slotNumber = (int) $featured['_source'][$slotKey][$city];
                                        if (empty($minimSlot) || $slotNumber <= $minimSlot) {
                                            $minimSlot = $slotNumber;
                                            $minimSlotCouponId = $couponAdvertId;
                                        }
                                    }
                                }
                            }

                            if (! empty($minimSlot)) {
                                $idCampaign = explode('|', $minimSlotCouponId);
                                $isFound = false;
                                for ($i = 1; $i <= 4; $i++) {
                                    if (! empty($slot[$i])) {
                                        foreach ($slot[$i] as $key => $value) {
                                            $idCampaignInSlot = explode('|', $value);
                                            if ($idCampaignInSlot[0] === $idCampaign[0]) {
                                                $isFound = true;
                                                if ($i > $minimSlot) {
                                                    unset($slot[$i][$key]);
                                                    $slot[$minimSlot][] = $minimSlotCouponId;
                                                }
                                            }
                                        }
                                    }
                                }

                                if (! $isFound) {
                                    $slot[$minimSlot][] = $minimSlotCouponId;
                                }
                            }
                        }
                    }
                }
            }

            // random per slot
            // slot 1
            $slotAdvertId = array();
            $slotCouponId = array();
            if (! empty($slot[1])) {
                $randKeys = array_rand($slot[1], 1);
                $ids = explode('|', $slot[1][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotCouponId[] = $ids[0];
            }
            // slot 2
            if (! empty($slot[2])) {
                $randKeys = array_rand($slot[2], 1);
                $ids = explode('|', $slot[2][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotCouponId[] = $ids[0];
            }
            // slot 3
            if (! empty($slot[3])) {
                $randKeys = array_rand($slot[3], 1);
                $ids = explode('|', $slot[3][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotCouponId[] = $ids[0];
            }
            // slot 4
            if (! empty($slot[4])) {
                $randKeys = array_rand($slot[4], 1);
                $ids = explode('|', $slot[4][$randKeys]);
                $slotAdvertId[] = $ids[1];
                $slotCouponId[] = $ids[0];
            }

            $advertOnly = (count($slotCouponId) >= 4) ? TRUE : FALSE;

            // get prefered reguler and large and other featured
            $advertTypeQuery = ['featured_list', 'preferred_list_regular', 'preferred_list_large'];
            $esAdvertQuery = array('query' => array('bool' => array('filter' => array( array('query' => array('match' => array('advert_status' => 'active'))), array('range' => array('advert_start_date' => array('lte' => $dateTimeEs))), array('range' => array('advert_end_date' => array('gte' => $dateTimeEs))), array('match' => array('advert_location_ids' => $locationId)), array('terms' => array('advert_type' => $advertTypeQuery))))), 'sort' => $sortByPageType);

            if ($advertOnly) {
                $esAdvertQuery['query']['bool']['filter'][] = array('terms' => ['promotion_id' => $slotCouponId]);
            }

            // $jsonQuery['query']['bool']['filter'][] = array('bool' => array('should' => array($esAdvertQuery['query'], array('bool' => array('must_not' => array(array('exists' => array('field' => 'advert_status'))))))));

            $esAdvertParam = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_coupons.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.advert_coupons.type'),
                'body'  => json_encode($esAdvertQuery)
            ];

            $featuredId = array();
            $advertResponse = $client->search($esAdvertParam);
            if ($advertResponse['hits']['total'] > 0) {
                $advertList = $advertResponse['hits']['hits'];
                $excludeId = array();
                $withPreferred = array();

                foreach ($advertList as $adverts) {
                    $advertId = $adverts['_id'];
                    $couponId = $adverts['_source']['promotion_id'];

                    if ($adverts['_source']['advert_type'] === 'featured_list') {
                        if (! in_array($couponId, $slotCouponId)) {
                            if (! in_array($couponId, $featuredId)) {
                                $featuredId[] = $couponId;
                                $jsonQuery['query']['bool']['should'][] = array('match' => array('promotion_id' => array('query' => $couponId, 'boost' => 100)));
                            }
                        }
                    }

                    // if featured list_type check preferred too
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                        if (empty($withPreferred[$couponId]) || $withPreferred[$couponId] != 'preferred_list_large') {
                            $withPreferred[$couponId] = 'preferred_list_regular';
                            if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                $withPreferred[$couponId] = 'preferred_list_large';
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
            $jsonQuery['size'] = $take;

            // boost slot
            $boost = [500, 400, 300, 200];
            $i = 0;
            foreach ($slotCouponId as $couponIdBoost) {
                $jsonQuery['query']['bool']['should'][] = array('match' => array('promotion_id' => array('query' => $couponIdBoost, 'boost' => $boost[$i])));
                $i += 1;
            }

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.coupons.type'),
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

            $promotionIds = array();
            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($records['hits'] as $record) {
                $data = array();
                $isOwned = false;
                $default_lang = '';
                $partnerTokens = isset($record['_source']['partner_tokens']) ? $record['_source']['partner_tokens'] : [];
                $pageView = 0;
                $data['placement_type'] = null;
                $data['placement_type_orig'] = null;
                $data['is_featured'] = false;
                $campaignId = '';
                foreach ($record['_source'] as $key => $value) {
                    $campaignId = $record['_source']['promotion_id'];

                    if ($key === "name") {
                        $key = "coupon_name";
                    } elseif ($key === "promotion_id") {
                        $key = "coupon_id";
                        $promotionIds[] = $value;
                        $campaignId = $value;
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
                                    $data['coupon_name'] = $dt['name'];
                                }

                                // desc
                                if (! empty($dt['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if ($record['_source']['promotion_type'] == 'sepulsa') {
                                    $data['image_url'] = $localPath;
                                } else {
                                    if (! empty($dt['image_url'])) {
                                        $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                    }
                                }
                            } elseif ($dt['language_code'] === $default_lang) {
                                // name
                                if (! empty($dt['name']) && empty($data['coupon_name'])) {
                                    $data['coupon_name'] = $dt['name'];
                                }

                                // description
                                if (! empty($dt['description']) && empty($data['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if ($record['_source']['promotion_type'] == 'sepulsa') {
                                    $data['image_url'] = $localPath;
                                } else {
                                    if (empty($data['image_url'])) {
                                        $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                    }
                                }
                            }
                        }
                    }

                    // Calculation percentage discount for sepulsa and hot delas
                    $data['price_discount'] = '0';
                    if ($record['_source']['promotion_type'] != 'mall') {
                        $priceOld = $record['_source']['price_old'];
                        $priceNew = $record['_source']['price_selling'];

                        if ($priceOld != '0' && $priceNew != '0') {
                            $data['price_discount'] = round((($priceOld - $priceNew) / $priceOld) * 100, 1, PHP_ROUND_HALF_DOWN);
                        }
                    }

                    // advert
                    if ($list_type === 'featured') {
                        if (in_array($campaignId, $slotCouponId) || in_array($campaignId, $featuredId)) {
                            $data['is_featured'] = true;
                            $data['placement_type'] = 'featured_list';
                            $data['placement_type_orig'] = 'featured_list';
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

                    if ($key === "wallet_operator") {
                        $data['wallet_operator'] = null;
                        if (! empty($record['_source']['wallet_operator'])) {
                            foreach ($record['_source']['wallet_operator'] as $dt) {
                                $logoLocalPath = (! empty($dt['operator_logo'])) ? $dt['operator_logo'] : '';
                                $logoCdnPath = (! empty($dt['operator_logo_cdn'])) ? $dt['operator_logo_cdn'] : '';
                                $data['wallet_operator'][] = array('operator_name' => $dt['operator_name'], 'operator_logo_url' => $imgUrl->getImageUrl($logoLocalPath, $logoCdnPath));
                            }

                        }
                    }

                    // !--------- Disable page view --------!
                    // if (empty($mallId)) {
                    //     if ($key === 'gtm_page_views') {
                    //         $pageView = $value;
                    //     }
                    // } else {
                    //     if (isset($record['_source']['mall_page_views'])) {
                    //         foreach ($record['_source']['mall_page_views'] as $dt) {
                    //             if ($dt['location_id'] === $mallId) {
                    //                 $pageView = $dt['total_views'];
                    //             }
                    //         }
                    //     }
                    // }
                }

                $data['average_rating'] = (! empty($record['fields']['average_rating'][0])) ? number_format(round($record['fields']['average_rating'][0], 1), 1) : 0;
                $data['total_review'] = (! empty($record['fields']['total_review'][0])) ? round($record['fields']['total_review'][0], 1) : 0;

                // if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                //     $redisKey = 'coupon' . '||' . $campaignId . '||' . $locationId;
                //     $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                //     $redis = Redis::connection($redisConnection);
                //     $pageView = (! empty($redis->get($redisKey))) ? $redis->get($redisKey) : $pageView;
                // }
                // $data['page_view'] = $pageView;
                $data['page_view'] = 0;
                $data['owned'] = $isOwned;
                $data['score'] = $record['_score'];
                unset($data['created_by'], $data['creator_email'], $data['partner_tokens']);
                $listOfRec[] = $data;
            }

            if ($user->isConsumer() && ! empty($promotionIds)) {
                $myCoupons = IssuedCoupon::select('promotion_id')
                                ->where('issued_coupons.user_id', '=', $user->user_id)
                                ->where('issued_coupons.status', '=', 'issued')
                                ->whereIn('promotion_id', $promotionIds)
                                ->orderBy('created_at', 'desc')
                                ->groupBy('promotion_id')
                                ->get()
                                ->lists('promotion_id');

                foreach ($listOfRec as &$couponData) {
                    if (in_array($couponData['coupon_id'], $myCoupons)) {
                        $couponData['owned'] = true;
                    }
                }
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

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall coupon list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_coupon_list')
                            ->setActivityNameLong('View mall coupon list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Coupon')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Coupon list');
                        $activity->setUser($user)
                            ->setActivityName('view_coupons_main_page')
                            ->setActivityNameLong('View Coupons Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Coupon')
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

        $output = $this->render($httpCode);

        return $output;
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
