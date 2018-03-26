<?php 

namespace Orbit\Controller\API\v1\Pub\Mall;

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
use Tenant;
use Advert;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Redis;
use BaseStore;
use DB;
use Validator;
use Language;
use Activity;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Lang;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Orbit\Controller\API\v1\Pub\Store\StoreHelper;
use Country;
use Orbit\Helper\Util\FollowStatusChecker;

use MallSearch;

/**
* @todo  add search result mapper class.
* @todo  the location filter?
*/
class MallListNewAPIController extends PubControllerAPI
{

    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     *
     * 
     * @return [type] [description]
     */
    public function getMallList()
    {
        $httpCode = 200;
        try {
            $activity = Activity::mobileci()->setActivityType('view');

            $this->checkAuth();
            $user = $this->api->user;

            // Cache result of all possible calls to backend storage
            $cacheConfig = Config::get('orbit.cache.context');
            $cacheContext = 'mall-list';
            $recordCache = SimpleCache::create($cacheConfig, $cacheContext);

            $keyword = OrbitInput::get('keyword');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', []);
            $countryFilter = OrbitInput::get('country', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', null);
            $sortBy = OrbitInput::get('sortby', 'name');
            $partner_id = OrbitInput::get('partner_id', null);
            $sort_mode = OrbitInput::get('sortmode','asc');
            $sortMode = OrbitInput::get('sortmode','asc');
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $radius = Config::get('orbit.geo_location.distance', 10);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $viewType = OrbitInput::get('view_type', 'grid');
            $list_type = OrbitInput::get('list_type', 'preferred');
            $withAdvert = (bool) OrbitInput::get('with_advert', true);
            $latitude = '';
            $longitude = '';
            $locationFilter = '';
            $withCache = TRUE;
            $words = 0;

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $prefix = DB::getTablePrefix();

            // Get ES config only once, avoid calling Config::get() everytime. :)
            $esConfig = Config::get('orbit.elasticsearch');

            // Create the search...
            $esStoreIndex = $esConfig['indices_prefix'] . $esConfig['indices']['malldata']['index'];
            $mallSearch = new MallSearch($esConfig);

            $mallSearch->setPaginationParams(['from' => $skip, 'size' => $take]);

            $mallSearch->filterBase();

            $countryData = null;
            $area = ['country' => '', 'cities' => $cityFilters];

            if ($countryFilter != '0') {
                $area['country'] = $countryFilter;
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();
            }

            // Otherwise, we filter based on user's selection of country 
            // and/or cities
            $mallSearch->filterByCountryAndCities($area);

            $keyword = OrbitInput::get('keyword', null);
            if (! empty($keyword)) {
                $mallSearch->filterByKeyword($keyword);
            }

            // Filter by partner
            // Check for competitor first.
            OrbitInput::get('partner_id', function($partnerId) use (&$searchFlag, &$cacheKey, &$mallSearch) {
                $cacheKey['partner_id'] = $partnerId;
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

                        if (in_array($partnerId, $exception)) {
                            $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');
                            
                            $mallSearch->excludePartnerCompetitors($partnerIds);
                        }
                        else {
                            $mallSearch->filterByPartner($partnerId);
                        }
                    }
                }
            });

            // Get Advertised Malls...
            if ($withAdvert) {
                $locationId = 0;
                $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_regular', 'preferred_list_large'] : ['preferred_list_regular', 'preferred_list_large'];

                $mallSearch->filterWithAdvert(compact('dateTimeEs', 'advertType', 'locationId', 'list_type'));
            }

            $scriptFields = $mallSearch->addReviewFollowScript(compact(
                'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
            ));

            // Force to sort result by relevance if any keyword is set.
            if (! empty($keyword)) {
                $sortBy = 'relevance';
            }

            // Next sorting based on Visitor's selection.
            switch ($sortBy) {
                case 'relevance':
                    $mallSearch->sortByRelevance();
                    break;
                case 'updated_at':
                    $mallSearch->sortByUpdatedAt();
                    break;
                case 'rating':
                    $mallSearch->sortByRating($scriptFields['scriptFieldRating']);
                    break;
                case 'followed':
                    $mallSearch->sortByFavorite($scriptFields['scriptFieldFollow']);
                    break;
                default:
                    $mallSearch->sortByName();
                    break;
            }

            // Add any constant scoring to search body if set.
            $mallSearch->addConstantScoringToQuery();

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() use ($mallSearch) {
                    return $mallSearch->getResult();
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $mallSearch->getResult();
            }

            $area_data = $response['hits'];
            $listmall = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            $total = $area_data['total'];
            foreach ($area_data['hits'] as $dt) {
                $areadata = array();

                $areadata['placement_type'] = null;
                $areadata['placement_type_orig'] = null;
                $areadata['average_rating'] = (! empty($dt['fields']['average_rating'][0])) ? number_format(round($dt['fields']['average_rating'][0], 1), 1) : 0;
                $areadata['total_review'] = (! empty($dt['fields']['total_review'][0])) ? round($dt['fields']['total_review'][0], 1) : 0;

                $pageView = 0;
                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $redisKeyGTM = 'mall' . '||' . $dt['_id'] . '||0';
                    $redisKeyMall = 'mall' . '||' . $dt['_id'] . '||' . $dt['_id'];
                    $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                    $redis = Redis::connection($redisConnection);
                    $pageViewGTM = (! empty($redis->get($redisKeyGTM))) ? $redis->get($redisKeyGTM) : 0;
                    $pageViewMall = (! empty($redis->get($redisKeyMall))) ? $redis->get($redisKeyMall) : 0;

                    $pageView = (int) $pageViewGTM + (int) $pageViewMall;
                }

                $followStatus = false;
                if (! empty($objectFollow)) {
                    if (in_array($dt['_id'], $objectFollow)) {
                        // if mall_id is available inside $objectFollow set follow status to true
                        $followStatus = true;
                    }
                }

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
                                if ($pageView != 0) {
                                    $areadata['gtm_page_view'] = $pageView;
                                }

                                $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                            }
                        }

                        $areadata['follow_status'] = $followStatus;
                        $listmall[] = $areadata;
                    }
                    $total = count($listmall);
                } else {
                    $areadata['id'] = $dt['_id'];
                    $localPath = '';
                    $cdnPath = '';

                    foreach ($dt['_source'] as $source => $val) {

                        // advert type
                        if ($source === 'advert_type') {
                            if ($val === 'featured_list') {
                                $areadata['is_featured'] = true;
                            }
                            $areadata['placement_type'] = $val;
                            $areadata['placement_type_orig'] = $val;
                        }

                        if ($source == 'logo_url') {
                            $localPath = $val;
                        }

                        if ($source == 'logo_cdn_url') {
                            $cdnPath = $val;
                        }

                        $areadata[$source] = $val;
                        if ($pageView != 0) {
                            $areadata['gtm_page_view'] = $pageView;
                        }

                        $areadata['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                    }

                    $areadata['follow_status'] = $followStatus;
                    $listmall[] = $areadata;
                }
            }

            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    $activityNotes = sprintf('Page viewed: Mall list');
                    $activity->setUser($user)
                        ->setActivityName('view_malls_main_page')
                        ->setActivityNameLong('View Malls Main Page')
                        ->setObject(null)
                        ->setModuleName('Mall')
                        ->setNotes($activityNotes)
                        ->setObjectDisplayName($viewType)
                        ->responseOK()
                        ->save();
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
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }
}
