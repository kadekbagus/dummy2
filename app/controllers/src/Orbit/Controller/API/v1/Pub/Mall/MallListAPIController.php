<?php namespace Orbit\Controller\API\v1\Pub\Mall;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Redis;
use Activity;
use Mall;
use PartnerAffectedGroup;
use PartnerCompetitor;
use stdClass;
use DB;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use MallCountry;
use MallCity;
use Country;
use Orbit\Helper\Util\FollowStatusChecker;

class MallListAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;

    /**
     * GET - check if mall inside map area
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallList()
    {
        $httpCode = 200;
        try {
            $activity = Activity::mobileci()->setActivityType('view');

            $this->checkAuth();
            $user = $this->api->user;

            // Cache result of all possible calls to backend storage
            $cacheConfig = Config::get('orbit.cache.context');
            $cacheContext = 'mall-list';
            $recordCache = SimpleCache::create($cacheConfig, $cacheContext);

            $keyword = OrbitInput::get('keyword');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', []);
            $countryFilter = OrbitInput::get('country', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', null);
            $partner_id = OrbitInput::get('partner_id', null);
            $sort_mode = OrbitInput::get('sortmode','asc');
            $ul = OrbitInput::get('ul', null);
            $radius = Config::get('orbit.geo_location.distance', 10);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $viewType = OrbitInput::get('view_type', 'grid');
            $latitude = '';
            $longitude = '';
            $locationFilter = '';
            $withCache = TRUE;

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();

            $jsonArea = array('from' => $skip, 'size' => $take, 'fields' => array("_source"), 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('is_subscribed' => 'Y')))))));

            $filterStatus = array('query' => array('match' => array('status' => 'active')));
            if ($usingDemo) {
                $filterStatus = array('query' => array('not' => array('term' => array('status' => 'deleted'))));
            }
            $jsonArea['query']['bool']['filter'][] = $filterStatus;

            // get user location, latitude and longitude. If latitude and longitude doesn't exist in query string, the code will be read cookie to get lat and lon
            if (empty($ul)) {
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

            // search by keyword
            $withScore = false;
            OrbitInput::get('keyword', function($keyword) use (&$jsonArea, &$searchFlag, &$withScore)
            {
                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withScore = true;
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.mall.keyword', '');

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.mall.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.mall.object_type', '^5');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.mall.city', '^3');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.mall.province', '^2');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.mall.keywords', '');
                    $priority['address_line'] = Config::get('orbit.elasticsearch.priority.mall.address_line', '');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.mall.country', '');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.mall.description', '');


                    $filterKeyword['bool']['should'][]= array('multi_match' => array('query' => $keyword, 'fields' => array('name'.$priority['name'], 'object_type'.$priority['object_type'], 'city'.$priority['city'], 'province'.$priority['province'], 'keywords'.$priority['keywords'], 'address_line'.$priority['address_line'], 'country'.$priority['country'], 'description'.$priority['description'])));

                    if ($shouldMatch != '') {
                        $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
                    }
                    $jsonArea['query']['bool']['filter'][] = $filterKeyword;
                }
            });

            // filter by location (city or user location)
            $words = 0;
            OrbitInput::get('location', function($location) use (&$jsonArea, &$searchFlag, &$withScore, &$words, $latitude, $longitude, $radius, &$withCache)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;
                    $words = count(explode(' ', $location));

                    if ($location === 'mylocation') {
                        $words = 0;
                    }

                    if ($location === 'mylocation' && $latitude != '' && $longitude != '') {
                        $withCache = FALSE;
                        $locationFilter = array('geo_distance' => array('distance' => $radius.'km', 'position' => array('lon' => $longitude, 'lat' => $latitude)));
                        $jsonArea['query']['bool']['filter'][] = $locationFilter;
                    } elseif ($location !== 'mylocation') {
                        $locationFilter = array('match' => array('city' => array('query' => $location, 'operator' => 'and')));
                        $jsonArea['query']['bool']['filter'][] = $locationFilter;
                    }
                }
            });

            // filter by country
            $countryData = null;
            OrbitInput::get('country', function ($countryFilter) use (&$jsonArea, &$searchFlag, &$countryData) {
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();
                $searchFlag = $searchFlag || TRUE;
                $countryFilterArr = array('match' => array('country.raw' => array('query' => $countryFilter)));;

                $jsonArea['query']['bool']['filter'][] = $countryFilterArr;
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonArea, $countryFilter, &$searchFlag) {
                if (! empty($countryFilter)) {
                    $searchFlag = $searchFlag || TRUE;
                    $cityFilterArr = [];
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.mall.city', '');
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr['bool']['should'][] = array('match' => array('city.raw' => array('query' => $cityFilter)));
                    }

                    if ($shouldMatch != '') {
                        if (count((array) $cityFilters) === 1) {
                            // if user just filter with one city, value of should match must be 100%
                            $shouldMatch = '100%';
                        }
                        $cityFilterArr['bool']['minimum_should_match'] = $shouldMatch;
                    }
                    $jsonArea['query']['bool']['filter'][] = $cityFilterArr;
                }
            });

            // filter by partner_id
            OrbitInput::get('partner_id', function($partnerId) use (&$jsonArea, &$searchFlag, &$withScore)
            {
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $searchFlag = $searchFlag || TRUE;
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'mall');
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
                        $jsonArea['query']['bool']['filter'][] = $partnerFilter;
                    }
                }
            });

            // calculate rating and review based on location/mall
            $scriptFieldRating = "double counter = 0; double rating = 0;";
            $scriptFieldReview = "double review = 0;";
            $scriptFieldFollow = "int follow = 0;";

            if (! empty($cityFilters)) {
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

            $scriptFieldRating = $scriptFieldRating . " if(counter == 0 || rating == 0) {return 0;} else {return rating/counter;}; ";
            $scriptFieldReview = $scriptFieldReview . " if(review == 0) {return 0;} else {return review;}; ";

            $role = $user->role->role_name;
            $objectFollow = [];
            if (strtolower($role) === 'consumer') {
                $objectFollow = $this->getUserFollow($user); // return array of followed mall_id

                if (! empty($objectFollow)) {
                    if ($sort_by === 'followed') {
                        foreach ($objectFollow as $followId) {
                            $scriptFieldFollow = $scriptFieldFollow . " if (doc.containsKey('merchant_id')) { if (! doc['merchant_id'].empty) { if (doc['merchant_id'].value.toLowerCase() == '" . strtolower($followId) . "'){ follow = 1; }}};";
                        }

                        $scriptFieldFollow = $scriptFieldFollow . " if(follow == 0) {return 0;} else {return follow;}; ";
                    }
                }
            }

            $jsonArea['script_fields'] = array('average_rating' => array('script' => $scriptFieldRating), 'total_review' => array('script' => $scriptFieldReview), 'is_follow' => array('script' => $scriptFieldFollow));

            // sort by name or location
            $sort = array('lowercase_name' => array('order' => 'asc'));
            $defaultSort = $sort;
            if ($sort_by === 'location' && $latitude != '' && $longitude != '') {
                $searchFlag = $searchFlag || TRUE;
                $withCache = FALSE;
                $sort = array('_geo_distance' => array('position' => array('lon' => $longitude, 'lat' => $latitude), 'order' => $sort_mode, 'unit' => 'km', 'distance_type' => 'plane'));
            } elseif ($sort_by === 'updated_date') {
                $sort = array('updated_at' => array('order' => $sort_mode));
            } elseif ($sort_by === 'created_date') {
                $sort = array('name.raw' => array('order' => 'asc'));
            } elseif ($sort_by === 'rating') {
                $sort = array('_script' => array('script' => $scriptFieldRating, 'type' => 'number', 'order' => $sort_mode));
            } elseif ($sort_by === 'followed') {
                $sort = array('_script' => array('script' => $scriptFieldFollow, 'type' => 'number', 'order' => 'desc'));
            }

            // put featured mall id in highest priority
            if (OrbitInput::get('by_pass_mall_order', 'n') === 'n') {
                $mallFeaturedIds =  Config::get('orbit.featured.mall_ids.all', []);

                if (! empty($countryFilter)) {
                    $countryFilter = strtolower($countryFilter);
                    $mallFeaturedIds = Config::get('orbit.featured.mall_ids.' . $countryFilter . '.all', []);

                    if (! empty($cityFilters)) {
                        $mallFeaturedIds = [];
                        foreach ($cityFilters as $key => $cityName) {
                            $cityName = str_replace(' ', '_', strtolower($cityName));
                            $cityValue = Config::get('orbit.featured.mall_ids.' . $countryFilter . '.' . $cityName, []);

                            if (! empty($cityValue)) {
                                $mallFeaturedIds = array_merge($cityValue, $mallFeaturedIds);
                            }
                        }
                    }
                }

                $mallFeaturedIds = array_unique($mallFeaturedIds);

                if (! empty($mallFeaturedIds)) {
                    $withScore = TRUE;
                    $esFeaturedBoost = Config::get('orbit.featured.es_boost', 10);
                    $mallOrder = array(array('terms' => array('_id' => $mallFeaturedIds, 'boost' => $esFeaturedBoost)), array('match_all' => new stdClass()));
                    $jsonArea['query']['bool']['should'] = $mallOrder;
                }
            }

            $sortby = array($sort, $defaultSort);
            if ($withScore) {
                $sortby = array('_score', $sort, $defaultSort);
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
            $jsonArea["sort"] = $sortby;

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $param_area = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => json_encode($jsonArea)
            ];

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($jsonArea);
                $response = $recordCache->get($serializedCacheKey, function() use ($client, &$param_area) {
                    return $client->search($param_area);
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $client->search($param_area);
            }

            $area_data = $response['hits'];
            $listmall = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

            $total = $area_data['total'];
            foreach ($area_data['hits'] as $dt) {
                $areadata = array();

                $areadata['average_rating'] = (! empty($dt['fields']['average_rating'][0])) ? number_format(round($dt['fields']['average_rating'][0], 1), 1) : 0;
                $areadata['total_review'] = (! empty($dt['fields']['total_review'][0])) ? round($dt['fields']['total_review'][0], 1) : 0;

                $pageView = 0;
                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $redisKeyGTM = 'mall' . '||' . $dt['_id'] . '||0';
                    $redisKeyMall = 'mall' . '||' . $dt['_id'] . '||' . $dt['_id'];
                    $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                    $redis = Redis::connection($redisConnection);
                    $pageViewGTM = (! empty($redis->get($redisKeyGTM))) ? $redis->get($redisKeyGTM) : 0;
                    $pageViewMall = (! empty($redis->get($redisKeyMall))) ? $redis->get($redisKeyMall) : 0;

                    $pageView = (int) $pageViewGTM + (int) $pageViewMall;
                }

                $followStatus = false;
                if (! empty($objectFollow)) {
                    if (in_array($dt['_id'], $objectFollow)) {
                        // if mall_id is available inside $objectFollow set follow status to true
                        $followStatus = true;
                    }
                }

                if ($words === 1) {
                    // handle if user filter location with one word, ex "jakarta", data in city "jakarta selatan", "jakarta barat" etc will be dissapear
                    if (strtolower($dt['_source']['city']) === strtolower($location)) {
                        $areadata['id'] = $dt['_id'];
                        $localPath = '';
                        $cdnPath = '';

                        foreach ($dt['_source'] as $source => $val) {

                            if (strtolower($dt['_source']['city']) === strtolower($location)) {
                                if ($source == 'logo_url') {
                                    $localPath = $val;
                                }

                                if ($source == 'logo_cdn_url') {
                                    $cdnPath = $val;
                                }

                                $areadata[$source] = $val;
                                if ($pageView != 0) {
                                    $areadata['gtm_page_view'] = $pageView;
                                }

                                $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                            }
                        }

                        $areadata['follow_status'] = $followStatus;
                        $listmall[] = $areadata;
                    }
                    $total = count($listmall);
                } else {
                    $areadata['id'] = $dt['_id'];
                    $localPath = '';
                    $cdnPath = '';

                    foreach ($dt['_source'] as $source => $val) {
                        if ($source == 'logo_url') {
                            $localPath = $val;
                        }

                        if ($source == 'logo_cdn_url') {
                            $cdnPath = $val;
                        }

                        $areadata[$source] = $val;
                        if ($pageView != 0) {
                            $areadata['gtm_page_view'] = $pageView;
                        }

                        $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                    }

                    $areadata['follow_status'] = $followStatus;
                    $listmall[] = $areadata;
                }
            }

            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    $activityNotes = sprintf('Page viewed: Mall list');
                    $activity->setUser($user)
                        ->setActivityName('view_malls_main_page')
                        ->setActivityNameLong('View Malls Main Page')
                        ->setObject(null)
                        ->setModuleName('Mall')
                        ->setNotes($activityNotes)
                        ->setObjectDisplayName($viewType)
                        ->responseOK()
                        ->save();
                }
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $total;
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
     * GET - City list from mall
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallLocationList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'city');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $city = MallCity::select('city')->orderBy($sort_by, $sort_mode);

            OrbitInput::get('country', function($country) use ($city) {
                $city->leftJoin('mall_countries', 'mall_countries.country_id', '=', 'mall_cities.country_id')
                    ->where('mall_countries.country', $country);
            });

            $_city = clone $city;

            $take = PaginationNumber::parseTakeFromGet('mall_location');
            $city->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $city->skip($skip);

            $listcity = $city->get();
            $count = count($_city->get());

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listcity);
            $this->response->data->records = $listcity;
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
     * GET - Country list from mall
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallCountryList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'country');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $countries = MallCountry::select('country')->orderBy($sort_by, $sort_mode);

            $_countries = clone $countries;

            $take = PaginationNumber::parseTakeFromGet('mall_country');
            $countries->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $countries->skip($skip);

            $listcountries = $countries->get();
            $count = count($_countries->get());

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listcountries);
            $this->response->data->records = $listcountries;
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

    // check user follow
    public function getUserFollow($user)
    {
        $follow = FollowStatusChecker::create()
                                    ->setUserId($user->user_id)
                                    ->setObjectType('mall')
                                    ->getFollowStatus();

        return $follow;
    }
}
