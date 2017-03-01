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
use Mall;
use PartnerAffectedGroup;
use PartnerCompetitor;
use stdClass;
use DB;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use MallCountry;
use MallCity;

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
            $this->checkAuth();
            $user = $this->api->user;

            // Cache result of all possible calls to backend storage
            $cacheConfig = Config::get('orbit.cache.context');
            $cacheContext = 'mall-list';
            $recordCache = SimpleCache::create($cacheConfig, $cacheContext);

            $keyword = OrbitInput::get('keyword');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', null);
            $partner_id = OrbitInput::get('partner_id', null);
            $sort_mode = OrbitInput::get('sortmode','asc');
            $ul = OrbitInput::get('ul', null);
            $radius = Config::get('orbit.geo_location.distance', 10);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
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

            $jsonArea = array('from' => $skip, 'size' => $take, 'query' => array('bool' => array('must' => array( array('query' => array('match' => array('is_subscribed' => 'Y')))))));

            $filterStatus = array('query' => array('match' => array('status' => 'active')));
            if ($usingDemo) {
                $filterStatus = array('query' => array('not' => array('term' => array('status' => 'deleted'))));
            }
            $jsonArea['query']['bool']['must'][] = $filterStatus;

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
                    $jsonArea['query']['bool']['must'][] = $filterKeyword;
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
                        $jsonArea['query']['bool']['must'][] = $locationFilter;
                    } elseif ($location !== 'mylocation') {
                        $locationFilter = array('match' => array('city' => array('query' => $location, 'operator' => 'and')));
                        $jsonArea['query']['bool']['must'][] = $locationFilter;
                    }
                }
            });

            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonArea, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $countryFilterArr = array('match' => array('country.raw' => array('query' => $countryFilter)));;

                $jsonArea['query']['bool']['must'][] = $countryFilterArr;
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonArea, $countryFilter, &$searchFlag) {
                if (! empty($countryFilter)) {
                    $searchFlag = $searchFlag || TRUE;
                    $cityFilterArr = [];
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.mall.city', '');
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr['bool']['should'][] = array('match' => array('city.raw' => array('query' => $cityFilter)));;
                    }

                    if ($shouldMatch != '') {
                        if (count((array) $cityFilters) === 1) {
                            // if user just filter with one city, value of should match must be 100%
                            $shouldMatch = '100%';
                        }
                        $cityFilterArr['bool']['minimum_should_match'] = $shouldMatch;
                    }
                    $jsonArea['query']['bool']['must'][] = $cityFilterArr;
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
                        $jsonArea['query']['bool']['must'][] = $partnerFilter;
                    }
                }
            });

            // sort by name or location
            $sort = array('name.raw' => array('order' => 'asc'));
            if ($sort_by === 'location' && $latitude != '' && $longitude != '') {
                $searchFlag = $searchFlag || TRUE;
                $withCache = FALSE;
                $sort = array('_geo_distance' => array('position' => array('lon' => $longitude, 'lat' => $latitude), 'order' => $sort_mode, 'unit' => 'km', 'distance_type' => 'plane'));
            } elseif ($sort_by === 'updated_date') {
                $sort = array('updated_at' => array('order' => 'desc'));
            }

            if (! $searchFlag) {
                $mallConfig =  Config::get('orbit.featured.mall_ids', null);
                if (! empty($mallConfig)) {
                    $withScore = true;
                    $filterKeyword = array('bool' => array('should' => array(array('terms' => array('_id' => $mallConfig)), array('match_all' => new stdClass()))));
                    $jsonArea['query'] = $filterKeyword;
                }
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
            $jsonArea["sort"] = $sortby;

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $param_area = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => json_encode($jsonArea)
            ];

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Mall',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => NULL,
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name'),
                    'partner' => OrbitInput::get('partner_id', NULL)
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
            }

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
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            $total = $area_data['total'];
            foreach ($area_data['hits'] as $dt) {
                $areadata = array();
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
                                $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                            }
                        }

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
                        $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                    }

                    $listmall[] = $areadata;
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
}