<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for get all store in all mall, group by name
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Tenant;
use Advert;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Language;
use Activity;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Lang;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Orbit\Controller\API\v1\Pub\Store\StoreHelper;

class StoreListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get all store in all mall, group by name
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
    public function getStoreList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $mall = NULL;
        $mall_name = NULL;
        $mall_city = NULL;
        $user = NULL;
        $httpCode = 200;

        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'store-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');
        $advertCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-adverts');
        $countCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-store-count');

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $ul = OrbitInput::get('ul');
            $lon = 0;
            $lat = 0;
            $list_type = OrbitInput::get('list_type', 'preferred');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $mallId = OrbitInput::get('mall_id', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            // store can not sorted by date, so it must be changes to default sorting (name - ascending)
            if ($sort_by === "created_date") {
                $sort_by = "name";
                $sort_mode = "asc";
            }

            // Call validation from store helper
            $storeHelper = StoreHelper::create();
            $storeHelper->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,updated_date',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sort_by, 'sort_mode' => $sort_mode, 'language' => $language,
                'location' => $location,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'list_type' => $list_type,
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

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;
            $esTake = $take;
            if ($list_type === 'featured') {
                $esTake = 50;
            }

            // value will be true if query to nested, *to get right number of stores
            $withInnerHits = false;
            $innerHitsCountry = false;
            $innerHitsCity = false;

            $jsonQuery = array('from' => $skip, 'size' => $take, 'aggs' => array('count' => array('nested' => array('path' => 'tenant_detail'), 'aggs' => array('top_reverse_nested' => array('reverse_nested' => new stdclass())))), 'query' => array('bool' => array('must' => array( array('range' => array('tenant_detail_count' => array('gt' => 0)))))));

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
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$withKeywordSearch, &$cacheKey, &$filterKeyword)
            {
                $cacheKey['keyword'] = $keyword;
                if ($keyword != '') {
                    $keyword = strtolower($keyword);
                    $searchFlag = $searchFlag || TRUE;
                    $withKeywordSearch = true;
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.store.keyword', '');

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.store.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.store.object_type', '^5');
                    $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.store.mall_name', '^4');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.store.city', '^3');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.store.province', '^2');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.store.keywords', '');
                    $priority['address_line'] = Config::get('orbit.elasticsearch.priority.store.address_line', '');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.store.country', '');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.store.description', '');

                    $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.description'.$priority['description'])))));

                    $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'tenant_detail', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('tenant_detail.city'.$priority['city'], 'tenant_detail.province'.$priority['province'], 'tenant_detail.country'.$priority['country'], 'tenant_detail.mall_name'.$priority['mall_name'])))));

                    $filterKeyword['bool']['should'][] = array('multi_match' => array('query' => $keyword, 'fields' => array('name'.$priority['name'],'object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));

                    if ($shouldMatch != '') {
                        $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
                    }

                    $jsonQuery['query']['bool']['must'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery, &$withInnerHits) {
                if (! empty($mallId)) {
                    $withInnerHits = true;
                    $withMallId = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('match' => array('tenant_detail.mall_id' => $mallId)))), 'inner_hits' => new stdclass()));
                    $jsonQuery['query']['bool']['must'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.store.category', '');
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['bool']['should'][] = array('match' => array('category' => $value));
                }

                if ($shouldMatch != '') {
                    $categoryFilter['bool']['minimum_should_match'] = '';
                }
                $jsonQuery['query']['bool']['must'][] = $categoryFilter;
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery, $prefix, &$searchFlag, &$cacheKey) {
                $cacheKey['partner_id'] = $partnerId;
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $searchFlag = $searchFlag || TRUE;
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'tenant');
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
                        $jsonQuery['query']['bool']['must'][]= $partnerFilter;
                    }
                }
            });

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$jsonQuery, &$searchFlag, &$withScore, $lat, $lon, $distance, &$withInnerHits, &$withCache)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;
                    $withInnerHits = true;
                    if ($location === 'mylocation' && $lat != '' && $lon != '') {
                        $withCache = FALSE;
                        $locationFilter = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'tenant_detail.position' => array('lon' => $lon, 'lat' => $lat))))), 'inner_hits' => new stdclass()));
                        $jsonQuery['query']['bool']['must'][] = $locationFilter;
                    } elseif ($location !== 'mylocation') {
                        $locationFilter = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('match' => array('tenant_detail.city.raw' => $location)))), 'inner_hits' => new stdclass()));
                        $jsonQuery['query']['bool']['must'][] = $locationFilter;
                    }
                }
            });

            $countryCityFilterArr = [];

            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$withInnerHits, &$innerHitsCity, &$countryCityFilterArr) {
                $withInnerHits = true;
                $innerHitsCity = true;

                $countryCityFilterArr = ['nested' => ['path' => 'tenant_detail', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];

                $countryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['tenant_detail.country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, $countryFilter, &$countryCityFilterArr) {
                if (! empty($countryFilter)) {
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.store.city', '');
                    $cityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['tenant_detail.city.raw' => $cityFilter]];
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
                $jsonQuery['query']['bool']['must'][] = $countryCityFilterArr;
            }

            // sort by name or location
            if ($sort_by === 'location' && $lat != '' && $lon != '') {
                $searchFlag = $searchFlag || TRUE;
                $withCache = FALSE;
                $sort = array('_geo_distance' => array('nested_path' => 'tenant_detail', 'tenant_detail.position' => array('lon' => $lon, 'lat' => $lat), 'order' => $sort_mode, 'unit' => 'km', 'distance_type' => 'plane'));
            } elseif ($sort_by === 'updated_date') {
                $sort = array('updated_at' => array('order' => $sort_mode));
            } else {
                $sort = array('name.raw' => array('order' => $sort_mode));
            }

            $sortByPageType = array();
            $pageTypeScore = '';
            if ($list_type === 'featured') {
                $pageTypeScore = 'featured_gtm_score';
                $sortByPageType = array('featured_gtm_score' => array('order' => 'desc'));
                if (! empty($mallId)) {
                    $pageTypeScore = 'tenant_detail.featured_mall_score';
                    $sortByPageType = array('tenant_detail.featured_mall_score' => array('order' => 'desc', 'nested_path' => 'tenant_detail'));
                }
            } else {
                $pageTypeScore = 'preferred_gtm_score';
                $sortByPageType = array('preferred_gtm_score' => array('order' => 'desc'));
                if (! empty($mallId)) {
                    $pageTypeScore = 'tenant_detail.preferred_mall_score';
                    $sortByPageType = array('tenant_detail.preferred_mall_score' => array('order' => 'desc', 'nested_path' => 'tenant_detail'));
                }
            }

            $sortby = array($sortByPageType, $sort);
            if ($withScore) {
                $sortby = array($sortByPageType, "_score", $sort);
            }
            $jsonQuery["sort"] = $sortby;

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

            $excludeAdvertQuery = array('match' => array($pageTypeScore => 0));
            if (! empty($mallId)) {
                $excludeAdvertQuery = array('nested' => array('path' => 'tenant_detail', 'query' => array('match' => array($pageTypeScore => 0))));
            }

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $esIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index');
            $locationId = ! empty($mallId) ? $locationId : 0;
            $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_reguler', 'preferred_list_large'] : ['preferred_list_reguler', 'preferred_list_large'];

            // call advert before call main query
            $esAdvertQuery = array('query' => array('bool' => array('must' => array( array('query' => array('match' => array('advert_status' => 'active'))), array('range' => array('advert_start_date' => array('lte' => $dateTimeEs))), array('range' => array('advert_end_date' => array('gte' => $dateTimeEs))), array('match' => array('advert_location_ids' => $locationId)), array('terms' => array('advert_type' => $advertType))))), 'sort' => $sortByPageType);

            $jsonQuery['query']['bool']['must'][] = array('bool' => array('should' => array($esAdvertQuery['query'], array('bool' => array('must_not' => array(array('exists' => array('field' => 'advert_status'))))))));

            $esAdvertParam = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_stores.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.advert_stores.type'),
                'body'  => json_encode($esAdvertQuery)
            ];

            $advertResponse = $client->search($esAdvertParam);
            if ($advertResponse['hits']['total'] > 0) {
                $esIndex = $esIndex . ',' . $esPrefix . Config::get('orbit.elasticsearch.indices.advert_stores.index');
                $advertList = $advertResponse['hits']['hits'];
                $excludeId = array();
                $withPreferred = array();

                foreach ($advertList as $adverts) {
                    $advertId = $adverts['_id'];
                    $merchantId = $adverts['_source']['merchant_id'];
                    if(! in_array($merchantId, $excludeId)) {
                        $excludeId[] = $merchantId;
                    } else {
                        $excludeId[] = $advertId;
                    }

                    // if featured list_type check preferred too
                    if ($list_type === 'featured') {
                        if ($adverts['_source']['advert_type'] === 'preferred_list_reguler' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                            if (empty($withPreferred[$merchantId]) || $withPreferred[$merchantId] != 'preferred_list_large') {
                                $withPreferred[$merchantId] = 'preferred_list_reguler';
                                if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                    $withPreferred[$merchantId] = 'preferred_list_large';
                                }
                            }
                        }
                    }
                }

                $jsonQuery['query']['bool']['must_not'][] = array('terms' => ['_id' => $excludeId]);
            }

            $esParam = [
                'index'  => $esIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.stores.type', 'basic'),
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
            $innerHitsCount = 0;

            foreach ($records['hits'] as $record) {
                $data = array();
                $localPath = '';
                $cdnPath = '';
                $default_lang = '';
                $pageView = 0;
                $data['placement_type'] = null;
                $data['placement_type_orig'] = null;
                $storeId = '';
                foreach ($record['_source'] as $key => $value) {

                    $localPath = ($key == 'logo') ? $value : $localPath;
                    $cdnPath = ($key == 'logo_cdn') ? $value : $cdnPath;
                    $key = ($key == 'logo') ? 'logo_url' : $key;

                    $data[$key] = $value;
                    $data['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);

                    $default_lang = (empty($record['_source']['default_lang']))? '' : $record['_source']['default_lang'];

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        foreach ($record['_source']['translation'] as $dt) {

                            if ($dt['language_code'] === $language) {
                                // desc
                                if (! empty($dt['description'])) {
                                    $data['description'] = $dt['description'];
                                }
                            } elseif ($dt['language_code'] === $default_lang) {
                                // desc
                                if (! empty($dt['description']) && empty($data['description'])) {
                                    $data['description'] = $dt['description'];
                                }
                            }
                        }
                    }

                    // advert type
                    if ($list_type === 'featured') {
                        if ($key === 'merchant_id') {
                            $storeId = $value;
                        }

                        if (! empty($mallId) && $key === 'featured_mall_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        } elseif ($key === 'featured_gtm_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        }

                        if (! empty($withPreferred[$storeId])) {
                            $data['placement_type'] = $withPreferred[$storeId];
                            $data['placement_type_orig'] = $withPreferred[$storeId];
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
                }

                if (! empty($record['inner_hits']['tenant_detail']['hits']['total'])) {
                    if (! empty($mallId)) {
                        $data['merchant_id'] = $record['inner_hits']['tenant_detail']['hits']['hits'][0]['_source']['merchant_id'];
                    }
                }

                $data['page_view'] = $pageView;
                $data['score'] = $record['_score'];
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

            // // random featured adv
            // // @todo fix for random -- this is not the right way to do random, it could lead to memory leak
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

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall store list page');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_store_list')
                            ->setActivityNameLong('View mall store list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Store')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Store list');
                        $activity->setUser($user)
                            ->setActivityName('view_stores_main_page')
                            ->setActivityNameLong('View Stores Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Store')
                            ->setNotes($activityNotes)
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
}