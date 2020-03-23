<?php

namespace Orbit\Controller\API\v1\Pub\Store;

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
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Elasticsearch\ClientBuilder;
use Lang;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Orbit\Controller\API\v1\Pub\Store\StoreHelper;
use Country;
use Orbit\Helper\Util\FollowStatusChecker;
use UserDetail;
use StoreSearch;

/**
* @todo  add search result mapper class.
* @todo  the location filter?
*/
class StoreListNewAPIController extends PubControllerAPI
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

    public function __construct($contentType = 'application/json')
    {
        parent::__construct($contentType);
        $this->esConfig = Config::get('orbit.elasticsearch');
        $this->searcher = new StoreSearch($this->esConfig);
    }

    /**
     *
     * @todo refactor as this is similar to promotion, coupon or store listing
     *
     * @return [type] [description]
     */
    public function getStoreList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $mall = NULL;
        $mall_name = NULL;
        $mall_city = NULL;
        $user = NULL;
        $httpCode = 200;

        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'store-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');
        $advertCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-adverts');
        $countCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-store-count');

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $sortBy = OrbitInput::get('sortby', 'name');
            $sortMode = OrbitInput::get('sortmode','asc');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', []);
            $cityFilters = (array) $cityFilters;
            $countryFilter = OrbitInput::get('country', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $ul = OrbitInput::get('ul');
            $lon = 0;
            $lat = 0;
            $list_type = OrbitInput::get('list_type', 'preferred');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $categoryIds = OrbitInput::get('category_id', []);
            $partnerId = OrbitInput::get('partner_id', '');
            $mallId = OrbitInput::get('mall_id', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();
            $viewType = OrbitInput::get('view_type', 'grid');
            $withCache = FALSE;
            $withAdvert = (bool) OrbitInput::get('with_advert', true);
            $gender = OrbitInput::get('gender', 'all');
            $ratingLow = OrbitInput::get('rating_low', 0);
            $ratingHigh = OrbitInput::get('rating_high', 5);
            $ratingLow = empty($ratingLow) ? 0 : $ratingLow;
            $ratingHigh = empty($ratingHigh) ? 5 : $ratingHigh;
            // search by key word or filter or sort by flag
            $searchFlag = FALSE;
            $excludedIds = OrbitInput::get('excluded_ids');

            // store can not sorted by date, so it must be changes to default sorting (name - ascending)
            if ($sortBy === "created_date") {
                $sortBy = "name";
                $sortMode = "asc";
            }

            // Call validation from store helper
            $storeHelper = StoreHelper::create();
            $storeHelper->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sortBy,
                    'rating_low' => $ratingLow,
                    'rating_high' => $ratingHigh,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,updated_date,rating,followed,relevance',
                    'rating_low' => 'numeric|min:0|max:5',
                    'rating_high' => 'numeric|min:0|max:5',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sortBy, 'sort_mode' => $sortMode, 'language' => $language,
                'location' => $location,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
                'country' => $countryFilter, 'cities' => $cityFilters,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;
            $esTake = $take;
            if ($list_type === 'featured') {
                $esTake = 50;
            }

            $this->searcher->setPaginationParams(['from' => $skip, 'size' => $take]);

            $this->searcher->hasAtLeastOneTenant();

            $this->searcher->exclude($excludedIds);

            $countryData = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();

                if (empty($mall)) {
                    OrbitShopAPI::throwInvalidArgument('Mall with ID: ' . $mallId . ' not found.');
                }
            }
            else {
                $area = ['country' => '', 'cities' => $cityFilters];

                if ($countryFilter != '0') {
                    $area['country'] = $countryFilter;
                    $countryData = Country::select('country_id')->where('name', $countryFilter)->first();
                }

                $location = [];
            }

            // If under mall page, we should ignore any other area/location filter.
            if (isset($mall)) {
                $this->searcher->filterByMall($mallId);
            }
            else {
                // Otherwise, we filter based on user's selection of country and/or cities
                $this->searcher->filterByCountryAndCities($area);
            }

            if (! empty($categoryIds)) {
                $this->searcher->filterByCategories($categoryIds);
            }

            // Filter by gender
            if (! empty($gender)) {
                $filterGender = 'all';
                if ($gender === 'mygender') {
                    $userGender = UserDetail::select('gender')->where('user_id', '=', $user->user_id)->first();
                    if ($userGender) {
                        if (strtolower($userGender->gender) == 'm') {
                            $filterGender = 'male';
                        } else if (strtolower($userGender->gender) == 'f') {
                            $filterGender = 'female';
                        }
                    }
                }
                $cacheKey['gender'] = $filterGender;
                $this->searcher->filterByGender(strtolower($filterGender));
            }

            // Filter by keyword
            $keyword = $this->searcher->escape(OrbitInput::get('keyword'));
            if (! empty($keyword)) {
                $this->searcher->filterByKeyword($keyword);
            }

            // Filter by partner
            $partnerId = OrbitInput::get('partner_id', null);
            if (! empty($partnerId)) {
                $cacheKey['partner_id'] = $partnerId;
                $this->searcher->filterByPartner($partnerId);
            }

            $sortByPageType = array();
            $pageTypeScore = '';
            if ($list_type === 'featured') {
                $pageTypeScore = 'featured_gtm_score';
                $sortByPageType = array('featured_gtm_score' => array('order' => 'desc'));
                if (! empty($mall)) {
                    $pageTypeScore = 'featured_mall_score';
                    $sortByPageType = array('featured_mall_score' => array('order' => 'desc'));
                }
            } else {
                $pageTypeScore = 'preferred_gtm_score';
                $sortByPageType = array('preferred_gtm_score' => array('order' => 'desc'));
                if (! empty($mall)) {
                    $pageTypeScore = 'preferred_mall_score';
                    $sortByPageType = array('preferred_mall_score' => array('order' => 'desc'));
                }
            }

            $sortPageScript = "if (doc.containsKey('" . $pageTypeScore . "')) { if(! doc['" . $pageTypeScore . "'].empty) { return doc['" . $pageTypeScore . "'].value } else { return 0}} else {return 0}";
            $advertStoreOrdering = [
                '_script' => [
                    'script' => $sortPageScript,
                    'type' => 'string',
                    'order' => 'desc'
                ]
            ];

            // Get Advert_Store...
            $locationId = ! empty($mallId) ? $mallId : 0;
            if ($withAdvert) {
                $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_regular', 'preferred_list_large'] : ['preferred_list_regular', 'preferred_list_large'];
                $this->searcher->filterWithAdvert(compact('list_type', 'dateTimeEs', 'mallId', 'advertType', 'locationId', 'advertStoreOrdering'));
            }

            $scriptFields = $this->searcher->addReviewFollowScript(compact(
                'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
            ));

            /*** disable follow status on listing ***/
            //$objectFollow = $scriptFields['objectFollow'];

            //filter by rating number
            $this->searcher->filterByRating(
                $ratingLow,
                $ratingHigh,
                compact(
                    'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
                )
            );

            // Force sort by relevance if visitor provide any keyword/searching.
            if (! empty($keyword)) {
                $sortBy = 'relevance';
            }

            // Next sorting based on Visitor's selection.
            switch ($sortBy) {
                case 'relevance':
                    $this->searcher->sortByRelevance();
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
                    $this->searcher->sortByName($language, $sortMode);
                    break;
            }

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
                $response = $recordCache->get($serializedCacheKey, function() use ($serializedCacheKey, $recordCache) {
                    $resp = $this->searcher->getResult();
                    $recordCache->put($serializedCacheKey, $resp);
                    return $resp;
                });
            } else {
                $response = $this->searcher->getResult();
            }

            $records = $response['hits'];

            $listOfRec = [];
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');
            $innerHitsCount = 0;

            foreach ($records['hits'] as $record) {
                $data = [];
                $localPath = '';
                $cdnPath = '';
                $default_lang = '';
                $pageView = 0;
                $data['placement_type'] = null;
                $data['placement_type_orig'] = null;
                $storeId = '';
                /*** disable follow status on listing ***/
                //$data['follow_status'] = false;
                $baseMerchantId = '';
                foreach ($record['_source'] as $key => $value) {
                    if ($key === 'merchant_id') {
                        $storeId = $value;
                    }

                    $localPath = ($key == 'logo') ? $value : $localPath;
                    $cdnPath = ($key == 'logo_cdn') ? $value : $cdnPath;
                    $key = ($key == 'logo') ? 'logo_url' : $key;

                    if ($key === 'base_merchant_id') {
                        $baseMerchantId = $value;
                    }

                    $data[$key] = $value;
                    $data['logo_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);

                    $default_lang = (empty($record['_source']['default_lang']))? '' : $record['_source']['default_lang'];

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        foreach ($record['_source']['translation'] as $dt) {

                            if ($dt['language_code'] === $language) {
                                // desc
                                if (! empty($dt['description'])) {
                                    $data['description'] = $dt['description'];
                                }
                            } elseif ($dt['language_code'] === $default_lang) {
                                // desc
                                if (! empty($dt['description']) && empty($data['description'])) {
                                    $data['description'] = $dt['description'];
                                }
                            }
                        }
                    }

                    // advert type
                    if ($list_type === 'featured') {
                        if (! empty($mallId) && $key === 'featured_mall_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        } elseif ($key === 'featured_gtm_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        }

                        if (! empty($withPreferred[$storeId])) {
                            $data['placement_type'] = $withPreferred[$storeId];
                            $data['placement_type_orig'] = $withPreferred[$storeId];
                        }
                    } elseif ($list_type === 'preferred') {
                        if (! empty($mallId) && $key === 'preferred_mall_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        } elseif ($key === 'preferred_gtm_type') {
                            $data['placement_type'] = $value;
                            $data['placement_type_orig'] = $value;
                        }
                    }

                    if (empty($mallId)) {
                        if ($key === 'gtm_page_views') {
                            $pageView = $value;
                        }
                    } else {
                        if (isset($record['_source']['mall_page_views'])) {
                            foreach ($record['_source']['mall_page_views'] as $dt) {
                                if ($dt['location_id'] === $mallId) {
                                    $pageView = $dt['total_views'];
                                }
                            }
                        }
                    }
                }

                if (! empty($record['inner_hits']['tenant_detail']['hits']['total'])) {
                    if (! empty($mallId)) {
                        $data['merchant_id'] = $record['inner_hits']['tenant_detail']['hits']['hits'][0]['_source']['merchant_id'];
                    }
                }

                /*** disable follow status on listing ***/
                // if (! empty($objectFollow)) {
                //     if (in_array($baseMerchantId, $objectFollow)) {
                //         $data['follow_status'] = true;
                //     }
                // }

                $data['average_rating'] = (! empty($record['fields']['average_rating'][0])) ? number_format(round($record['fields']['average_rating'][0], 1), 1) : 0;
                $data['total_review'] = (! empty($record['fields']['total_review'][0])) ? round($record['fields']['total_review'][0], 1) : 0;

                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $baseStore = BaseStore::where('base_store_id', $storeId)->first();

                    if (! empty($baseStore)) {
                        $redisKey = 'tenant' . '||' . $baseStore->base_merchant_id . '||' . $locationId;
                        $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                        $redis = Redis::connection($redisConnection);
                        $pageView = (! empty($redis->get($redisKey))) ? $redis->get($redisKey) : $pageView;
                    }
                }
                $data['page_view'] = $pageView;
                $data['score'] = $record['_score'];
                $listOfRec[] = $data;
            }

            $data = new \stdClass();

            $data->returned_records = count($listOfRec);
            $data->total_records = $records['total'];

            if (isset($mall)) {
                $data->mall_name = $mall->name;
                $data->mall_city = $mall->city;
            }
            $data->records = $listOfRec;

            // // random featured adv
            // // @todo fix for random -- this is not the right way to do random, it could lead to memory leak
            if ($list_type === 'featured') {
                $advertedCampaigns = array_filter($listOfRec, function($v) {
                    return ($v['placement_type_orig'] === 'featured_list');
                });

                if (count($advertedCampaigns) > $take) {
                    $output = array();
                    $listSlide = array_rand($advertedCampaigns, $take);
                    if (count($listSlide) > 1) {
                        foreach ($listSlide as $key => $value) {
                            $output[] = $advertedCampaigns[$value];
                        }
                    } else {
                        $output = $advertedCampaigns[$listSlide];
                    }
                } else {
                    $output = array_slice($listOfRec, 0, $take);
                }

                $data->returned_records = count($output);
                $data->total_records = $records['total'];
                $data->records = $output;
            }

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall store list page');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_store_list')
                            ->setActivityNameLong('View mall store list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Store')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Store list');
                        $activity->setUser($user)
                            ->setActivityName('view_stores_main_page')
                            ->setActivityNameLong('View Stores Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Store')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
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
