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
use Activity;
use stdClass;
use Redis;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Util\CdnUrlGenerator;

class MallInfoAPIController extends PubControllerAPI
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

            $user = $this->getUser();

            $fromMallDetail = OrbitInput::get('from_mall_detail', 'y');

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
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $total = $area_data['total'];
            $mallIds = [];
            foreach ($area_data['hits'] as $dt) {
                $areadata = array();
                $areadata['id'] = $dt['_id'];
                $localPath = '';
                $cdnPath = '';

                foreach ($dt['_source'] as $source => $val) {
                    if ($source === 'logo_url') {
                        $localPath = $val;
                    }

                    if ($source === 'logo_cdn_url') {
                        $cdnPath = $val;
                    }

                    if ($source === 'maps_url') {
                        array_walk($val, function (&$value, $key) use ($urlPrefix) {
                           $value = $urlPrefix . $value;
                        });

                        $areadata['maps_url'] = $val;
                    }

                    if ($source === 'gtm_page_views') {
                        if (Config::get('page_view.source', 'mysql') === 'redis') {
                            $locationId = (! empty($mallId)) ? $mallId : 0;
                            $redisKey = 'mall' . '-' . $dt['_id'] . '-' . $locationId;
                            $redisConnection = Config::get('page_view.redis.connection', '');
                            $redis = Redis::connection($redisConnection);
                            $areadata['total_view'] = (! empty($redis->get($redisKey))) ? $redis->get($redisKey) : 0;
                        } else {
                            $areadata['total_view'] = $val;
                        }
                    }

                    $areadata[$source] = $val;
                    $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);

                    if ($usingCdn && $source === 'maps_cdn_url' && (! empty($val))) {
                        $areadata['maps_url'] = $val;
                    }
                }

                $listmall[] = $areadata;
                $mallIds[] = $areadata['id'];
            }

            // ---- START RATING ----
            $reviewCounter = \Orbit\Helper\MongoDB\Review\ReviewCounter::create(Config::get('database.mongodb'))
                ->setObjectId($mallIds)
                ->setObjectType('mall')
                ->setMall($mall)
                ->request();

            foreach ($listmall as &$itemMall) {
                $itemMall['rating_average'] = $reviewCounter->getAverage();
                $itemMall['review_counter'] = $reviewCounter->getCounter();
            }
            // ---- END OF RATING ----

            if ($fromMallDetail === 'y') {
                $activityNotes = sprintf('Page viewed: Mall Detail Page');
                $activity->setUser($user)
                    ->setActivityName('view_mall')
                    ->setActivityNameLong('View Mall Page')
                    ->setObject($mall)
                    ->setLocation($mall)
                    ->setModuleName('Mall')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: Mall Info Page');
                $activity->setUser($user)
                    ->setActivityName('view_mall_info')
                    ->setActivityNameLong('View Mall Info')
                    ->setObject($mall)
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