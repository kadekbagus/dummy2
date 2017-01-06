<?php namespace Orbit\Controller\API\v1\Pub\News;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use News;
use NewsMerchant;
use Language;
use Validator;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Elasticsearch\ClientBuilder;

class NewsListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get active news in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     * @author Rio Astamal <rio@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNews()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;
        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'event-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'created_date');
            $sort_mode = OrbitInput::get('sortmode','desc');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $category_id = OrbitInput::get('category_id');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $newsHelper = NewsHelper::create();
            $newsHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sort_by, 'sort_mode' => $sort_mode, 'language' => $language,
                'location' => $location, 'ul' => $ul,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $newsHelper->getValidLanguage();
            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $withScore = false;

            $jsonArea = array("from" => $skip, "size" => $take, "query" => array("filtered" => array("filter" => array("and" => array( array("query" => array("match" => array("status" => "active"))), array("range" => array("begin_date" => array("lte" => "now", "time_zone" => "+07:00"))), array("range" => array("end_date" => array("gte" => "now", "time_zone" => "+07:00"))))))));

            // get user lat and lon
            if ($sort_by == 'location' || $location == 'mylocation') {
                if (! empty($ul)) {
                    $position = explode("|", $ul);
                    $lon = $position[0];
                    $lat = $position[1];
                } else {
                    // get lon lat from cookie
                    $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
                    if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                        $lon = $userLocationCookieArray[0];
                        $lat = $userLocationCookieArray[1];
                    }
                }
            }

            OrbitInput::get('keyword', function($keyword) use (&$jsonArea, &$searchFlag, &$withScore)
            {
                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withScore = true;
                    $filterKeyword = array("multi_match" => array("query" => $keyword, "fields" => array("translation.name^6", "object_type^5", "keywords^4", "translation.description^3", "link_to_tenant.city^2", "link_to_tenant.province^2", "link_to_tenant.country^1")));
                    $jsonArea['query']['filtered']['query'] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallid) use ($jsonArea) {
                if (! empty($mallId)) {
                    $withMallId = array("nested" => array("path" => "link_to_tenant", "query" => array("filtered" => array("filter" => array("match" => array("link_to_tenant.parent_id" => $mallId))))));
                    $jsonArea['query']['filtered']['filter']['and'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonArea, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter["or"][] = array("match" => array("category_ids" => $value));
                }
                $jsonArea['query']['filtered']['filter']['and'][] = $categoryFilter;
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$jsonArea, $prefix, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $searchFlag = $searchFlag || TRUE;
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'news');
                                                            })
                                                            ->where('partner_id', $partnerId)
                                                            ->first();

                    if (is_object($partnerAffected)) {
                        $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);
                        $partnerFilter = array("query" => array("match" => array("partner_ids" => $partnerId)));

                        if (in_array($partnerId, $exception)) {
                            $partnerException = PartnerCompetitor::where("partner_id", $partnerId)->lists("competitor_id");
                            $partnerIds = implode('", "', $partnerException);
                            $partnerFilter = array("query" => array("not" => array("terms" => array("partner_ids" => $partnerIds))));
                        }
                        $jsonArea['query']['filtered']['filter']['and'][] = $partnerFilter;
                    }
                }
            });

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$jsonArea, &$searchFlag, &$withScore, $lat, $lon, $distance)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;

                    if ($location === "mylocation" && $lat != '' && $lon != '') {
                        $locationFilter = array("nested" => array("path" => "link_to_tenant", "query" => array("filtered" => array("filter" => array("geo_distance" => array("distance" => $distance."km", "link_to_tenant.position" => array("lon" => $lon, "lat" => $lat)))))));
                        $jsonArea['query']['filtered']['filter']['and'][] = $locationFilter;
                    } elseif ($location !== "mylocation") {
                        $locationFilter = array("nested" => array("path" => "link_to_tenant", "query" => array("filtered" => array("filter" => array("match" => array("link_to_tenant.city.raw" => $location))))));
                        $jsonArea['query']['filtered']['filter']['and'][] = $locationFilter;
                    }
                }
            });

            // sort by name or location
            if ($sort_by === 'location' && $lat != '' && $lon != '') {
                $searchFlag = $searchFlag || TRUE;
                $sort = array("_geo_distance" => array("link_to_tenant.position" => array("lon" => $lon, "lat" => $lat), "order" => $sort_mode, "unit" => "km", "distance_type" => "plane"));
            } elseif ($sort_by === 'created_date') {
                $sort = array("begin_date" => array("order" => $sort_mode));
            } else {
                $sort = array("name.raw" => array("order" => $sort_mode));
            }

            $sortby = $sort;
            if ($withScore) {
                $sortby = array("_score", $sort);
            }
            $jsonArea["sort"] = $sortby;

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $param_area = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.news.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.news.type'),
                'body' => json_encode($jsonArea)
            ];

            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
            $response = $recordCache->get($serializedCacheKey, function() use ($client, &$param_area) {
                return $client->search($param_area);
            });
            $recordCache->put($serializedCacheKey, $response);

            $records = $response['hits'];

            $listOfRec = array();
            foreach ($records['hits'] as $record) {
                $data = array();
                foreach ($record['_source'] as $key => $value) {
                    if ($key === "name") {
                        $key = "news_name";
                    }
                    $data[$key] = $value;

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        foreach ($record['_source']['translation'] as $dt) {
                            if ($dt['language_id'] === $valid_language->language_id) {
                                // name & desc
                                if (! empty($dt['name'])) {
                                    $data['news_name'] = $dt['name'];
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url'])) {
                                    $data['image_url'] = $dt['image_url'];
                                }
                            } else {
                                // name & desc
                                if (! empty($dt['name']) && empty($data['news_name'])) {
                                    $data['news_name'] = $dt['name'];
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url']) && empty($data['image_url'])) {
                                    $data['image_url'] = $dt['image_url'];
                                }
                            }
                        }
                    }
                }
                $listOfRec[] = $data;
            }

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'News',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name'),
                    'partner' => OrbitInput::get('partner_id', NULL)
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
            }

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listOfRec;


            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall event list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_event_list')
                            ->setActivityNameLong('View mall event list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('News')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: News list');
                        $activity->setUser($user)
                            ->setActivityName('view_news_main_page')
                            ->setActivityNameLong('View News Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('News')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    }
                }
            }

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

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

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}