<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for promotion list and search in landing page
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
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\Promotion\PromotionHelper;
use Mall;
use stdClass;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use PartnerAffectedGroup;
use PartnerCompetitor;

class PromotionListAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;

    /**
     * GET - get active promotion in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
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
    public function getSearchPromotion()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;
        $cacheKey = [];
        $serializedCacheKey = '';

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'promotion-list';
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
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('promotion');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $partnerToken = OrbitInput::get('token', null);

             // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $promotionHelper = PromotionHelper::create();
            $promotionHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date,updated_date',
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

            $valid_language = $promotionHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            //Get now time, time must be 2017-01-09T15:30:00Z
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

            $jsonQuery = array('from' => $skip, 'size' => $esTake, 'query' => array('bool' => array('must' => array( array('query' => array('match' => array('status' => 'active'))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

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
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$withKeywordSearch, &$cacheKey, &$filterKeyword)
            {
                $cacheKey['keyword'] = $keyword;
                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withScore = true;
                    $withKeywordSearch = true;
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.keyword', '50%');

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.promotions.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.promotions.object_type', '^5');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.promotions.keywords', '^4');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.promotions.description', '^3');
                    $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.promotions.mall_name', '^3');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.promotions.city', '^2');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.promotions.province', '^2');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.promotions.country', '^2');

                    $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.name'.$priority['name'], 'translation.description'.$priority['description'])))));

                    $filterKeyword['bool']['should'][] = array('nested' => array('path' => 'link_to_tenant', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('link_to_tenant.city'.$priority['city'], 'link_to_tenant.province'.$priority['province'], 'link_to_tenant.country'.$priority['country'], 'link_to_tenant.mall_name'.$priority['mall_name'])))));

                    $filterKeyword['bool']['should'][] = array('multi_match' => array('query' => $keyword, 'fields' => array('object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));

                    $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.parent_id' => $mallId))))));
                    $jsonQuery['query']['bool']['must'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
                $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.category', '50%');
                $searchFlag = $searchFlag || TRUE;
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['bool']['should'][] = array('match' => array('category_ids' => $value));
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
                                                                     ->where('affected_group_names.group_type', '=', 'promotion');
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
                        $jsonQuery['query']['bool']['must'][] = $partnerFilter;
                    }
                }
            });

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$jsonQuery, &$searchFlag, &$withScore, $lat, $lon, $distance, &$withCache)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;
                    $withCache = FALSE;
                    if ($location === 'mylocation' && $lat != '' && $lon != '') {
                        $locationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat)))))));
                        $jsonQuery['query']['bool']['must'][] = $locationFilter;
                    } elseif ($location !== 'mylocation') {
                        $locationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.city.raw' => $location))))));
                        $jsonQuery['query']['bool']['must'][] = $locationFilter;
                    }
                }
            });

            $countryCityFilterArr = [];
            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$countryCityFilterArr) {
                $countryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];

                $countryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, $countryFilter, &$countryCityFilterArr) {
                if (! empty($countryFilter)) {
                    $cityFilterArr = [];
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.city', '50%');
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
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

            if ($sort_by === 'location' && $lat != '' && $lon != '') {
                $searchFlag = $searchFlag || TRUE;
                $withCache = FALSE;
                $sort = array('_geo_distance' => array('nested_path' => 'link_to_tenant', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat), 'order' => $sort_mode, 'unit' => 'km', 'distance_type' => 'plane'));
            } elseif ($sort_by === 'created_date') {
                $sort = array('begin_date' => array('order' => $sort_mode));
            } elseif ($sort_by === 'updated_date') {
                $sort = array('updated_at' => array('order' => $sort_mode));
            } else {
                $sort = array('name.raw' => array('order' => $sort_mode));
            }

            $sortby = $sort;
            if ($withScore) {
                $sortby = array("_score", $sort);
            }
            $jsonQuery["sort"] = $sortby;

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
                                $q->on('advert_link_types.advert_link_name', '=', DB::raw("'Promotion'"));
                                $q->on('advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id');
                            })
                            ->join('advert_locations', function ($q) use ($advert_location_id, $advert_location_type) {
                                $q->on('advert_locations.location_type', '=', DB::raw("'" . $advert_location_type . "'"));
                                $q->on('advert_locations.location_id', '=', DB::raw("'" . $advert_location_id . "'"));
                                $q->on('advert_locations.advert_id', '=', 'adverts.advert_id');
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
                            ->where('adverts.start_date', '<=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.end_date', '>=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.status', '=', DB::raw("'active'"))
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
                // if user searching, we call es twice, first for get coupon data that match with keyword and then get the id,
                // and second, call es data combine with advert
                $_jsonQuery['query']['bool']['must'][] = $filterKeyword;

                $_esParam = [
                    'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index'),
                    'type'   => Config::get('orbit.elasticsearch.indices.promotions.type'),
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

                $promotionIds = array();
                foreach ($searchData['hits'] as $content) {
                    foreach ($content as $key => $val) {
                        if ($key === "_id") {
                            $promotionIds[] = $val;
                            $promId = $val;
                        }
                        if ($key === "_score") {
                            $promotionScore[$promId] = $val;
                        }
                    }
                }
                $jsonQuery['query']['bool']['must'][] = array('terms' => array('_id' => $promotionIds));
            }

            // call es
            if (! empty($advertData)) {
                unset($jsonQuery['sort']);
                $withScore = true;
                foreach ($advertData as $dt) {
                    $advertIds[] = $dt->advert_id;
                    $boost = $dt->placement_order * 3;
                    $esAdvert = array('match' => array('_id' => array('query' => $dt->link_object_id, 'boost' => $boost)));
                    $jsonQuery['query']['bool']['should'][] = $esAdvert;
                }

                if ($withKeywordSearch) {
                    $withoutAdv = array_diff($couponIds, $advertIds);
                    foreach ($withoutAdv as $wa) {
                        $esWithoutAdvert = array('match' => array('_id' => array('query' => $wa, 'boost' => $promotionScore[$wa])));
                        $jsonQuery['query']['bool']['should'][] = $esWithoutAdvert;
                    }
                } else {
                    $jsonQuery['query']['bool']['should'][] = array('match_all' => new stdclass());
                }

            }

            // Exclude specific document Ids, useful for some cases e.g You May Also Like
            // @todo rewrite deprected 'filtered' query to bool only
            OrbitInput::get('excluded_ids', function($excludedIds) use (&$jsonQuery) {
                $jsonExcludedIds = [];
                foreach ($excludedIds as $excludedId) {
                    $jsonExcludedIds[] = array('term' => ['_id' => $excludedId]);
                }
                $jsonQuery['query']['bool']['must_not'] = $jsonExcludedIds;
            });

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

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.promotions.type'),
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

            foreach ($records['hits'] as $record) {
                $data = array();
                $isOwned = false;
                $default_lang = '';
                $partnerTokens = isset($record['_source']['partner_tokens']) ? $record['_source']['partner_tokens'] : [];
                foreach ($record['_source'] as $key => $value) {
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

                    // advert
                    if ($key === "news_id") {
                        $data['placement_type'] = null;
                        $data['placement_type_orig'] = null;
                        foreach ($advertData as $advData) {

                            if ($advData->link_object_id === $value) {
                                $data['placement_type'] = $advData->placement_type;
                                $data['placement_type_orig'] = $advData->placement_type_orig;

                                // image
                                if (! empty($advData->path)) {
                                    $data['image_url'] = $advData->path;
                                }
                                break;
                            }
                        }
                    }

                    if ($key === "is_exclusive") {
                        $data[$key] = ! empty($data[$key]) ? $data[$key] : 'N';
                        // disable is_exclusive if token is sent and in the partner_tokens
                        if ($data[$key] === 'Y' && in_array($partnerToken, $partnerTokens)) {
                            $data[$key] = 'N';
                        }
                    }
                }

                $data['score'] = $record['_score'];
                unset($data['created_by'], $data['creator_email'], $data['partner_tokens']);
                $listOfRec[] = $data;
            }

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Coupon',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name'),
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
                        $activityNotes = sprintf('Page viewed:  View mall promotion list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_promotion_list')
                            ->setActivityNameLong('View mall promotion list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Promotion')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Promotion list');
                        $activity->setUser($user)
                            ->setActivityName('view_promotions_main_page')
                            ->setActivityNameLong('View Promotions Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Promotion')
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