<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
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
use stdClass;
use Orbit\Helper\Util\PaginationNumber;

use Elasticsearch\ClientBuilder;

class MallNearbyAPIController extends ControllerAPI
{
    /**
     * GET - Search mall by location
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     * @param string distance
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMallNearby()
    {
        $httpCode = 200;
        try {
            $lat = OrbitInput::get('latitude', null);
            $long = OrbitInput::get('longitude', null);
            $distance = OrbitInput::get('distance');

            if (empty($distance)) {
                $distance = Config::get('orbit.geo_location.distance', 10);
            }

            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $malls = Mall::select('merchants.*')
                         ->includeLatLong()
                         ->join('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id');

            if ($usingDemo) {
                $malls->excludeDeleted();
            } else {
                // Production
                $malls->active();
            }

            $callNearBy = TRUE;

            // Filter
            OrbitInput::get('keyword_search', function ($keyword) use ($malls, $callNearBy) {
                $mainKeyword = explode(" ", $keyword);

                $malls->where(function($q) use ($mainKeyword) {
                    foreach ($mainKeyword as $key => $value) {
                        $q->orWhere(function($r) use ($value) {
                            $r->where('merchants.name', 'like', "%$value%")
                                ->orWhere('merchants.city', 'like', "%$value%");
                        });
                    }
                });

                // Keyword does not need the distance we make it false
                $callNearBy = FALSE;
            });

            if ($callNearBy) {
                $malls->nearBy($lat, $long, $distance);
            }

            // Filter by mall_id
            OrbitInput::get('mall_id', function ($mallid) use ($malls) {
                $malls->where('merchants.merchant_id', $mallid);
            });

            $_malls = clone $malls;

            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $malls->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $malls->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            if ((int) $distance !== -1) {
                // Default sort by
                $sortBy = 'distance';
                // Default sort mode
                $sortMode = 'asc';
            }

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'mall_name'         => 'merchants.name',
                    'city'              => 'merchants.city',
                    'created_at'        => 'merchants.created_at',
                    'updated_at'        => 'merchants.updated_at',
                    'distance'          => 'distance'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $malls->orderBy($sortBy, $sortMode);

            $listmalls = $malls->get();
            $count = RecordCounter::create($_malls)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmalls);
            $this->response->data->records = $listmalls;
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
     * GET - Search mall keyword by Elasticsearch
     *
     * Priority : name, city, country, position, address_line, description
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     * @param string distance
     * @param string keyword_search
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMallKeyword()
    {
        $httpCode = 200;
        try {

            $latitude = OrbitInput::get('latitude',null);
            $longitude = OrbitInput::get('longitude',null);
            $distance = OrbitInput::get('distance',null);
            $keywordSearch = OrbitInput::get('keyword_search',null);

            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $skip = PaginationNumber::parseSkipFromGet();

            $json_search = '{
                                "from" : ' . $skip . ', "size" : ' . $take . ',
                                "query": {
                                "multi_match": {
                                    "query": "' . $keywordSearch . '",
                                    "fields": [
                                        "name^8",
                                        "city^4",
                                        "country^4",
                                        "position^2",
                                        "address_line^3",
                                        "description^3"
                                    ]
                                }
                              },
                                "sort": [
                                    "_score",
                                    {
                                        "_geo_distance": {
                                            "position": {
                                                "lon": ' . $longitude . ',
                                                "lat": ' . $latitude . '
                                            },
                                            "order": "asc",
                                            "unit": "km",
                                            "distance_type": "plane"
                                        }
                                    }
                                ]
                            }';


            $param_nearest = [
                'index'  => Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => $json_search
            ];
            $response = $client->search($param_nearest);

            $area_data = $response['hits'];

            $listmall = array();

            // Reformat return data
            foreach ($area_data['hits'] as $key => $dt) {
                $areadata = array();
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



}