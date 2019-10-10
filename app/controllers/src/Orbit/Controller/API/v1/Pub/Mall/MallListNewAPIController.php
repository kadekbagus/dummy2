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
     * Enable / disable scroll function on ES
     */
    protected $useScroll = FALSE;

    /**
     * Scroll duration when $useScroll is TRUE
     */
    protected $scrollDuration = '20s';

    /**
     * Searcher
     */
    protected $searcher = null;

    /**
     * ES Config
     */
    protected $esConfig = [];

    public function __construct()
    {
        parent::__construct();
        $this->esConfig = Config::get('orbit.elasticsearch');
        $this->searcher = new MallSearch($this->esConfig);
    }

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
            $sortBy = OrbitInput::get('sortby', 'name');
            $sortMode = OrbitInput::get('sortmode','asc');
            $partner_id = OrbitInput::get('partner_id', null);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $radius = Config::get('orbit.geo_location.distance', 10);
            $viewType = OrbitInput::get('view_type', 'grid');
            $list_type = OrbitInput::get('list_type', 'preferred');
            $withAdvert = (bool) OrbitInput::get('with_advert', true);
            $ratingLow = (double) OrbitInput::get('rating_low', 0);
            $ratingHigh = (double) OrbitInput::get('rating_high', 5);
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

            $cacheKey = [
                'country' => $countryFilter,
                'cities' => $cityFilters,
                'skip' => $skip,
                'take' => $take,
                'sort_by' => $sortBy,
                'sort_mode' => $sortMode,
                'language' => $language,
                'location' => $location,
                'list_type' => $list_type,
                'rating_low' => $ratingLow,
                'rating_high' => $ratingHigh,
            ];

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $prefix = DB::getTablePrefix();

            $this->searcher->setPaginationParams(['from' => $skip, 'size' => $take]);

            $this->searcher->filterBase();

            $countryData = null;
            $area = ['country' => '', 'cities' => $cityFilters];

            if ($countryFilter != '0') {
                $area['country'] = $countryFilter;
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();
            }

            // Otherwise, we filter based on user's selection of country
            // and/or cities
            $this->searcher->filterByCountryAndCities($area);

            $keyword = OrbitInput::get('keyword', null);
            $forbiddenCharacter = array('>', '<', '(', ')', '{', '}', '[', ']', '^', '"', '~', '/');
            $keyword = str_replace($forbiddenCharacter, '', $keyword);
            if (! empty($keyword)) {
                $cacheKey['keyword'] = $keyword;
                $this->searcher->filterByKeyword($keyword);
            }

            // Filter by partner
            // Check for competitor first.
            OrbitInput::get('partner_id', function($partnerId) use (&$searchFlag, &$cacheKey) {
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

                            $this->searcher->excludePartnerCompetitors($partnerIds);
                        }
                        else {
                            $this->searcher->filterByPartner($partnerId);
                        }
                    }
                }
            });

            // Make sure to prioritize mall order from config
            // (At the moment, until we add feature to set ordering in admin portal)
            $bypassMallOrder = OrbitInput::get('by_pass_mall_order', 'n');
            $mallFeaturedIds = array();
            if ($bypassMallOrder === 'n') {
                $cacheKey['by_pass_mall_order'] = $bypassMallOrder;
                $this->searcher->bypassMallOrder(compact('countryFilter', 'cityFilters'));

                // Get featured IDS for set as featured
                if (! empty($countryFilter)) {
                    $countryFilter = strtolower($countryFilter);
                    $mallFeaturedIds = Config::get('orbit.featured.mall_ids.' . $countryFilter . '.all', []);

                    if (! empty($cityFilters)) {
                        $mallFeaturedIds = [];
                        foreach ($cityFilters as $key => $cityName) {
                            $cityName = str_replace(' ', '_', strtolower($cityName));
                            $cityValue = Config::get('orbit.featured.mall_ids.' . $countryFilter . '.' . $cityName, []);

                            if (! empty($cityValue)) {
                                $mallFeaturedIds = array_merge($cityValue, $mallFeaturedIds);
                            }
                        }
                    }
                } else {
                    $mallFeaturedIds = Config::get('orbit.featured.mall_ids.all', []);
                }
            }

            // Get Advertised Malls...
            $withPreferred = [];
            if ($withAdvert) {
                $locationId = 0;
                $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_regular', 'preferred_list_large'] : ['preferred_list_regular', 'preferred_list_large'];

                $advertResult = $this->searcher->filterWithAdvert(compact('dateTimeEs', 'advertType', 'locationId', 'list_type'));
                $withPreferred = $advertResult['withPreferred'];
            }

            //filter by rating number
            $this->searcher->filterByRating(
                $ratingLow,
                $ratingHigh,
                compact(
                    'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
                )
            );

            $scriptFields = $this->searcher->addReviewFollowScript(compact(
                'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
            ));

            $objectFollow = $scriptFields['objectFollow'];

            // Force to sort result by relevance if any keyword is set.
            if (! empty($keyword)) {
                $sortBy = 'relevance';
            }

            // Next sorting based on Visitor's selection.
            switch ($sortBy) {
                case 'relevance':
                    $this->searcher->sortByRelevance();
                    break;
                case 'updated_at':
                    $this->searcher->sortByUpdatedAt();
                    break;
                case 'location':
                    $this->searcher->sortByNearest($ul);
                    break;
                case 'rating':
                    $this->searcher->sortByRating($scriptFields['scriptFieldRating'], $sortMode);
                    break;
                case 'followed':
                    $this->searcher->sortByFavorite($scriptFields['scriptFieldFollow']);
                    break;
                default:
                    $this->searcher->sortByName($sortMode);
                    break;
            }

            // Add any constant scoring to search body if set.
            $this->searcher->addConstantScoringToQuery();

            if ($this->useScroll) {
                $this->searcher->setParams([
                    'search_type' => 'scan',
                    'scroll' => $this->scrollDuration,
                ]);
                $this->searcher->removeParamItem('body.aggs');

                return $this->searcher->getResult();
            }

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() use($serializedCacheKey, $recordCache) {
                    $resp = $this->searcher->getResult();
                    $recordCache->put($serializedCacheKey, $resp);
                    return $resp;
                });
            } else {
                $response = $this->searcher->getResult();
            }

            $area_data = $response['hits'];
            $listmall = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            $total = $area_data['total'];
            foreach ($area_data['hits'] as $dt) {
                $areadata = array();

                $placementType = null;
                $placementTypeOrig = null;
                if (in_array($dt['_source']['merchant_id'], $mallFeaturedIds)) {
                    $placementType = 'featured';
                    $placementTypeOrig = 'featured';
                    $areadata['is_featured'] = true;
                }

                $areadata['placement_type'] = $placementType;
                $areadata['placement_type_orig'] = $placementTypeOrig;
                $areadata['average_rating'] = (! empty($dt['fields']['average_rating'][0])) ? number_format(round($dt['fields']['average_rating'][0], 1), 1) : 0;
                $areadata['total_review'] = (! empty($dt['fields']['total_review'][0])) ? round($dt['fields']['total_review'][0], 1) : 0;
                $mallId = $dt['_source']['merchant_id'];

                $pageView = 0;
                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $redisKeyGTM = 'mall' . '||' . $mallId . '||0';
                    $redisKeyMall = 'mall' . '||' . $mallId . '||' . $mallId;
                    $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                    $redis = Redis::connection($redisConnection);
                    $pageViewGTM = (! empty($redis->get($redisKeyGTM))) ? $redis->get($redisKeyGTM) : 0;
                    $pageViewMall = (! empty($redis->get($redisKeyMall))) ? $redis->get($redisKeyMall) : 0;

                    $pageView = (int) $pageViewGTM + (int) $pageViewMall;
                }

                $followStatus = false;
                if (! empty($objectFollow)) {
                    if (in_array($mallId, $objectFollow)) {
                        // if mall_id is available inside $objectFollow set follow status to true
                        $followStatus = true;
                    }
                }

                if ($words === 1) {
                    // handle if user filter location with one word, ex "jakarta", data in city "jakarta selatan", "jakarta barat" etc will be dissapear
                    if (strtolower($dt['_source']['city']) === strtolower($location)) {
                        $areadata['id'] = $mallId;
                        $localPath = '';
                        $cdnPath = '';

                        foreach ($dt['_source'] as $source => $val) {

                            // advert type
                            if ($source === 'advert_type') {
                                $areadata['placement_type'] = $val;
                                $areadata['placement_type_orig'] = $val;

                                if ($list_type === 'featured') {
                                    if ($val === 'featured_list') {
                                        $areadata['is_featured'] = true;
                                    }

                                    if (isset($withPreferred[$mallId])) {
                                        $areadata['placement_type'] = $withPreferred[$mallId];
                                        $areadata['placement_type_orig'] = $withPreferred[$mallId];
                                    }
                                }
                            }

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
                    $areadata['id'] = $mallId;
                    $localPath = '';
                    $cdnPath = '';

                    foreach ($dt['_source'] as $source => $val) {

                        if ($source === 'advert_type') {
                            $areadata['placement_type'] = $val;
                            $areadata['placement_type_orig'] = $val;

                            if ($list_type === 'featured') {
                                if ($val === 'featured_list') {
                                    $areadata['is_featured'] = true;
                                }

                                if (isset($withPreferred[$mallId])) {
                                    $areadata['placement_type'] = $withPreferred[$mallId];
                                    $areadata['placement_type_orig'] = $withPreferred[$mallId];
                                }
                            }
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


    /**
     * Force $useScroll value to TRUE, ignoring previously set value
     * @param $bool boolean
     */
    public function setUseScroll()
    {
        $this->useScroll = TRUE;

        return $this;
    }

    /**
     * Set $scrollDuration, use less when $useScroll is FALSE
     * @param $scrollDuration int
     */
    public function setScrollDuration($scrollDuration=20)
    {
        $this->scrollDuration = $scrollDuration . 's';

        return $this;
    }

    /**
     * Get Searcher
     */
    public function getSearcher()
    {
        return $this->searcher->getActiveClient();
    }
}
