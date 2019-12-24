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
use Redis;
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
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;
use UserSponsor;

class NewsListAPIController extends PubControllerAPI
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
    public function getSearchNews()
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
        $cacheContext = 'event-list';
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
            $cityFilters = OrbitInput::get('cities', null);
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
            $myCCFilter = OrbitInput::get('my_cc_filter', false);

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
                'my_cc_filter' => $myCCFilter
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

            $jsonQuery = array('from' => $skip, 'size' => $esTake, 'fields' => array("_source"), 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('status' => 'active'))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

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
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$cacheKey, &$filterKeyword, &$withKeywordSearch)
            {
                $cacheKey['keyword'] = $keyword;

                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withKeywordSearch = true;
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.keyword', '');

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.news.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.news.object_type', '^5');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.news.keywords', '^4');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.news.description', '^3');
                    $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.news.mall_name', '^3');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.news.city', '^2');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.news.province', '^2');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.news.country', '^2');

                    $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.name'.$priority['name'], 'translation.description'.$priority['description'])))));

                    $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'link_to_tenant', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('link_to_tenant.city'.$priority['city'], 'link_to_tenant.province'.$priority['province'], 'link_to_tenant.country'.$priority['country'], 'link_to_tenant.mall_name'.$priority['mall_name'])))));

                    $filterKeyword['bool']['should'][] = array('multi_match' => array('query' => $keyword, 'fields' => array('object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));

                    if ($shouldMatch != '') {
                        $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
                    }

                    $jsonQuery['query']['bool']['filter'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.parent_id' => $mallId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withMallId;
                }
             });

            // Filter by my credit card or choose manually
            if ($myCCFilter) {
                $role = $user->role->role_name;
                if (strtolower($role) === 'consumer') {
                    $userId = $user->user_id;
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
                        $cacheKey['sponsor_provider_ids'] = $sponsorProviderIds;
                        $withSponsorProviderIds = array('nested' => array('path' => 'sponsor_provider', 'query' => array('filtered' => array('filter' => array('terms' => array('sponsor_provider.sponsor_id' => $sponsorProviderIds))))));
                        $jsonQuery['query']['bool']['filter'][] = $withSponsorProviderIds;
                    }
                }
            } else {
                OrbitInput::get('sponsor_provider_ids', function($sponsorProviderIds) use (&$jsonQuery, &$cacheKey) {
                    $cacheKey['sponsor_provider_ids'] = $sponsorProviderIds;
                    if (! empty($sponsorProviderIds) && is_array($sponsorProviderIds)) {
                        // re index key array, have issue ES when sent key [1]
                        $sponsorProviderIds = array_values($sponsorProviderIds);
                        $withSponsorProviderIds = array('nested' => array('path' => 'sponsor_provider', 'query' => array('filtered' => array('filter' => array('terms' => array('sponsor_provider.sponsor_id' => $sponsorProviderIds))))));
                        $jsonQuery['query']['bool']['filter'][] = $withSponsorProviderIds;
                    }
                 });
            }

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.category', '');
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['bool']['should'][] = array('match' => array('category_ids' => $value));
                }

                if ($shouldMatch != '') {
                    $categoryFilter['bool']['minimum_should_match'] = $shouldMatch;
                }
                $jsonQuery['query']['bool']['filter'][] = $categoryFilter;
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery, $prefix, &$searchFlag, &$cacheKey) {
                $cacheKey['partner_id'] = $partnerId;

                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $searchFlag = $searchFlag || TRUE;
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'news');
                                                            })
                                                            ->where('partner_id', $partnerId)
                                                            ->first();

                    if (is_object($partnerAffected)) {
                        $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);
                        $partnerFilter = array('query' => array('match' => array('partner_ids' => $partnerId)));

                        if (in_array($partnerId, $exception)) {
                            $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');
                            $partnerFilter = array('query' => array('not' => array('terms' => array('partner_ids' => $partnerIds))));
                        }
                        $jsonQuery['query']['bool']['filter'][] = $partnerFilter;
                    }
                }
            });

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

            // sort by name or location
            $defaultSort = $sort = array('begin_date' => array('order' => 'desc'));
            if ($sort_by === 'location' && $lat != '' && $lon != '') {
                $searchFlag = $searchFlag || TRUE;
                $withCache = FALSE;
                $sort = array('_geo_distance' => array('nested_path' => 'link_to_tenant', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat), 'order' => $sort_mode, 'unit' => 'km', 'distance_type' => 'plane'));
            } elseif ($sort_by === 'created_date') {
                $sort = array('begin_date' => array('order' => $sort_mode));
            } elseif ($sort_by === 'updated_date') {
                $sort = array('updated_at' => array('order' => $sort_mode));
            } elseif ($sort_by === 'rating') {
                $sort = array('_script' => array('script' => $scriptFieldRating, 'type' => 'number', 'order' => $sort_mode));
            } else {
                $sortScript =  "if(doc['name_" . $language . "'].value != null) { return doc['name_" . $language . "'].value } else { doc['name_default'].value }";
                $sort = array('_script' => array('script' => $sortScript, 'type' => 'string', 'order' => $sort_mode));
            }

            $sortByPageType = array();
            $pageTypeScore = '';
            if ($list_type === 'featured') {
                $pageTypeScore = 'featured_gtm_score';
                $sortByPageType = array('featured_gtm_score' => array('order' => 'desc'));
                if (! empty($mallId)) {
                    $pageTypeScore = 'featured_mall_score';
                    $sortByPageType = array('featured_mall_score' => array('order' => 'desc'));
                }
            } else {
                $pageTypeScore = 'preferred_gtm_score';
                $sortByPageType = array('preferred_gtm_score' => array('order' => 'desc'));
                if (! empty($mallId)) {
                    $pageTypeScore = 'preferred_mall_score';
                    $sortByPageType = array('preferred_mall_score' => array('order' => 'desc'));
                }
            }

            $sortPageScript = "if (doc.containsKey('" . $pageTypeScore . "')) { if(! doc['" . $pageTypeScore . "'].empty) { return doc['" . $pageTypeScore . "'].value } else { return 0}} else {return 0}";
            $sortPage = array('_script' => array('script' => $sortPageScript, 'type' => 'string', 'order' => 'desc'));

            $sortby = array($sortPage, $sort, $defaultSort);
            if ($withScore) {
                $sortby = array($sortPage, "_score", $sort, $defaultSort);
            }
            $jsonQuery["sort"] = $sortby;

            // Exclude specific document Ids, useful for some cases e.g You May Also Like
            // @todo rewrite deprected 'filtered' query to bool only
            $withAdvert = TRUE;
            OrbitInput::get('excluded_ids', function($excludedIds) use (&$jsonQuery, &$withAdvert) {
                $jsonExcludedIds = [];
                foreach ($excludedIds as $excludedId) {
                    $jsonExcludedIds[] = array('term' => ['_id' => $excludedId]);
                }
                $jsonQuery['query']['bool']['must_not'] = $jsonExcludedIds;

                $withAdvert = FALSE;
            });

            if ($this->withoutScore) {
                // remove _score sort
                $found = FALSE;
                $sortby = array_filter($sortby, function($val) use(&$found) {
                        if ($val === '_score') {
                            $found = $found || TRUE;
                        }
                        return $val !== '_score';
                    });

                if($found) {
                    // redindex array if _score is eliminated
                    $sortby = array_values($sortby);
                }
            }
            $jsonQuery['sort'] = $sortby;

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $esIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.news.index');
            $locationId = ! empty($mallId) ? $mallId : 0;

			if ($withAdvert) {
		        $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_regular', 'preferred_list_large'] : ['preferred_list_regular', 'preferred_list_large'];

		        // call advert before call main query
		        $esAdvertQuery = array('query' => array('bool' => array('must' => array( array('query' => array('match' => array('advert_status' => 'active'))), array('range' => array('advert_start_date' => array('lte' => $dateTimeEs))), array('range' => array('advert_end_date' => array('gte' => $dateTimeEs))), array('match' => array('advert_location_ids' => $locationId)), array('terms' => array('advert_type' => $advertType))))), 'sort' => $sortByPageType);

		        $jsonQuery['query']['bool']['filter'][] = array('bool' => array('should' => array($esAdvertQuery['query'], array('bool' => array('must_not' => array(array('exists' => array('field' => 'advert_status'))))))));

		        $esAdvertParam = [
		            'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
		            'type'  => Config::get('orbit.elasticsearch.indices.advert_news.type'),
		            'body'  => json_encode($esAdvertQuery)
		        ];

		        $advertResponse = $client->search($esAdvertParam);
		        if ($advertResponse['hits']['total'] > 0) {
		            $esIndex = $esIndex . ',' . $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index');
		            $advertList = $advertResponse['hits']['hits'];
		            $excludeId = array();
		            $withPreferred = array();

		            foreach ($advertList as $adverts) {
		                $advertId = $adverts['_id'];
		                $newsId = $adverts['_source']['news_id'];
		                if(! in_array($newsId, $excludeId)) {
		                    $excludeId[] = $newsId;
		                } else {
		                    $excludeId[] = $advertId;
		                }

		                // if featured list_type check preferred too
		                if ($list_type === 'featured') {
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

		            $jsonQuery['query']['bool']['must_not'][] = array('terms' => ['_id' => $excludeId]);
		        }
			}

            $esParam = [
                'index'  => $esIndex,
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
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

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
                $campaignId = '';
                foreach ($record['_source'] as $key => $value) {
                    if ($key === 'news_id') {
                        $campaignId = $value;
                    }

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
                        if (! empty($mallId) && $key === 'featured_mall_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        } elseif ($key === 'featured_gtm_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        }

                        if (! empty($withPreferred[$campaignId])) {
                            $data['placement_type'] = $withPreferred[$campaignId];
                            $data['placement_type_orig'] = $withPreferred[$campaignId];
                        }
                    } elseif ($list_type === 'preferred') {
                        if (! empty($mallId) && $key === 'preferred_mall_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        } elseif ($key === 'preferred_gtm_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
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

                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $redisKey = 'news' . '||' . $campaignId . '||' . $locationId;
                    $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                    $redis = Redis::connection($redisConnection);
                    $pageView = (! empty($redis->get($redisKey))) ? $redis->get($redisKey) : $pageView;
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

            // random featured adv
            // @todo fix for random -- this is not the right way to do random, it could lead to memory leak
            if ($list_type === 'featured') {
                $advertedCampaigns = array_filter($listOfRec, function($v) {
                    return ($v['placement_type_orig'] === 'featured_list');
                });

                if (count($advertedCampaigns) > $take) {
                    $output = array();
                    $listSlide = array_rand($advertedCampaigns, $take);
                    if (count($listSlide) > 1) {
                        foreach ($listSlide as $key => $value) {
                            $output[] = $advertedCampaigns[$value];
                        }
                    } else {
                        $output = $advertedCampaigns[$listSlide];
                    }
                } else {
                    $output = array_slice($listOfRec, 0, $take);
                }

                $data->returned_records = count($output);
                $data->total_records = $records['total'];
                $data->records = $output;
            }


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
