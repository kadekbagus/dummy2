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
use Orbit\Helper\Util\GTMSearchRecorder;
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
                    $withScore = true;
                    $withKeywordSearch = true;
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.store.keyword', '50%');

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

                    $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
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
                $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.store.category', '50%');
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['bool']['should'][] = array('match' => array('category' => $value));
                }
                $categoryFilter['bool']['minimum_should_match'] = $shouldMatch;
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
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.store.city', '50%');
                    $cityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['tenant_detail.city.raw' => $cityFilter]];
                    }

                    if (count((array) $cityFilters) === 1) {
                        // if user just filter with one city, value of should match must be 100%
                        $shouldMatch = '100%';
                    }

                    $countryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
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

            $sortby = $sort;
            if ($withScore) {
                $sortby = array('_score', $sort);
            }

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

            $advert_location_type = 'gtm';
            $advert_location_id = '0';
            if (! empty($mallId)) {
                $advert_location_type = 'mall';
                $advert_location_id = $mallId;
            }

            $adverts = Advert::select('adverts.advert_id',
                                    'adverts.link_object_id',
                                    'advert_placements.placement_type',
                                    'advert_placements.placement_order',
                                    'media.path',
                                    DB::raw("CASE WHEN placement_type = 'featured_list' THEN 0 ELSE 1 END AS with_preferred"))
                            ->join('advert_link_types', function ($q) {
                                $q->on('advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id');
                                $q->on('advert_link_types.advert_type', '=', DB::raw("'store'"));
                            })
                            ->join('advert_locations', function ($q) use ($advert_location_id, $advert_location_type) {
                                $q->on('advert_locations.advert_id', '=', 'adverts.advert_id');
                                $q->on('advert_locations.location_id', '=', DB::raw("'" . $advert_location_id . "'"));
                                $q->on('advert_locations.location_type', '=', DB::raw("'" . $advert_location_type . "'"));
                            })
                            ->join('advert_placements', function ($q) use ($list_type) {
                                $q->on('advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id');
                                if ($list_type === 'featured') {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('featured_list', 'preferred_list_regular', 'preferred_list_large')"));
                                } else {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('preferred_list_regular', 'preferred_list_large')"));
                                }
                            })
                            ->leftJoin('media', function ($q) {
                                $q->on("object_id", '=', "adverts.advert_id");
                                $q->on("media_name_long", '=', DB::raw("'advert_image_orig'"));
                            })
                            ->where('adverts.status', '=', DB::raw("'active'"))
                            ->where('adverts.start_date', '<=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.end_date', '>=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->orderBy('advert_placements.placement_order', 'desc');

            $advertList = DB::table(DB::raw("({$adverts->toSql()}) as adv"))
                         ->mergeBindings($adverts->getQuery())
                         ->select(DB::raw("adv.advert_id,
                                    adv.link_object_id,
                                    adv.placement_order,
                                    adv.path,
                                    adv.placement_type as placement_type_orig,
                                    CASE WHEN SUM(with_preferred) > 0 THEN 'preferred_list_large' ELSE placement_type END AS placement_type"))
                         ->groupBy(DB::raw("adv.link_object_id"))
                         ->take(100);

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $advertData = $advertCache->get($serializedCacheKey, function() use ($advertList) {
                    return $advertList->get();
                });
                $advertCache->put($serializedCacheKey, $advertData);
            } else {
                $advertData = $advertList->get();
            }

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $_jsonQuery = $jsonQuery;

            if ($withKeywordSearch) {
                // if user searching, we call es twice, first for get store data that match with keyword and then get the id,
                // and second, call es data combine with advert
                $_jsonQuery['query']['bool']['must'][] = $filterKeyword;

                $_esParam = [
                    'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index', 'stores'),
                    'type'   => Config::get('orbit.elasticsearch.indices.stores.type', 'basic'),
                    'body' => json_encode($_jsonQuery)
                ];

                if ($withCache) {
                    $searchResponse = $keywordSearchCache->get($serializedCacheKey, function() use ($client, &$_esParam) {
                        return $client->search($_esParam);
                    });
                    $keywordSearchCache->put($serializedCacheKey, $searchResponse);
                } else {
                    $searchResponse = $client->search($_esParam);
                }

                $searchData = $searchResponse['hits'];

                $storeIds = array();
                foreach ($searchData['hits'] as $content) {
                    foreach ($content as $key => $val) {
                        if ($key === "_id") {
                            $storeIds[] = $val;
                            $cId = $val;
                        }
                        if ($key === "_score") {
                            $storeScore[$cId] = $val;
                        }
                    }
                }
                $jsonQuery['query']['bool']['must'][] = array('terms' => array('_id' => $storeIds));
            }

            // call es
            if (! empty($advertData)) {
                unset($jsonQuery['sort']);
                $withScore = true;
                foreach ($advertData as $dt) {
                    if ($list_type === 'featured') {
                        if ($dt->placement_type_orig === 'featured_list') {
                            $advertIds[] = $dt->advert_id;
                            $boost = $dt->placement_order * 3;
                            $esAdvert = array('match' => array('_id' => array('query' => $dt->link_object_id, 'boost' => $boost)));
                            $jsonQuery['query']['bool']['should'][] = $esAdvert;
                        }
                    } else {
                        $advertIds[] = $dt->advert_id;
                        $boost = $dt->placement_order * 3;
                        $esAdvert = array('match' => array('_id' => array('query' => $dt->link_object_id, 'boost' => $boost)));
                        $jsonQuery['query']['bool']['should'][] = $esAdvert;
                    }
                }

                if ($withKeywordSearch) {
                    $withoutAdv = array_diff($storeIds, $advertIds);
                    foreach ($withoutAdv as $wa) {
                        $esWithoutAdvert = array('match' => array('_id' => array('query' => $wa, 'boost' => $storeScore[$wa])));
                        $jsonQuery['query']['bool']['should'][] = $esWithoutAdvert;
                    }
                } else {
                    $jsonQuery['query']['bool']['should'][] = array('match_all' => new stdclass());
                }

            }

            $sortby = $sort;
            if ($withScore) {
                $sortby = array("_score", $sort);
            }
            $jsonQuery["sort"] = $sortby;

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index', 'stores'),
                'type'   => Config::get('orbit.elasticsearch.indices.stores.type', 'basic'),
                'body' => json_encode($jsonQuery)
            ];

            if ($withCache) {
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

                    // advert
                    if ($key === "merchant_id") {
                        $data['placement_type'] = null;
                        $data['placement_type_orig'] = null;
                        foreach ($advertData as $advData) {

                            if ($advData->link_object_id === $value) {
                                $data['placement_type'] = $advData->placement_type;
                                $data['placement_type_orig'] = $advData->placement_type_orig;

                                // image
                                if (! empty($advData->path)) {
                                    $data['logo_url'] = $advData->path;
                                }
                                break;
                            }
                        }
                    }
                }

                if (! empty($record['inner_hits']['tenant_detail']['hits']['total'])) {
                    if (! empty($mallId)) {
                        $data['merchant_id'] = $record['inner_hits']['tenant_detail']['hits']['hits'][0]['_source']['merchant_id'];
                    }
                }
                $data['score'] = $record['_score'];
                $listOfRec[] = $data;
            }

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Store',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => $sort_by,
                    'partner' => OrbitInput::get('partner_id', NULL)
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
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

}