<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
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
use News;
use Tenant;
use Advert;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Language;
use Coupon;
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

class StoreAPIController extends PubControllerAPI
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

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $location = OrbitInput::get('location', null);
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

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            // store can not sorted by date, so it must be changes to default sorting (name - ascending)
            if ($sort_by === "created_date") {
                $sort_by = "name";
                $sort_mode = "asc";
            }

            $this->registerCustomValidation();
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
                'location' => $location, 'ul' => $ul,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,

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

            $jsonQuery = array('from' => $skip, 'size' => $esTake, 'aggs' => array('count' => array('nested' => array('path' => 'tenant_detail'), 'aggs' => array('top_reverse_nested' => array('reverse_nested' => new stdclass())))), 'query' => array('filtered' => array('filter' => array('and' => array( array('range' => array('tenant_detail_count' => array('gt' => 0))))))));

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
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$withKeywordSearch, &$cacheKey)
            {
                $cacheKey['keyword'] = $keyword;
                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withScore = true;
                    $withKeywordSearch = true;

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.store.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.store.object_type', '^5');
                    $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.store.mall_name', '^4');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.store.city', '^3');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.store.province', '^2');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.store.keywords', '');
                    $priority['address_line'] = Config::get('orbit.elasticsearch.priority.store.address_line', '');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.store.country', '');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.store.description', '');

                    $filterTranslation = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.description'.$priority['description'])))));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterTranslation;

                    $filterDetail = array('nested' => array('path' => 'tenant_detail', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('tenant_detail.city'.$priority['city'], 'tenant_detail.province'.$priority['province'], 'tenant_detail.country'.$priority['country'], 'tenant_detail.mall_name'.$priority['mall_name'])))));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterDetail;

                    $filterKeyword = array('multi_match' => array('query' => $keyword, 'fields' => array('name'.$priority['name'],'object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery, &$withInnerHits) {
                if (! empty($mallId)) {
                    $withInnerHits = true;
                    $withMallId = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('match' => array('tenant_detail.mall_id' => $mallId)))), 'inner_hits' => new stdclass()));
                    $jsonQuery['query']['filtered']['filter']['and'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['or'][] = array('match' => array('category' => $value));
                }
                $jsonQuery['query']['filtered']['filter']['and'][] = $categoryFilter;
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
                        $jsonQuery['query']['filtered']['filter']['and'][] = $partnerFilter;
                    }
                }
            });

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$jsonQuery, &$searchFlag, &$withScore, $lat, $lon, $distance, &$withInnerHits)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;
                    $withInnerHits = true;
                    if ($location === 'mylocation' && $lat != '' && $lon != '') {
                        $locationFilter = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'tenant_detail.position' => array('lon' => $lon, 'lat' => $lat))))), 'inner_hits' => new stdclass()));
                        $jsonQuery['query']['filtered']['filter']['and'][] = $locationFilter;
                    } elseif ($location !== 'mylocation') {
                        $locationFilter = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('match' => array('tenant_detail.city.raw' => $location)))), 'inner_hits' => new stdclass()));
                        $jsonQuery['query']['filtered']['filter']['and'][] = $locationFilter;
                    }
                }
            });

            // sort by name or location
            if ($sort_by === 'location' && $lat != '' && $lon != '') {
                $searchFlag = $searchFlag || TRUE;
                $sort = array('_geo_distance' => array('tenant_detail.position' => array('lon' => $lon, 'lat' => $lat), 'order' => $sort_mode, 'unit' => 'km', 'distance_type' => 'plane'));
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

            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
            $advertData = $advertCache->get($serializedCacheKey, function() use ($advertList) {
                return $advertList->get();
            });
            $advertCache->put($serializedCacheKey, $advertData);

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $_jsonQuery = $jsonQuery;

            if ($withKeywordSearch) {
                // if user searching, we call es twice, first for get store data that match with keyword and then get the id,
                // and second, call es data combine with advert
                unset($jsonQuery['query']['filtered']['query']);

                $_esParam = [
                    'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.store.index'),
                    'type'   => Config::get('orbit.elasticsearch.indices.store.type'),
                    'body' => json_encode($_jsonQuery)
                ];

                $searchResponse = $keywordSearchCache->get($serializedCacheKey, function() use ($client, &$_esParam) {
                    return $client->search($_esParam);
                });
                $keywordSearchCache->put($serializedCacheKey, $searchResponse);

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
                $jsonQuery['query']['filtered']['filter']['and'][] = array('terms' => array('_id' => $storeIds));
            }

            // call es
            if (! empty($advertData)) {
                unset($jsonQuery['sort']);
                $withScore = true;
                foreach ($advertData as $dt) {
                    $advertIds[] = $dt->advert_id;
                    $boost = $dt->placement_order * 3;
                    $esAdvert = array('match' => array('_id' => array('query' => $dt->link_object_id, 'boost' => $boost)));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $esAdvert;
                }

                if ($withKeywordSearch) {
                    $withoutAdv = array_diff($storeIds, $advertIds);
                    foreach ($withoutAdv as $wa) {
                        $esWithoutAdvert = array('match' => array('_id' => array('query' => $wa, 'boost' => $storeScore[$wa])));
                        $jsonQuery['query']['filtered']['query']['bool']['should'][] = $esWithoutAdvert;
                    }
                } else {
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = array('match_all' => new stdclass());
                }

            }

            $sortby = $sort;
            if ($withScore) {
                $sortby = array("_score", $sort);
            }
            $jsonQuery["sort"] = $sortby;

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.store.index', 'stores'),
                'type'   => Config::get('orbit.elasticsearch.indices.store.type', 'basic'),
                'body' => json_encode($jsonQuery)
            ];

            $response = $recordCache->get($serializedCacheKey, function() use ($client, &$esParam) {
                return $client->search($esParam);
            });
            $recordCache->put($serializedCacheKey, $response);

            $records = $response['hits'];

            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $innerHitsCount = 0;

            foreach ($records['hits'] as $record) {
                $data = array();
                $localPath = '';
                $cdnPath = '';
                foreach ($record['_source'] as $key => $value) {

                    $localPath = ($key == 'logo') ? $value : $localPath;
                    $cdnPath = ($key == 'logo_cdn') ? $value : $cdnPath;
                    $key = ($key == 'logo') ? 'logo_url' : $key;

                    $data[$key] = $value;
                    $data['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);

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
                    $innerHitsCount = $innerHitsCount + $record['inner_hits']['tenant_detail']['hits']['total'];
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

            $totalStores = $response['aggregations']['count']['doc_count'];
            if ($withInnerHits) {
                $totalStores = $innerHitsCount;
            }

            $data = new \stdclass();
            $extras = new \stdClass();
            $extras->total_stores = $totalStores;
            $extras->total_merchants = $records['total'];

            $data->returned_records = count($listOfRec);
            $data->total_records = $records['total'];
            $data->extras = $extras;
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
     * GET - get mall list after click store name
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
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $storename = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Query without searching keyword
            $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                    ->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                    ->active();

            // Query list mall based on keyword. Handling description and keyword can be different with other stores
            if (! empty($keyword)) {
                $words = explode(" ", $keyword);
                $keywordSql = " 1=1 ";
                foreach ($words as $key => $value) {
                    if (strlen($value) === 1 && $value === '%') {
                        $keywordSql .= " or {$prefix}merchants.name like '%|{$value}%' escape '|' or {$prefix}keywords.keyword = '|{$value}' escape '|' ";
                    } else {
                        // escaping the query
                        $real_value = $value;
                        $word = '%' . $value . '%';
                        $value = $this->quote($word);
                        $keywordSql .= " or {$prefix}merchants.name like {$value} or {$prefix}keywords.keyword = {$this->quote($real_value)} ";
                    }
                }

                $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                        ->join(DB::raw("( select {$prefix}merchants.merchant_id, name, parent_id from {$prefix}merchants
                                            left join {$prefix}keyword_object on {$prefix}merchants.merchant_id = {$prefix}keyword_object.object_id
                                            left join {$prefix}keywords on {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                                            where name = {$this->quote($storename)}
                                            and {$prefix}merchants.status = 'active'
                                            and (" . $keywordSql . ")
                                        ) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                        ->active();
            }

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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
     * GET - get all detail store in all mall, group by name
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreDetail()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try {
            $user = $this->getUser();

            $merchantid = OrbitInput::get('merchant_id');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'merchantid' => $merchantid,
                    'language' => $language,
                ),
                array(
                    'merchantid' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $store = Tenant::select(
                                'merchants.merchant_id',
                                'merchants.name',
                                'merchants.name as mall_name',
                                DB::Raw("CASE WHEN (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN {$prefix}merchants.description
                                            ELSE (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            )
                                        END as description
                                    "),
                                'merchants.url'
                            )
                ->with(['categories' => function ($q) use ($valid_language, $prefix) {
                        $q->select(
                                DB::Raw("
                                        CASE WHEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                        WHERE ct.status = 'active'
                                                            and ct.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                            and ct.category_id = {$prefix}categories.category_id
                                                    ) != ''
                                            THEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                    WHERE ct.status = 'active'
                                                        and ct.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                        and category_id = {$prefix}categories.category_id
                                                    )
                                            ELSE {$prefix}categories.category_name
                                        END AS category_name
                                    ")
                            )
                            ->groupBy('categories.category_id')
                            ->orderBy('category_name')
                            ;
                    }, 'mediaLogo' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }, 'mediaImageOrig' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }, 'mediaImageCroppedDefault' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    },  'keywords' => function ($q) {
                        $q->addSelect('keyword', 'object_id');
                    }])
                ->join(DB::raw("(select merchant_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'")
                ->where('merchants.merchant_id', $merchantid);

            OrbitInput::get('mall_id', function($mallId) use ($store, &$mall, $prefix) {
                $store->where('merchants.parent_id', $mallId);
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            $store = $store->orderBy('merchants.created_at', 'asc')
                ->first();

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View mall store detail page');
                $activity->setUser($user)
                    ->setActivityName('view_mall_store_detail')
                    ->setActivityNameLong('View mall store detail')
                    ->setObject($store)
                    ->setLocation($mall)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: Landing Page Store Detail Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_store_detail')
                    ->setActivityNameLong('View GoToMalls Store Detail')
                    ->setObject($store)
                    ->setLocation($mall)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = $store;
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
     * GET - get mall detail list after click store name
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallDetailStore()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $storename = null;

        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'location-store-list';

        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');

        try {
            $user = $this->getUser();
            $mallId = OrbitInput::get('mall_id', null);
            $merchantId = OrbitInput::get('merchant_id');
            $location = OrbitInput::get('location');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $ul = OrbitInput::get('ul', null);
            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();

            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                ),
                array(
                    'merchant_id' => 'required',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'mall_id' => $mallId,
                'merchant_id' => $merchantId,
                'location' => $location,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance,
                'ul' => $ul,
                'take' => $take,
                'skip' => $skip,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $mallLogo = "CONCAT({$this->quote($urlPrefix)}, img.path) as location_logo";
            $mallMap = "CONCAT({$this->quote($urlPrefix)}, map.path) as map_image";
            if ($usingCdn) {
                $mallLogo = "CASE WHEN (img.cdn_url is null or img.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, img.path) ELSE img.cdn_url END as location_logo";
                $mallMap = "CASE WHEN (map.cdn_url is null or map.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, map.path) ELSE map.cdn_url END as map_image";
            }

            // Get store name base in merchant_id
            $store = Tenant::select('merchant_id', 'name')->where('merchant_id', $merchantId)->active()->first();
            if (! empty($store)) {
                $storename = $store->name;
            }

            // Query without searching keyword
            $mall = Mall::select('merchants.merchant_id',
                                    'merchants.name',
                                    'merchants.address_line1 as address',
                                    'merchants.city',
                                    'merchants.floor',
                                    'merchants.unit',
                                    'merchants.operating_hours',
                                    'merchants.is_subscribed',
                                    'merchants.object_type as location_type',
                                    DB::raw("{$mallLogo}"),
                                    DB::raw("{$mallMap}"),
                                    'merchants.phone',
                                    DB::raw("x(position) as latitude"),
                                    DB::raw("y(position) as longitude")
                                )
                    ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id')
                    // Map
                    ->leftJoin(DB::raw("{$prefix}media as map"), function($q) use ($prefix){
                        $q->on(DB::raw('map.object_id'), '=', "merchants.merchant_id")
                          ->on(DB::raw('map.media_name_long'), 'IN', DB::raw("('mall_map_orig', 'retailer_map_orig')"))
                          ;
                    })
                    // Logo
                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
                        $q->on(DB::raw('img.object_id'), '=', "merchants.merchant_id")
                          ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"))
                          ;
                    })

                    ->with(['tenants' => function ($q) use ($prefix, $storename) {
                            $q->select('merchants.merchant_id',
                                        'merchants.name as title',
                                        'merchants.phone',
                                        'merchants.url',
                                        'merchants.description',
                                        'merchants.parent_id',
                                        DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                                    )
                              ->join('objects', 'objects.object_id', '=', 'merchants.floor_id')
                              ->where('objects.object_type', 'floor')
                              ->where('merchants.name', $storename)
                              ->where('merchants.status', 'active')
                              ->with(['categories' => function ($q) {
                                    $q->select(
                                            'category_name'
                                        );
                                }, 'mediaMap' => function ($q) {
                                    $q->select(
                                            'media.object_id',
                                            'media.path'
                                        );
                                }]);
                        }, 'mediaLogo' => function ($q) {
                                    $q->select(
                                            'media.object_id',
                                            'media.path'
                                        );
                        }]);

            // Query list mall based on keyword. Handling description and keyword can be different with other stores
            if (! empty($keyword)) {

                $cacheKey['keyword'] = $keyword;

                $words = explode(" ", $keyword);
                $keywordSql = " 1=1 ";
                foreach ($words as $key => $value) {
                    if (strlen($value) === 1 && $value === '%') {
                        $keywordSql .= " or {$prefix}merchants.name like '%|{$value}%' escape '|' or {$prefix}keywords.keyword = '|{$value}' escape '|' ";
                    } else {
                        // escaping the query
                        $real_value = $value;
                        $word = '%' . $value . '%';
                        $value = $this->quote($word);
                        $keywordSql .= " or {$prefix}merchants.name like {$value} or {$prefix}keywords.keyword = {$this->quote($real_value)} ";
                    }
                }

                $mall = $mall->join(DB::raw("( select {$prefix}merchants.merchant_id, name, parent_id from {$prefix}merchants
                                            left join {$prefix}keyword_object on {$prefix}merchants.merchant_id = {$prefix}keyword_object.object_id
                                            left join {$prefix}keywords on {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                                            where {$prefix}merchants.status = 'active'
                                            and (" . $keywordSql . ")
                                        ) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                            ->active();
            } else {
                $mall = $mall->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                            ->active();
            }

            // Get user location
            $position = isset($ul)?explode("|", $ul):null;
            $lon = isset($position[0])?$position[0]:null;
            $lat = isset($position[1])?$position[1]:null;

            // Filter by location
            if (! empty($location)) {
                if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                    $mall->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                        ->havingRaw("distance <= {$distance}");
                } else {
                    $mall->where('merchants.city', $location);
                }
            };

            if (! empty($mallId)) {
                $mall->where('merchants.merchant_id', '=', $mallId)->first();
            }

            // Order data by nearby or city alphabetical
            if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                $mall->orderBy('distance', 'asc');
            } else {
                $mall->orderBy('city', 'asc');
                $mall->orderBy('merchants.name', 'asc');
            }

            $mall = $mall->groupBy('merchants.merchant_id');

            $_mall = clone $mall;
            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
            $recordCounter = RecordCounter::create($_mall);

            // Try to get the result from cache
            $totalRec = $totalRecordCache->get($serializedCacheKey, function() use ($recordCounter) {
                return $recordCounter->count();
            });

            // Put the result in cache if it is applicable
            $totalRecordCache->put($serializedCacheKey, $totalRec);

            $mall->take($take);
            $mall->skip($skip);

            // Try to get the result from cache
            $listOfRec = $recordCache->get($serializedCacheKey, function() use ($mall) {
                return $mall->get();
            });
            $recordCache->put($serializedCacheKey, $listOfRec);

            // moved from generic activity number 40
            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Store location list');
                $activity->setUser($user)
                    ->setActivityName('view_store_location')
                    ->setActivityNameLong('View Store Location Page')
                    ->setObject(null)
                    ->setObjectDisplayName($storename)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $totalRec;
            $this->response->data->returned_records = count($listOfRec);
            $this->response->data->records = $listOfRec;
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
     * GET - get campaign store list after click store name
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignStoreList()
    {
        $httpCode = 200;
        try {


            $sort_by = OrbitInput::get('sortby', 'campaign_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $merchant_id = OrbitInput::get('merchant_id');
            $store_name = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $category_id = OrbitInput::get('category_id');
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.tenant',
                    'language'    => 'required|orbit.empty.language_default',
                    'sortby'      => 'in:campaign_name,name,location,created_date',
                ),
                array(
                    'required'           => 'Merchant id is required',
                    'orbit.empty.tenant' => Lang::get('validation.orbit.empty.tenant'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $storeIds = Tenant::where('name', $this->store->name)->lists('merchant_id');

            // get news list
            $news = DB::table('news')->select(
                        'news.news_id as campaign_id',
                        'news.begin_date as begin_date',
                        DB::Raw("
                                 CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name
                            "),
                        'news.object_type as campaign_type',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (
                                    CASE WHEN {$prefix}news.end_date < (
                                        SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}news_merchant onm
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE onm.news_id = {$prefix}news.news_id
                                    )
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                    END
                                )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(onm.merchant_id)
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id
                                    AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END as original_media_path
                            "))
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->whereIn('merchants.merchant_id', $storeIds)
                        ->where('news.object_type', '=', 'news')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy('news.created_at', 'desc');

            // filter by mall id
            OrbitInput::get('mall_id', function($mallid) use ($news) {
                $news->where(function($q) use($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
            });

            // filter by city
            OrbitInput::get('location', function($location) use ($news, $prefix, $ul, $userLocationCookieName, $distance) {
                $news = $this->getLocation($prefix, $location, $news, $ul, $distance, $userLocationCookieName);
            });

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($news, $prefix) {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $news = $news->whereIn('merchants', $category_id);
                } else {
                    $news = $news->leftJoin('category_merchant', function($q) {
                                    $q->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
                                    $q->on('merchants.object_type', '=', DB::raw("'tenant'"));
                                })
                        ->whereIn('category_merchant.category_id', $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($news) {
                $news = ObjectPartnerBuilder::getQueryBuilder($news, $partner_id, 'news');
            });

            $promotions = DB::table('news')->select(
                        'news.news_id as campaign_id',
                        'news.begin_date as begin_date',
                        DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name
                        "),
                        'news.object_type as campaign_type',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (
                                    CASE WHEN {$prefix}news.end_date < (
                                        SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}news_merchant onm
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE onm.news_id = {$prefix}news.news_id
                                    )
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                    END
                                )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(onm.merchant_id)
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id
                                    AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END as original_media_path
                            "))
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })

                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->whereIn('merchants.merchant_id', $storeIds)
                        ->where('news.object_type', '=', 'promotion')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy('news.created_at', 'desc');

            // filter by mall id
            OrbitInput::get('mall_id', function($mallid) use ($promotions) {
                $promotions->where(function($q) use($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
            });

            // filter by city
            OrbitInput::get('location', function($location) use ($promotions, $prefix, $ul, $userLocationCookieName, $distance) {
                $promotions = $this->getLocation($prefix, $location, $promotions, $ul, $distance, $userLocationCookieName);
            });

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($promotions, $prefix) {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $promotions = $promotions->whereIn('merchants', $category_id);
                } else {
                    $promotions = $promotions->leftJoin('category_merchant', function($q) {
                                    $q->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
                                    $q->on('merchants.object_type', '=', DB::raw("'tenant'"));
                                })
                        ->whereIn('category_merchant.category_id', $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($promotions) {
                $promotions = ObjectPartnerBuilder::getQueryBuilder($promotions, $partner_id, 'promotion');
            });

            // get coupon list
            $coupons = DB::table('promotions')->select(DB::raw("
                                {$prefix}promotions.promotion_id as campaign_id,
                                {$prefix}promotions.begin_date as begin_date,
                                CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as campaign_name,
                                'coupon' as campaign_type,
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (
                                    CASE WHEN {$prefix}promotions.end_date < (
                                        SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}promotion_retailer opt
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id)
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                    END
                                )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(opt.promotion_retailer_id)
                                    FROM {$prefix}promotion_retailer opt
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}coupon_translations ct
                                        join {$prefix}media m
                                            on m.object_id = ct.coupon_translation_id
                                            and m.media_name_long = 'coupon_translation_image_orig'
                                        where ct.promotion_id = {$prefix}promotions.promotion_id
                                        group by ct.promotion_id
                                    ) ELSE {$prefix}media.path END as original_media_path
                            "))
                            ->leftJoin('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin(DB::raw("(SELECT promotion_id, COUNT(*) as tot FROM {$prefix}issued_coupons WHERE status = 'available' GROUP BY promotion_id) as available"), DB::raw("available.promotion_id"), '=', 'promotions.promotion_id')
                            ->whereRaw("available.tot > 0")
                            ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                            ->whereIn('merchants.merchant_id', $storeIds)
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('campaign_id')
                            ->orderBy(DB::raw("{$prefix}promotions.created_at"), 'desc');

            // filter by mall id
            OrbitInput::get('mall_id', function($mallid) use ($coupons) {
                $coupons->where(function($q) use($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
            });

            // filter by city
            OrbitInput::get('location', function($location) use ($coupons, $prefix, $ul, $userLocationCookieName, $distance) {
                $coupons = $this->getLocation($prefix, $location, $coupons, $ul, $distance, $userLocationCookieName);
            });

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($coupons, $prefix) {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $coupons = $coupons->whereIn('merchants', $category_id);
                } else {
                    $coupons = $coupons->leftJoin('category_merchant', function($q) {
                                    $q->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
                                    $q->on('merchants.object_type', '=', DB::raw("'tenant'"));
                                })
                        ->whereIn('category_merchant.category_id', $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($coupons) {
                $coupons = ObjectPartnerBuilder::getQueryBuilder($coupons, $partner_id, 'coupon');
            });

            $result = $news->unionAll($promotions)->unionAll($coupons);

            $querySql = $result->toSql();

            $campaign = DB::table(DB::Raw("({$querySql}) as campaign"))->mergeBindings($result);

            $_campaign = clone $campaign;

            if ($sort_by !== 'location') {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'campaign_name'   => 'campaign_name',
                    'name'            => 'campaign_name',
                    'created_date'    => 'begin_date',
                );

                $sort_by = $sortByMapping[$sort_by];
            }

            $take = PaginationNumber::parseTakeFromGet('campaign');

            $campaign->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $campaign->skip($skip);

            if ($sort_by !== 'location') {
                $campaign->orderBy($sort_by, $sort_mode);
            }

            $recordCounter = RecordCounter::create($_campaign);
            $totalRec = $recordCounter->count();
            $listcampaign = $campaign->get();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $totalRec;
            $this->response->data->returned_records = count($listcampaign);
            $this->response->data->records = $listcampaign;
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

    protected function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });

        // Check store is exists
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $store = Tenant::where('status', 'active')
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($store)) {
                return FALSE;
            }

            $this->store = $store;
            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function getLocation($prefix, $location, $query, $ul, $distance, $userLocationCookieName)
    {
        $query = $query->join('merchants as mp', function($q) use ($prefix) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("{$prefix}merchants.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("{$prefix}merchants.status"), '=', DB::raw("'active'"));
                            });

                if ($location === 'mylocation') {
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

                    if (!empty($lon) && !empty($lat)) {
                        $query = $query->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                        ->join('merchant_geofences', function ($q) use($prefix) {
                                                $q->on('merchant_geofences.merchant_id', '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"));
                                        });
                    }
                    $query = $query->havingRaw("distance <= {$distance}");
                } else {
                    $query = $query->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.city ELSE {$prefix}merchants.city END)"), $location);
                }

        return $query;
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
