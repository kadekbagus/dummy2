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
use Redis;
use News;
use Advert;
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
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;
use UserSponsor;
use UserDetail;
use NewsSearch;
use AdvertStoreSearch as AdvertSearch;

class NewsListNewAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
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
        $this->searcher = new NewsSearch($this->esConfig);
    }

    /**
     * GET - get active news in all mall, and also provide for searching
     *
     * @todo refactor as this is similar to promotion, coupon or store listing
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
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');
        $advertCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-adverts');

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $sortBy = OrbitInput::get('sortby', 'created_date');
            $sortMode = OrbitInput::get('sortmode','desc');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', []);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $category_id = OrbitInput::get('category_id');
            $categoryIds = OrbitInput::get('category_id', []);
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $partnerToken = OrbitInput::get('token', null);
            $viewType = OrbitInput::get('view_type', 'grid');
            $myCCFilter = OrbitInput::get('my_cc_filter', false);
            $withAdvert = (bool) OrbitInput::get('with_advert', true);
            $gender = OrbitInput::get('gender', 'all');
            $ratingLow = OrbitInput::get('rating_low', 0);
            $ratingHigh = OrbitInput::get('rating_high', 5);
            $ratingLow = empty($ratingLow) ? 0 : $ratingLow;
            $ratingHigh = empty($ratingHigh) ? 5 : $ratingHigh;

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $newsHelper = NewsHelper::create();
            $newsHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sortBy,
                    'rating_low' => $ratingLow,
                    'rating_high' => $ratingHigh,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date,updated_date,rating,relevance',
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
                'with_premium' => $withPremium, 'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
                'country' => $countryFilter, 'cities' => $cityFilters,
                'my_cc_filter' => $myCCFilter,
                'rating_low' => $ratingLow,
                'rating_high' => $ratingHigh,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $newsHelper->getValidLanguage();
            $prefix = DB::getTablePrefix();

            $withScore = false;
            $esTake = $take;
            if ($list_type === 'featured') {
                $esTake = 50;
            }

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $this->searcher->setPaginationParams(['from' => $skip, 'size' => $take]);

            // Only search active promotions..
            $this->searcher->isActive(compact('dateTimeEs'));

            // Filter by given keyword...
            $keyword = OrbitInput::get('keyword');
            $forbiddenCharacter = array('>', '<', '(', ')', '{', '}', '[', ']', '^', '"', '~', '/');
            $keyword = str_replace($forbiddenCharacter, '', $keyword);
            if (! empty($keyword)) {
                $cacheKey['keyword'] = $keyword;
                $this->searcher->filterByKeyword($keyword);
            }

            $countryData = null;
            $mall = null;
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

            }

            // If under mall page, we should ignore any other area/location filter.
            if (! empty($mall)) {
                $this->searcher->filterByMall($mallId);
            }
            else {
                $this->searcher->filterByCountryAndCities($area);
            }

            // Filter by my credit card or choose manually
            $sponsorProviderIds = OrbitInput::get('sponsor_provider_ids', []);
            if ($myCCFilter) {
                $sponsorProviderIds = $this->searcher->filterByMyCC(compact('myCCFilter', 'user'));

                if (count($sponsorProviderIds) > 0) {
                    $cacheKey['sponsor_provider_ids'] = $sponsorProviderIds;
                }
            } else if (! empty($sponsorProviderIds)) {
                $cacheKey['sponsor_provider_ids'] = $sponsorProviderIds;
                $this->searcher->filterBySponsors($sponsorProviderIds);
            }

            // Filter by selected categories...
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

            // Filter by partner...
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
            $advertSorting = [
                '_script' => [
                    'script' => $sortPageScript,
                    'type' => 'string',
                    'order' => 'desc'
                ]
            ];

            $locationId = ! empty($mall) ? $mallId : 0;
            if ($withAdvert) {
                $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_regular', 'preferred_list_large'] : ['preferred_list_regular', 'preferred_list_large'];
                $this->searcher->filterWithAdvert(compact('dateTimeEs', 'mallId', 'advertType', 'locationId', 'list_type', 'advertSorting'));
            }

            //filter by rating number
            $this->searcher->filterByRating(
                $ratingLow,
                $ratingHigh,
                compact(
                    'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
                )
            );

            // Add script fields...
            $scriptFields = $this->searcher->addReviewFollowScript(compact(
                'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
            ));

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
                case 'created_date':
                    $this->searcher->sortByCreatedDate($sortMode);
                    break;
                case 'updated_date':
                    $this->searcher->sortByUpdatedDate($sortMode);
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

            // Exclude specific document Ids, useful for some cases e.g You May Also Like
            // @todo rewrite deprected 'filtered' query to bool only
            $withAdvert = TRUE;
            OrbitInput::get('excluded_ids', function($excludedIds) use (&$withAdvert) {
                $jsonExcludedIds = [];
                foreach ($excludedIds as $excludedId) {
                    $jsonExcludedIds[] = array('term' => ['_id' => $excludedId]);
                }

                if (count($jsonExcludedIds) > 0) {
                    $this->searcher->exclude($jsonExcludedIds);
                }

                $withAdvert = FALSE;
            });

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() {
                    return $this->searcher->getResult();
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $this->searcher->getResult();
            }

            $records = $response['hits'];

            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($records['hits'] as $record) {
                $data = array();
                $default_lang = '';
                $partnerTokens = isset($record['_source']['partner_tokens']) ? $record['_source']['partner_tokens'] : [];
                $pageView = 0;
                $data['placement_type'] = null;
                $data['placement_type_orig'] = null;
                $isHavingReward = 'N';
                $avgGeneralRating = 0;
                $totalGeneralReviews = 0;
                $campaignId = '';
                foreach ($record['_source'] as $key => $value) {
                    if ($key === 'news_id') {
                        $campaignId = $value;
                    }

                    if ($key === 'is_having_reward') {
                        $isHavingReward = $value;
                    }

                    if ($key === "name") {
                        $key = "news_name";
                    }

                    $default_lang = (empty($record['_source']['default_lang']))? '' : $record['_source']['default_lang'];
                    $data[$key] = $value;

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        $data['image_url'] = '';

                        foreach ($record['_source']['translation'] as $dt) {
                            $localPath = (! empty($dt['image_url'])) ? $dt['image_url'] : '';
                            $cdnPath = (! empty($dt['image_cdn_url'])) ? $dt['image_cdn_url'] : '';

                            if ($dt['language_code'] === $language) {
                                // name
                                if (! empty($dt['name'])) {
                                    $data['news_name'] = $dt['name'];
                                }

                                // desc
                                if (! empty($dt['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url'])) {
                                    $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                }
                            } elseif ($dt['language_code'] === $default_lang) {
                                // name
                                if (! empty($dt['name']) && empty($data['news_name'])) {
                                    $data['news_name'] = $dt['name'];
                                }

                                // description
                                if (! empty($dt['description']) && empty($data['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (empty($data['image_url'])) {
                                    $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
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

                        if (! empty($withPreferred[$campaignId])) {
                            $data['placement_type'] = $withPreferred[$campaignId];
                            $data['placement_type_orig'] = $withPreferred[$campaignId];
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

                    if ($key === "is_exclusive") {
                        $data[$key] = ! empty($data[$key]) ? $data[$key] : 'N';
                        // disable is_exclusive if token is sent and in the partner_tokens
                        if ($data[$key] === 'Y' && in_array($partnerToken, $partnerTokens)) {
                            $data[$key] = 'N';
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

                    if ($key === 'avg_general_rating') {
                        $avgGeneralRating = $value;
                    }

                    if ($key === 'total_general_reviews') {
                        $totalGeneralReviews = $value;
                    }
                }

                $data['average_rating'] = (! empty($record['fields']['average_rating'][0])) ? number_format(round($record['fields']['average_rating'][0], 1), 1) : 0;
                $data['total_review'] = (! empty($record['fields']['total_review'][0])) ? round($record['fields']['total_review'][0], 1) : 0;
                if ($isHavingReward === 'Y') {
                    $data['average_rating'] = ($avgGeneralRating != 0) ? number_format(round($avgGeneralRating, 1), 1) : 0;
                    $data['total_review'] = round($totalGeneralReviews, 1);
                }

                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $redisKey = 'news' . '||' . $campaignId . '||' . $locationId;
                    $redisConnection = Config::get('orbit.page_view.redis.connection', '');
                    $redis = Redis::connection($redisConnection);
                    $pageView = (! empty($redis->get($redisKey))) ? $redis->get($redisKey) : $pageView;
                }
                $data['page_view'] = $pageView;

                $data['score'] = $record['_score'];
                unset($data['created_by'], $data['creator_email'], $data['partner_tokens']);
                $listOfRec[] = $data;
            }

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $records['total'];
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
                $data->mall_city = $mall->city;
            }
            $data->records = $listOfRec;

            // random featured adv
            // @todo fix for random -- this is not the right way to do random, it could lead to memory leak
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
                            ->setObjectDisplayName($viewType)
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

        return $this->render($httpCode);
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
