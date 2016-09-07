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
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Util\PaginationNumber;

class MallNearestAPIController extends ControllerAPI
{
    /**
     * GET - Nearest Mall
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     * @param string width_ratio
     * @param string height_ratio
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMallNearest()
    {
        $httpCode = 200;
        try {
            $lat = OrbitInput::get('latitude', null);
            $long = OrbitInput::get('longitude', null);
            $width_ratio = OrbitInput::get('width_ratio', 1);
            $height_ratio = OrbitInput::get('height_ratio', 1);

            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $filterStatus = '';
            if ($usingDemo) {
                $filterStatus = '"filter" : {
                    "not" : {
                        "term" : {
                            "status" : "deleted"
                        }
                    }
                }';
            } else {
                // Production
                $filterStatus = '"query": {
                    "match" : {
                        "status" : "active"
                    }
                }';
            }

            $json_nearest = '{
                    ' . $filterStatus . ',
                    "sort": [
                       {
                      "_geo_distance": {
                        "position": { 
                          "lat": ' . $lat . ', 
                          "lon": ' . $long . '
                        },
                        "order":         "asc",
                        "unit":          "km", 
                        "distance_type": "plane" 
                      }
                    }
                    ]
                }';

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $param_nearest = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type'  => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => $json_nearest
            ];

            $response = $client->search($param_nearest);
            $nearest = $response['hits']['hits'][0]['_source']['position'];
            
            $maxtry = Config::get('orbit.elasticsearch.nearest.max_try', 10);
            $multiple = Config::get('orbit.elasticsearch.nearest.multiple', 5);

            for ($i=0; $i<$maxtry; $i++) { 

                $area = array();
                $topLeft = array();
                $bottomRight = array();
                $cy = $nearest['lat'];
                $cx = $nearest['lon'];
                $width = $width_ratio;
                $height = $height_ratio;
                $radius = $multiple * 1000;
                $dy = $dx = $radius * 2;
                $rad_lat = ($cy * M_PI / 180);

                if ($height < $width) {
                    $dx = ($dy * $width) / $height;
                } elseif ($width < $height) {
                    $dy = ($dx * $height) / $width;
                }

                $topLeftLat = ($dy / 2) / 110540;
                $topLeftLng = -($dx / 2) / (111320 * cos($rad_lat));

                $topLeftLat = $cy + $topLeftLat;
                $topLeftLng = $cx + $topLeftLng;

                $bottomRightLat = -($dy / 2) / 110540;
                $bottomRightLng = ($dx / 2) / (111320 * cos($rad_lat));

                $bottomRightLat = $cy + $bottomRightLat;
                $bottomRightLng = $cx + $bottomRightLng;

                $topLeft = [$topLeftLat, $topLeftLng];
                $bottomRight = [$bottomRightLat, $bottomRightLng];

                $json_area = '{
                              "query": {
                                "filtered": {
                                  "filter": {
                                    "geo_bounding_box": {
                                      "type":       "indexed",
                                      "position": {
                                        "top_left": {
                                          "lat": ' . $topLeftLat . ',
                                          "lon": ' . $topLeftLng . '
                                        },
                                        "bottom_right": {
                                          "lat": ' . $bottomRightLat . ',
                                          "lon": ' . $bottomRightLng . '
                                        }
                                      }
                                    }
                                  }
                                }
                              },
                              "sort": [
                               {
                              "_geo_distance": {
                                "position": { 
                                  "lat": ' . $cy . ', 
                                  "lon": ' . $cx . '
                                },
                                "order":         "asc",
                                "unit":          "km", 
                                "distance_type": "plane" 
                              }
                            }
                            ]
                          }';
                
                $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
                $param_area = [
                    'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                    'type'   => Config::get('orbit.elasticsearch.indices.malldata.type'),
                    'body' => $json_area
                ];

                $response = $client->search($param_area);
                $area = $response['hits']['hits'];

                if (! empty($area)){
                    break;
                }

                $multiple += $multiple;
            }
            
            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $skip = PaginationNumber::parseSkipFromGet();
            
            $area_data = $response['hits'];
            $listmall = array();
            $loop = 0;
            $loopfirst = 0;
            $loopinside = 1;
            foreach ($area_data['hits'] as $dt) {
                // first data is mall nearest - center point, so it's cannot take/skip
                if ($loop == 0) {
                    $areadata = array();
                    $areadata['id'] = $dt['_id'];
                    foreach ($dt['_source'] as $source => $val) {
                        $areadata[$source] = $val;
                    }
                    $listmall[] = $areadata;
                } else {
                    if ($loop >= $skip) {
                        $areadata['id'] = $dt['_id'];
                        foreach ($dt['_source'] as $source => $val) {
                            $areadata[$source] = $val;
                        }
                        $listmall[] = $areadata;
                        if ($loopinside == $take) {
                            break;
                        }
                        $loopinside += 1;
                    }
                }
                $loop += 1;
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $area_data['total'];
            $this->response->data->returned_records = count($listmall);
            $this->response->data->centre_point = $nearest;
            $this->response->data->map_area = array("top_left" => $topLeft, "bottom_right" => $bottomRight);
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