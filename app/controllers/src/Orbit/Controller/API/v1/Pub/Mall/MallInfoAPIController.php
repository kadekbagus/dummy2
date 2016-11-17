<?php namespace Orbit\Controller\API\v1\Pub\Mall;
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
use Activity;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;

class MallInfoAPIController extends ControllerAPI
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
    public function getMallInfo()
    {
        $httpCode = 200;
        try {
            $activity = Activity::mobileci()->setActivityType('view');

            $this->checkAuth();
            $user = $this->api->user;

            $from_mall_ci = OrbitInput::get('from_mall_ci', 'y');

            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $mallId = OrbitInput::get('mall_id', null);

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

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

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();
            $json_area = '{
                        "from" : ' . $skip . ', "size" : ' . $take . ',
                            "query": {
                                "filtered": {
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
                                                        "_id" : "' . $mallId . '"
                                                    }
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
                                {"name.raw" : {"order" : "asc"}}
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

            if ($from_mall_ci === 'y') {
                $activityNotes = sprintf('Page viewed: View mall page');
                $activity->setUser($user)
                    ->setActivityName('view_mall')
                    ->setActivityNameLong('View mall page')
                    ->setObject(null)
                    ->setLocation($mall)
                    ->setModuleName('Mall')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: View sidebar mall info');
                $activity->setUser($user)
                    ->setActivityName('view_sidebar_mall_info')
                    ->setActivityNameLong('View sidebar mall info')
                    ->setObject(null)
                    ->setLocation($mall)
                    ->setModuleName('Mall')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
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