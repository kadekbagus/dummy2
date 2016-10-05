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

class MallFenceAPIController extends ControllerAPI
{
    /**
     * GET - check if user inside mall area
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallFence()
    {
        $httpCode = 200;
        try {
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $latitude = '';
            $longitude = '';

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

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();
            $sortby = '{"name.raw" : {"order" : "' . $sort_mode . '"}}';
            $json_area = '{
                        "from" : ' . $skip . ', "size" : ' . $take . ',
                            "query": {
                                "filtered": {
                                    "filter": {
                                        "and": [
                                            {
                                                "geo_shape": {
                                                  "area": {
                                                    "relation": "intersects",
                                                    "shape": {
                                                      "coordinates": [
                                                        ' . $longitude . ', ' . $latitude . '
                                                      ],
                                                      "type": "point"
                                                    }
                                                  }
                                                }
                                            },
                                            {
                                                "query": {
                                                    ' . $filterStatus . '
                                                }
                                            },
                                            {
                                                "query": {
                                                    "match" : {
                                                        "is_subscribed" : "Y"
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
            $total = $area_data['total'];
            foreach ($area_data['hits'] as $dt) {
                $areadata = array();
                $areadata['id'] = $dt['_id'];
                foreach ($dt['_source'] as $source => $val) {
                    $areadata[$source] = $val;
                }
                $listmall[] = $areadata;
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
}