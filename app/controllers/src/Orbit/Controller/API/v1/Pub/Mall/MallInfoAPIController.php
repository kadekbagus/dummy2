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
use Orbit\Helper\Util\FollowStatusChecker;
use \Orbit\Helper\Exception\OrbitCustomException;
use DB;

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
        $user = null;
        try {
            $activity = Activity::mobileci()->setActivityType('view');

            $user = $this->getUser();

            $fromMallDetail = OrbitInput::get('from_mall_detail', 'y');

            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $mallId = OrbitInput::get('mall_id', null);
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $prefix = DB::getTablePrefix();
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

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
                                    {
                                        "query": {
                                            "not" : {
                                                "term" : {
                                                    "status" : "deleted"
                                                }
                                            }
                                        }
                                    },
                                ';
            }

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();
            $json_area = '{
                        "from" : ' . $skip . ', "size" : ' . $take . ',
                            "query": {
                                "filtered": {
                                    "filter": {
                                        "and": [
                                            ' . $filterStatus . '
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

            $message = 'Request Ok';
            if ($response['hits']['total'] === 0) {
                throw new OrbitCustomException('Mall that you specify is not found', Mall::NOT_FOUND_ERROR_CODE, NULL);
            }

            if ($response['hits']['total'] > 0 && $response['hits']['hits'][0]['_source']['status'] != 'active') {
                $customData = new \stdClass;
                $customData->type = 'mall';
                throw new OrbitCustomException('Mall is inactive', Mall::INACTIVE_ERROR_CODE, $customData);
            }

            $role = $user->role->role_name;
            $objectFollow = [];
            if (strtolower($role) === 'consumer') {
                $objectFollow = $this->getUserFollow($user, $mallId);
            }

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

                $areadata['follow_status'] = false;
                if (! empty($objectFollow)) {
                    if (in_array($dt['_id'], $objectFollow)) {
                        $areadata['follow_status'] = true;
                    }
                }

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
                        if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                            $locationId = (! empty($mallId)) ? $mallId : 0;
                            $redisKey = 'mall' . '||' . $dt['_id'] . '||' . $locationId;
                            $redisConnection = Config::get('orbit.page_view.redis.connection', '');
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

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
            if ($usingCdn) {
                $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
            }

            $mallData = Mall::with(['mediaPhotos' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.cdn_bucket_name',
                                'media.media_id',
                                'media.media_name_id',
                                'media.media_name_long',
                                'media.object_id',
                                'media.file_name',
                                'media.file_extension',
                                'media.file_size',
                                'media.mime_type',
                                'media.path',
                                'media.metadata',
                                'media.modified_by',
                                'media.created_at',
                                'media.updated_at'
                            );
                    }, 'mediaOtherPhotos' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.cdn_bucket_name',
                                'media.media_id',
                                'media.media_name_id',
                                'media.media_name_long',
                                'media.object_id',
                                'media.file_name',
                                'media.file_extension',
                                'media.file_size',
                                'media.mime_type',
                                'media.path',
                                'media.metadata',
                                'media.modified_by',
                                'media.created_at',
                                'media.updated_at'
                            );
                    }])->where('merchant_id', '=', $mallId)->first();

            // ---- START RATING ----
            $reviewCounter = \Orbit\Helper\MongoDB\Review\ReviewCounter::create(Config::get('database.mongodb'))
                ->setObjectId($mallIds)
                ->setObjectType('mall')
                ->setMall($mall)
                ->request();

            foreach ($listmall as &$itemMall) {
                $itemMall['rating_average'] = $reviewCounter->getAverage();
                $itemMall['review_counter'] = $reviewCounter->getCounter();
                $itemMall['video_id_1'] = $mallData->video_id_1;
                $itemMall['video_id_2'] = $mallData->video_id_2;
                $itemMall['video_id_3'] = $mallData->video_id_3;
                $itemMall['video_id_4'] = $mallData->video_id_4;
                $itemMall['video_id_5'] = $mallData->video_id_5;
                $itemMall['video_id_6'] = $mallData->video_id_6;
                $itemMall['other_photo_section_title'] = $mallData->other_photo_section_title;
                $itemMall['mall_photos'] = $mallData->mediaPhotos;
                $itemMall['mall_other_photos'] = $mallData->mediaOtherPhotos;
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
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }
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

    // check user follow
    public function getUserFollow($user, $objectId)
    {
        $follow = FollowStatusChecker::create()
                                    ->setUserId($user->user_id)
                                    ->setObjectType('mall')
                                    ->setObjectId($objectId)
                                    ->getFollowStatus();

        return $follow;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}