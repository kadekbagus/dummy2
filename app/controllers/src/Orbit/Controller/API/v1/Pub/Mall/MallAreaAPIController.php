<?php namespace Orbit\Controller\API\v1\Pub\Mall;
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
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;

class MallAreaAPIController extends PubControllerAPI
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
    public function getMallArea()
    {
        $httpCode = 200;
        try {
            $latitude = OrbitInput::get('latitude',null);
            $longitude = OrbitInput::get('longitude',null);

            $area = OrbitInput::get('area', null);
            $keyword = OrbitInput::get('keyword_search');

            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');

            $sort_by = OrbitInput::get('sortby',null);
            $sort_mode = OrbitInput::get('sortmode','asc');

            $subscribed = OrbitInput::get('subscribed','Y');

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $getArea = explode(", ", $area);
            $topLeft = explode(" ", $getArea[1]);
            $bottomRight = explode(" ", $getArea[3]);

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

            // Filter
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

            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $skip = PaginationNumber::parseSkipFromGet();

            switch ($sort_by) {
                case 'name':
                    $sortby = '{"name.raw" : {"order" : "' . $sort_mode . '"}}';
                    break;

                default:
                    $sortby = '{"_score" : {"order" : "' . $sort_mode . '"}},
                        {
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
                    break;
            }

            $json_area = '{
                        "from" : ' . $skip . ', "size" : ' . $take . ',
                            "query": {
                                "filtered": {
                                    ' . $filterKeyword . '
                                    "filter": {
                                        "and": [
                                            {
                                                "query": {
                                                    ' . $filterStatus . '
                                                }
                                            },
                                            {
                                                "query": {
                                                    "match" : {
                                                        "is_subscribed" : "' . $subscribed . '"
                                                    }
                                                }
                                            },
                                            {
                                                "geo_bounding_box": {
                                                    "type": "indexed",
                                                    "position": {
                                                        "top_left": {
                                                            "lat": ' . $topLeft[0] . ',
                                                            "lon": ' . $topLeft[1] . '
                                                        },
                                                        "bottom_right": {
                                                            "lat": ' . $bottomRight[0] . ',
                                                            "lon": ' . $bottomRight[1] . '
                                                        }
                                                    }
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
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($area_data['hits'] as $dt) {
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
