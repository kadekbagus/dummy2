<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
 */

use App;
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
use Coupon;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;
use UserSponsor;
use UserDetail;
use ArticleSearch;
use BrandProduct;
use Orbit\Controller\API\v1\Pub\BrandProduct\Request\ListRequest;
use Orbit\Controller\API\v1\Pub\Product\Request\ListRequest as ProductAffiliationListRequest;
use Product;
use BaseMerchant;
use Tenant;

class MenuCounterAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - Menu counter in homepage
     *
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string country
     * @param string cities
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMenuCounter(
        BrandProduct $brandProduct,
        ListRequest $request
    ) {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $mall = null;

        try {
            $this->checkAuth();
            $user = $this->api->user;
            $userId = $user->user_id;
            $roleName = $user->role->role_name;
            $host = Config::get('orbit.elasticsearch');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $keyword = OrbitInput::get('keyword', null);
            $myCCFilter = OrbitInput::get('my_cc_filter', false);
            $articleCategories = OrbitInput::get('category_id', []);
            $articleObjectType = OrbitInput::get('object_type', null);
            $articleObjectId = OrbitInput::get('object_id', null);
            $ratingLow = OrbitInput::get('rating_low', 0);
            $ratingHigh = OrbitInput::get('rating_high', 5);
            $ratingLow = empty($ratingLow) ? 0 : $ratingLow;
            $ratingHigh = empty($ratingHigh) ? 5 : $ratingHigh;
            $bankBaseMerchantId = OrbitInput::get('bank_base_merchant_id', null);

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

            $campaignJsonQuery = array('from' => 0, 'size' => 1, 'aggs' => array('campaign_index' => array('terms' => array('field' => '_index'))), 'query' => array('bool' => array('must' => array(  array('match' => array('status' => 'active')), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

            $couponJsonQuery = array('from' => 0, 'size' => 1, 'aggs' => array('campaign_index' => array('terms' => array('field' => '_index'))), 'query' => array('bool' => array('must' => array( array('match' => array('status' => 'active')), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

            $mallJsonQuery = array('from' => 0, 'size' => 1, 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('is_subscribed' => 'Y'))), array('query' => array('match' => array('status' => 'active')))))));

            $merchantJsonQuery = array('from' => 0, 'size' => 1);
            $storeJsonQuery = $merchantJsonQuery;

            $articleSearcher = new ArticleSearch();
            $articleSearcher->setPaginationParams(['from' => 0, 'size' => 1]);
            $articleSearcher->isActive(compact('dateTimeEs'));

            // get user lat and lon
            if ($location == 'mylocation') {
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

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$campaignJsonQuery, &$couponJsonQuery, &$mallJsonQuery, $lat, $lon, $distance)
            {
                if (! empty($location)) {

                    if ($location === 'mylocation' && $lat != '' && $lon != '') {
                        $withCache = FALSE;

                        // campaign
                        $campaignLocationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat)))))));
                        $campaignJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                        $couponJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;

                        // mall
                        $mallLocationFilter = array('geo_distance' => array('distance' => $radius.'km', 'position' => array('lon' => $lon, 'lat' => $lat)));
                        $mallJsonQuery['query']['bool']['filter'][] = $mallLocationFilter;
                    } elseif ($location !== 'mylocation') {

                        // campaign
                        $campaignLocationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.city.raw' => $location))))));
                        $campaignJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                        $couponJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;

                        // mall
                        $mallLocationFilter = array('match' => array('city' => array('query' => $location, 'operator' => 'and')));
                        $mallJsonQuery['query']['bool']['filter'][] = $mallLocationFilter;
                    }
                }
            });

            $campaignCountryCityFilterArr = [];
            $merchantCountryCityFilterArr = [];
            $storeCountryCityFilterArr = [];
            $mallFilterCampaign = [];
            $mallFilterStore = [];
            $keywordFilter = [];
            $keywordFilterShould = [];
            $campaignCountryFilter = [];
            $campaignCityFilter = [];
            $keywordMallFilter = [];
            $keywordMallFilterShould = [];
            $categoryCampaignFilter = [];
            $categoryStoreFilter = [];
            $sponsorFilter = [];
            $storeCountryFilter = [];
            $storeCityFilter = [];
            $partnerFilterMustNot = [];
            $partnerFilterMust = [];
            $countryData = null;
            $genderFilter = [];
            $genderFilterStore = [];
            $articleLinkedObjectFilter = [];
            $searchLinkedObjects = false;
            $searchCategories = false;
            $searchKeyword = false;

            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$campaignJsonQuery, &$mallJsonQuery, &$campaignCountryCityFilterArr, &$countryData, &$merchantCountryCityFilterArr, &$storeCountryCityFilterArr, &$campaignCountryFilter, &$storeCountryFilter, &$articleSearcher) {
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();

                // campaign
                $campaignCountryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];
                $campaignCountryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];

                // mall
                $mallCountryFilterArr = array('match' => array('country.raw' => array('query' => $countryFilter)));;
                $mallJsonQuery['query']['bool']['filter'][] = $mallCountryFilterArr;

                // merchant & store
                $merchantCountryCityFilterArr = ['nested' => ['path' => 'tenant_detail', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];
                $merchantCountryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['tenant_detail.country.raw' => $countryFilter]]];

                $storeCountryCityFilterArr['bool'] = ['must' => ['match' => ['country.raw' => $countryFilter]]];

                $campaignCountryFilter = ['nested' => [
                                            'path' => 'link_to_tenant',
                                            'query' => [
                                                'match' => [
                                                    'link_to_tenant.country.raw' => $countryFilter
                                                ]
                                            ],
                                            'inner_hits' => [
                                                'name' => 'country_city_hits'
                                            ],
                                        ]];

                $storeCountryFilter = ['nested' => [
                                            'path' => 'tenant_detail',
                                            'query' => [
                                                'match' => [
                                                    'tenant_detail.country.raw' => $countryFilter
                                                ]
                                            ],
                                            'inner_hits' => [
                                                'name' => 'country_city_hits'
                                            ],
                                        ]];

                $articleSearcher->filterByCountry($countryFilter);
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$campaignJsonQuery, &$mallJsonQuery, $countryFilter, &$campaignCountryCityFilterArr, &$merchantCountryCityFilterArr, &$storeCountryCityFilterArr, &$campaignCityFilter, &$storeCityFilter, &$articleSearcher) {
                if (! empty($countryFilter)) {
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.city', '');
                    $campaignCityFilterArr = [];
                    $mallCityFilterArr = [];
                    $merchantCityFilterArr = [];
                    $storeCityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $campaignCityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
                        $mallCityFilterArr['bool']['should'][] = array('match' => array('city.raw' => array('query' => $cityFilter)));
                        $merchantCityFilterArr[] = ['match' => ['tenant_detail.city.raw' => $cityFilter]];
                        $storeCityFilterArr[] = ['match' => ['city.raw' => $cityFilter]];
                    }

                    if ($shouldMatch != '') {
                        if (count((array) $cityFilters) === 1) {
                            // if user just filter with one city, value of should match must be 100%
                            $shouldMatch = '100%';
                        }
                        $campaignCountryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                        $mallCityFilterArr['bool']['minimum_should_match'] = $shouldMatch;
                        $merchantCountryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                        $storeCountryCityFilterArr['bool']['minimum_should_match'] = $shouldMatch;
                    }

                    $campaignCountryCityFilterArr['nested']['query']['bool']['should'] = $campaignCityFilterArr;
                    $mallJsonQuery['query']['bool']['filter'][] = $mallCityFilterArr;
                    $merchantCountryCityFilterArr['nested']['query']['bool']['should'] = $merchantCityFilterArr;
                    $storeCountryCityFilterArr['bool']['should'] = $storeCityFilterArr;
                }

                $campaignCityFilter['bool']['should'] = [];
                $storeCityFilter['bool']['should'] = [];

                foreach((array) $cityFilters as $city) {
                    $campaignCityFilter['bool']['should'][] = [
                        'nested' => [
                            'path' => 'link_to_tenant',
                            'query' => [
                                'match' => [
                                    'link_to_tenant.city.raw' => $city
                                ]
                            ]
                        ]
                    ];

                    $storeCityFilter['bool']['should'][] = [
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

                $articleSearcher->filterByCities($cityFilters);
            });

            // filter by mall_id (use in mall homepage/mall detail)
            OrbitInput::get('mall_id', function ($mallId) use (&$mallFilterCampaign, &$mallFilterStore) {
                $mallFilterCampaign = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => ['must' => ['match' => ['link_to_tenant.parent_id' => $mallId]]]], 'inner_hits' => ['name' => 'link_tenant_hits']]];
                $mallFilterStore = ['nested' => ['path' => 'tenant_detail', 'query' => ['bool' => ['must' => ['match' => ['tenant_detail.mall_id' => $mallId]]]], 'inner_hits' => ['name' => 'tenant_detail_hits']]];
            });

            if (! empty($articleObjectType) && ! empty($articleObjectId)) {
                $searchLinkedObjects = true;
            }

            // filter by keywords
            OrbitInput::get('keywords', function($keywords) use (&$keywordFilter, &$keywordFilterShould, &$keywordMallFilter, &$keywordMallFilterShould) {
                $forbiddenCharacter = array('>', '<', '(', ')', '{', '}', '[', ']', '^', '"', '~', '/', ':');
                $keywords = str_replace($forbiddenCharacter, '', $keywords);

                $esPriority = Config::get('orbit.elasticsearch.priority');

                // for campaign
                $priorityName = isset($esPriority['news']['name']) ? $esPriority['news']['name'] : '^6';
                $priorityObjectType = isset($esPriority['news']['object_type']) ? $esPriority['news']['object_type'] : '^5';
                $priorityDescription = isset($esPriority['news']['description']) ? $esPriority['news']['description'] : '^4';
                $priorityKeywords = isset($esPriority['news']['keywords']) ? $esPriority['news']['keywords'] : '^3';
                $priorityProductTags = isset($esPriority['news']['product_tags']) ? $esPriority['news']['product_tags'] : '^3';
                $priorityCountry = isset($esPriority['news']['country']) ? $esPriority['news']['country'] : '';
                $priorityProvince = isset($esPriority['news']['province']) ? $esPriority['news']['province'] : '';
                $priorityCity = isset($esPriority['news']['city']) ? $esPriority['news']['city'] : '';
                $priorityMallName = isset($esPriority['news']['mall_name']) ? $esPriority['news']['mall_name'] : '';

                $keywordFilter['bool']['should'][] = array('query_string' => array('query' => '*' . $keywords .'*', 'fields' => array(
                                        "name" . $priorityName,
                                        "object_type" . $priorityObjectType,
                                        "description" . $priorityDescription,
                                        "keywords" . $priorityKeywords,
                                        "product_tags" . $priorityProductTags)));

                $keywordFilter['bool']['should'][] = array('nested' => array('path'=> 'translation', 'query' => array('match' => array("translation.description" . $priorityDescription => $keywords))));

                $keywordFilterShould =  ['nested' => [
                                            'path' => 'link_to_tenant',
                                            'query' => [
                                                'query_string' => [
                                                    'query' => '*' . $keywords . '*',
                                                    'fields' => [
                                                        'link_to_tenant.country' . $priorityCountry,
                                                        'link_to_tenant.province' . $priorityProvince,
                                                        'link_to_tenant.city' . $priorityCity,
                                                        'link_to_tenant.mall_name' . $priorityMallName,
                                                    ]
                                                ]
                                            ]
                                        ]];

                // for mall
                $priorityName = isset($esPriority['mall']['name']) ? $esPriority['mall']['name'] : '^6';
                $priorityObjectType = isset($esPriority['mall']['object_type']) ? $esPriority['mall']['object_type'] : '^5';
                $priorityDescription = isset($esPriority['mall']['description']) ? $esPriority['mall']['description'] : '^3';
                $priorityAddressLine = isset($esPriority['mall']['address_line']) ? $esPriority['mall']['address_line'] : '';
                $priorityCountry = isset($esPriority['mall']['country']) ? $esPriority['mall']['country'] : '';
                $priorityProvince = isset($esPriority['mall']['province']) ? $esPriority['mall']['province'] : '';
                $priorityCity = isset($esPriority['mall']['city']) ? $esPriority['mall']['city'] : '';

                $keywordMallFilter = array('query_string' => array('query' => '*' . $keywords .'*', 'fields' => array(
                                        "name" . $priorityName,
                                        "object_type" . $priorityObjectType,
                                        "description" . $priorityDescription,
                                        "address_line" . $priorityAddressLine)));

                $keywordMallFilterShould = array('query_string' => array('query' => '*' . $keywords .'*', 'fields' => array(
                                            "country" . $priorityCountry,
                                            "province" . $priorityProvince,
                                            "city" . $priorityCity)));

            });

            $articleKeyword = OrbitInput::get('article_keywords', null);
            $forbiddenCharacter = array('>', '<', '(', ')', '{', '}', '[', ']', '^', '"', '~', '/');
            $articleKeyword = str_replace($forbiddenCharacter, '', $articleKeyword);
            if (! empty($articleKeyword)) {
                $searchKeyword = true;
            }

            // filter by category
            OrbitInput::get('category_id', function($category_ids) use (&$categoryCampaignFilter, &$categoryStoreFilter) {
                foreach((array) $category_ids as $category_id) {
                    $categoryCampaignFilter['bool']['should'][] = ['match' => ['category_ids' => $category_id]];
                    $categoryStoreFilter['bool']['should'][] = ['match' => ['category' => $category_id]];
                }
            });

            // Get object categories.
            // Useful for case like related article to a campaign/object.
            // We will query the object to root of their store/mall and get the category.
            // Merge with the requested categories (if any).
            $articleCategories = array_merge($articleCategories, $this->getObjectCategories());

            if (! empty($articleCategories)) {
                $searchCategories = true;
            }

            // filter by sponsor provider
            OrbitInput::get('sponsor_provider_ids', function($sponsor_provider_ids) use (&$sponsorFilter) {
                $sponsor_provider_ids = (array) $sponsor_provider_ids;
                $sponsor_provider_ids = array_values($sponsor_provider_ids);

                $sponsorFilter = [
                        'nested' => [
                            'path' => 'sponsor_provider',
                            'query' => [
                                'terms' => [
                                    'sponsor_provider.sponsor_id' => $sponsor_provider_ids
                                ]
                            ]
                        ]
                ];
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$partnerFilterMustNot, &$partnerFilterMust){
                $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                            $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                 ->where('affected_group_names.group_type', '=', 'promotion');
                                        })
                                        ->where('partner_id', $partnerId)
                                        ->first();

                if (is_object($partnerAffected)) {
                $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                    if (in_array($partnerId, $exception)) {
                        $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');

                        $partnerFilterMustNot = ['terms' => [
                                                    'partner_ids' => $partnerIds
                                                    ]
                                                ];
                    }
                    else {
                        $partnerFilterMust = [
                            'match' => [
                                'partner_ids' => $partnerId
                            ]
                        ];
                    }
                }
            });

            // filter by my credit card and my wallet
            if ($myCCFilter) {
                if (strtolower($roleName) === 'consumer') {
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
                        $sponsorFilter = [
                                'nested' => [
                                    'path' => 'sponsor_provider',
                                    'query' => [
                                        'terms' => [
                                            'sponsor_provider.sponsor_id' => $sponsorProviderIds
                                        ]
                                    ]
                                ]
                        ];
                    }
                }
            }

            OrbitInput::get('gender', function($gender) use (&$genderFilter, &$genderFilterStore, $user) {
                $gender = strtolower($gender);
                if ($gender === 'mygender') {
                    $userGender = UserDetail::select('gender')->where('user_id', '=', $user->user_id)->first();
                    if ($userGender) {
                        if (strtolower($userGender->gender) == 'm') {
                            $genderFilter = ['match' => ['is_all_gender' => 'F']];
                            $genderFilterStore = ['match' => ['gender' => 'F']];
                        } else if (strtolower($userGender->gender) == 'f') {
                            $genderFilter = ['match' => ['is_all_gender' => 'M']];
                            $genderFilterStore = ['match' => ['gender' => 'M']];
                        }
                    }
                }
            });

            // Build article search filter.
            if ($searchLinkedObjects) {
                $articleSearcher->filterByLinkedObject($articleObjectType, $articleObjectId, 'should');

                if ($searchCategories) {
                    $articleSearcher->filterByCategories($articleCategories, 'should');
                }

                if ($searchKeyword) {
                    $articleSearcher->filterByKeyword($articleKeyword, 'should');
                }

                $articleSearcher->minimumShouldMatch(1);
            }
            else {
                if ($searchCategories) {
                    $articleSearcher->filterByCategories($articleCategories, 'must');
                }

                if ($searchKeyword) {
                    $articleSearcher->filterByKeyword($articleKeyword, 'must');
                }
            }

            // calculate rating and review based on location/mall
            $scriptFieldRating = "double counter = 0; double rating = 0;";
            $scriptFieldReview = "double review = 0;";

            if (! empty($mallId)) {
                // count total review and average rating for store inside mall
                $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('mall_rating.rating_" . $mallId . "')) { if (! doc['mall_rating.rating_" . $mallId . "'].empty) { counter = counter + doc['mall_rating.review_" . $mallId . "'].value; rating = rating + (doc['mall_rating.rating_" . $mallId . "'].value * doc['mall_rating.review_" . $mallId . "'].value);}};";
                $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('mall_rating.review_" . $mallId . "')) { if (! doc['mall_rating.review_" . $mallId . "'].empty) { review = review + doc['mall_rating.review_" . $mallId . "'].value;}}; ";
            } else if (! empty($cityFilters)) {
                // count total review and average rating based on city filter
                $countryId = $countryData->country_id;
                foreach ((array) $cityFilters as $cityFilter) {
                    $scriptFieldRating = $scriptFieldRating . " if (doc.containsKey('location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { counter = counter + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value; rating = rating + (doc['location_rating.rating_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value * doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value);}}; ";
                    $scriptFieldReview = $scriptFieldReview . " if (doc.containsKey('location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "')) { if (! doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].empty) { review = review + doc['location_rating.review_" . $countryId . "_" . str_replace(" ", "_", trim(strtolower($cityFilter), " ")) . "'].value;}}; ";
                }
            } else if (! empty($countryFilter)) {
                // count total review and average rating based on country filter
                $countryId = $countryData->country_id;
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

            $scriptFieldRating = $scriptFieldRating . " return (counter == 0 && rateLow == 0) || "."((counter>0) && (rating/counter >= rateLow) && (rating/counter <= rateHigh));";
            $scriptFieldReview = $scriptFieldReview . " if(review == 0) {return 0;} else {return review;}; ";

            $rateLow = (double) $ratingLow;
            $rateHigh = (double) $ratingHigh + 0.001;

            $paramFilterRating = compact('rateLow', 'rateHigh');

            $queryFilterRating = ['script' => ['script' => $scriptFieldRating, 'params' => $paramFilterRating]];

            // filter by gender
            if (! empty($genderFilter)) {
                $couponJsonQuery['query']['bool']['must_not'][] = $genderFilter;
                $campaignJsonQuery['query']['bool']['must_not'][] = $genderFilter;
            }

            if (! empty($genderFilterStore)) {
                $storeJsonQuery['query']['bool']['must_not'][] = $genderFilterStore;
                $merchantJsonQuery['query']['bool']['must_not'][] = $genderFilterStore;
            }


            if (! empty($campaignCountryFilter)) {
                $campaignJsonQuery['query']['bool']['must'][] = $campaignCountryFilter;
                $couponJsonQuery['query']['bool']['must'][] = $campaignCountryFilter;
            }

            if (! empty($campaignCityFilter)) {
                $campaignJsonQuery['query']['bool']['must'][] = $campaignCityFilter;
                $couponJsonQuery['query']['bool']['must'][] = $campaignCityFilter;
            }

            if (! empty($storeCountryFilter)) {
                $merchantJsonQuery['query']['bool']['must'][] = $storeCountryFilter;
            }

            if (! empty($storeCityFilter)) {
                $merchantJsonQuery['query']['bool']['must'][] = $storeCityFilter;
            }

            if (! empty($mallFilterCampaign)) {
                $campaignJsonQuery['query']['bool']['filter'][] = $mallFilterCampaign;
                $couponJsonQuery['query']['bool']['filter'][] = $mallFilterCampaign;
            }

            if (! empty($mallFilterStore)) {
                $merchantJsonQuery['query']['bool']['filter'][] = $mallFilterStore;
            }

            if (! empty($keywordFilter)) {
                $merchantJsonQuery['query']['bool']['must'][] = $keywordFilter;
                $campaignJsonQuery['query']['bool']['must'][] = $keywordFilter;
                $couponJsonQuery['query']['bool']['must'][] = $keywordFilter;
            }

            if (! empty($keywordFilterShould)) {
                $campaignJsonQuery['query']['bool']['should'][] = $keywordFilterShould;
                $couponJsonQuery['query']['bool']['should'][] = $keywordFilterShould;
            }

            if (! empty($keywordMallFilter)) {
                $mallJsonQuery['query']['bool']['must'][] = $keywordMallFilter;
            }

            if (! empty($keywordMallFilterShould)) {
                $mallJsonQuery['query']['bool']['should'][] = $keywordMallFilterShould;
            }


            // This scope for et list mall per bank, klik button location in bank detail page
            // There is no data bank in mall ES, so we need to get data mall id per bank
            if ($bankBaseMerchantId != null) {
                if ($bankBaseMerchantId != null) {
                    $mallIdsPerBank = [];

                    $bankBaseMerchant = BaseMerchant::select('base_merchant_id', 'name', 'country_id')
                    ->where('base_merchant_id', $bankBaseMerchantId)
                    ->where('status', 'active')
                    ->first();

                    if (! empty($bankBaseMerchant)) {
                        // Get mall list which have bank
                        $mallIdsPerBank = Tenant::select(DB::raw('mall.merchant_id'))
                                        ->join('merchants as mall', DB::raw('mall.merchant_id'), '=', 'merchants.parent_id')
                                        ->where('merchants.name', $bankBaseMerchant['name'])
                                        ->where('merchants.country_id', $bankBaseMerchant['country_id'])
                                        ->where('merchants.status', 'active')
                                        ->where(DB::raw('mall.status'), 'active')
                                        ->get();
                    }

                    $mallJsonQuery['query']['bool']['filter'][]['bool']['must']['terms']['_id'] = $mallIdsPerBank;
                }
            }
            // end scope list mall per bank


            if (! empty($categoryCampaignFilter)) {
                $campaignJsonQuery['query']['bool']['must'][] = $categoryCampaignFilter;
                $couponJsonQuery['query']['bool']['must'][] = $categoryCampaignFilter;
            }

            if (! empty($categoryStoreFilter)) {
                $merchantJsonQuery['query']['bool']['must'][] = $categoryStoreFilter;
            }

            if (! empty($sponsorFilter)) {
                $campaignJsonQuery['query']['bool']['must'][] = $sponsorFilter;
            }

            if (! empty($partnerFilterMustNot)) {
                $campaignJsonQuery['query']['bool']['must_not'][] = $partnerFilterMustNot;
                $couponJsonQuery['query']['bool']['must_not'][] = $partnerFilterMustNot;
                $merchantJsonQuery['query']['bool']['must_not'][] = $partnerFilterMustNot;
            }

            if (! empty($partnerFilterMust)) {
                $campaignJsonQuery['query']['bool']['must'][] = $partnerFilterMust;
                $couponJsonQuery['query']['bool']['must'][] = $partnerFilterMust;
                $merchantJsonQuery['query']['bool']['must'][] = $partnerFilterMust;
            }

            // filter by rating
            $merchantJsonQuery['query']['bool']['filter'][] = $queryFilterRating;
            $mallJsonQuery['query']['bool']['filter'][] = $queryFilterRating;
            $campaignJsonQuery['query']['bool']['filter'][] = $queryFilterRating;
            $couponJsonQuery['query']['bool']['filter'][] = $queryFilterRating;

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $newsIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.news.index');
            $promotionIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index');
            $couponIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index');
            $mallIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index');
            $merchantIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index', 'stores');
            $storeIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.store_details.index', 'store_details');

            // call es campaign
            $campaignParam = [
                'index'  => $newsIndex . ',' . $promotionIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.news.type'),
                'body' => json_encode($campaignJsonQuery)
            ];
            $campaignResponse = $client->search($campaignParam);

            $couponParam = [
                'index'  => $couponIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.news.type'),
                'body' => json_encode($couponJsonQuery)
            ];
            $couponResponse = $client->search($couponParam);

            // call es mall
            $mallParam = [
                'index'  => $mallIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => json_encode($mallJsonQuery)
            ];
            $mallResponse = $client->search($mallParam);

            // merchant
            $merchantParam = [
                'index'  => $merchantIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.stores.type'),
                'body' => json_encode($merchantJsonQuery)
            ];
            $merchantResponse = $client->search($merchantParam);

            // store
            $storeParam = [
                'index'  => $storeIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.store_details.type'),
                'body' => json_encode($storeJsonQuery)
            ];
            $storeResponse = $client->search($storeParam);

            // Get article list
            $articleResponse = $articleSearcher->getResult();

            $campaignRecords = $campaignResponse['aggregations']['campaign_index']['buckets'];
            $couponRecords = $couponResponse['aggregations']['campaign_index']['buckets'];
            $listOfRec = array();
            $listOfRec['promotions'] = 0;
            $listOfRec['coupons'] = 0;
            $listOfRec['news'] = 0;
            $listOfRec['articles'] = 0;

            foreach ($campaignRecords as $campaign) {
                $key = str_replace($esPrefix, '', $campaign['key']);
                $listOfRec[$key] = $campaign['doc_count'];
            }

            foreach ($couponRecords as $coupon) {
                $key = str_replace($esPrefix, '', $coupon['key']);
                $listOfRec[$key] = $coupon['doc_count'];
            }

            $productCount = $brandProduct->countResult($request);

            $productAffiliations = App::make(Product::class);
            $productAffiliationRequest = App::make(
                ProductAffiliationListRequest::class
            );
            $productAffiliationCount = $productAffiliations
                ->countResult($productAffiliationRequest);

            $listOfRec['mall'] = empty($mallResponse['hits']['total']) ? 0 : $mallResponse['hits']['total'];
            $listOfRec['merchants'] = empty($merchantResponse['hits']['total']) ? 0 : $merchantResponse['hits']['total'];
            $listOfRec['stores'] = empty($storeResponse['hits']['total']) ? 0 : $storeResponse['hits']['total'];
            $listOfRec['articles'] = empty($articleResponse['hits']['total']) ? 0 : $articleResponse['hits']['total'];
            $listOfRec['products'] = $productCount;
            $listOfRec['product_affiliations'] = $productAffiliationCount;

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);
            $data->records = $listOfRec;

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

    /**
     * Get object categories.
     *
     * @return [type] [description]
     */
    private function getObjectCategories()
    {
        $objectType = OrbitInput::get('object_type', '');
        $objectId = OrbitInput::get('object_id', '');

        switch ($objectType) {
            case 'event':
            case 'promotion':
                return $this->getNewsCategory($objectId);
                break;

            case 'brand':
            case 'store':
                return $this->getBrandCategory($objectId);
                break;

            case 'coupon':
                return $this->getCouponCategory($objectId);
                break;

            default:
                return [];
                break;
        }
    }

    /**
     * Get news/promotion categories.
     *
     * @param  string $newsId [description]
     * @return [type]         [description]
     */
    private function getNewsCategory($newsId = '')
    {
        return News::select('categories.category_id')
                     ->leftJoin('news_merchant', 'news.news_id', '=', 'news_merchant.news_id')
                     ->leftJoin('category_merchant', 'news_merchant.merchant_id', '=', 'category_merchant.merchant_id')
                     ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                     ->where('categories.merchant_id', 0)
                     ->where('categories.status', 'active')
                     ->where('news.news_id', $newsId)
                     ->groupBy('categories.category_id')
                     ->get()->lists('category_id');
    }

    /**
     * Get Brand/store categories.
     *
     * @param  string $brandId [description]
     * @return [type]          [description]
     */
    private function getBrandCategory($brandId = '')
    {
        return Tenant::select('categories.category_id')
                       ->leftJoin('category_merchant', 'merchants.merchant_id', '=', 'category_merchant.merchant_id')
                       ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                       ->where('categories.merchant_id', 0)
                       ->where('categories.status', 'active')
                       ->where('merchants.merchant_id', $brandId)
                       ->groupBy('categories.category_id')
                       ->get()->lists('category_id');
    }

    /**
     * Get coupon categories.
     *
     * @param  string $couponId [description]
     * @return [type]           [description]
     */
    private function getCouponCategory($couponId = '')
    {
        return Coupon::select('category_merchant.category_id')
                       ->leftJoin('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                       ->leftJoin('merchants', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                       ->leftJoin('category_merchant', 'merchants.merchant_id', '=', 'category_merchant.merchant_id')
                       ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                       ->where('categories.merchant_id', 0)
                       ->where('categories.status', 'active')
                       ->where('promotions.promotion_id', $couponId)
                       ->groupBy('categories.category_id')
                       ->get()->lists('category_id');
    }
}
