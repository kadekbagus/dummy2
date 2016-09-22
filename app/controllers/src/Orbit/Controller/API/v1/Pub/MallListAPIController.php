<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
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
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;

class MallListAPIController extends ControllerAPI
{
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
            $keyword = OrbitInput::get('keyword');
            $location = OrbitInput::get('location', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', null);
            $sort_mode = OrbitInput::get('sortmode','asc');
            $ul = OrbitInput::get('ul', null);
            $radius = Config::get('orbit.geo_location.distance', 10);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $latitude = '';
            $longitude = '';
            $locationFilter = '';

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $filterStatus = '';
            if ($usingDemo) {
                $filterStatus = '
                    "not" : {
                        "term" : {
                            "status" : "deleted"
                        }
                    }';
            } else {
                // Production
                $filterStatus = '
                    "match" : {
                        "status" : "active"
                    }';
            }

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
            $filterKeyword = '';
            if ($keyword != '') {
                $filterKeyword = '"query": {
                                    "multi_match" : {
                                        "query": "' . $keyword . '",
                                        "fields": [
                                            "name",
                                            "address_line",
                                            "description"
                                        ]
                                    }
                                  },';
            }

            // filter by location (city or user location)
            if (! empty($location)) {
                $locationFilter = '{ 
                            "match":{
                                "city": "' . $location . '"
                            }
                        },';

                if ($location === "mylocation" && $latitude != '' && $longitude != '') {
                    $locationFilter = '{
                                "geo_distance" : {
                                    "distance" : "' . $radius . 'km",
                                    "position" : {
                                        "lon": ' . $longitude . ',
                                        "lat": ' . $latitude . '
                                    }
                                }
                            },';
                }
            }

            // sort by name or location
            $sortby = '{"name.raw" : {"order" : "' . $sort_mode . '"}}';
            if($sort_by === 'location' && $latitude != '' && $longitude != '') {
                $sortby = ' {
                                "_geo_distance": {
                                    "position": {
                                        "lon": ' . $longitude . ',
                                        "lat": ' . $latitude . '
                                    },
                                    "order": "' . $sort_mode . '",
                                    "unit": "km",
                                    "distance_type": "plane"
                                }
                            }';
                
            }

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();
            $json_area = '{
                        "from" : ' . $skip . ', "size" : ' . $take . ',
                            "query": {
                                "filtered": {
                                    ' . $filterKeyword . '
                                    "filter": {
                                        "and": [
                                            ' . $locationFilter . '
                                            {
                                                "query": {
                                                    ' . $filterStatus . '
                                                }
                                            }
                                        ]
                                    }
                                }
                            },
                            "sort": [
                                ' . $sortby . '
                            ]
                        }';

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $param_area = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => $json_area
            ];

            $response = $client->search($param_area);

            $area_data = $response['hits'];
            $listmall = array();
            foreach ($area_data['hits'] as $dt) {
                $areadata['id'] = $dt['_id'];
                foreach ($dt['_source'] as $source => $val) {
                    $areadata[$source] = $val;
                }
                $listmall[] = $areadata;
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $area_data['total'];
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
    public function getCityMallList()
    {
        $httpCode = 200;
        try {
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $sort_by = OrbitInput::get('sortby', 'city');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $city = Mall::select('city')->groupBy('city')->orderBy($sort_by, $sort_mode);

            if ($usingDemo) {
                $city = $city->excludeDeleted();
            } else {
                $city = $city->active();
            }

            $_city = clone $city;

            $take = PaginationNumber::parseTakeFromGet('retailer');
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
}