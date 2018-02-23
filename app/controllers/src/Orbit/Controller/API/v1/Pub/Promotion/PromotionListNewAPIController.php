<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for promotion list and search in landing page
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
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\Promotion\PromotionHelper;
use Mall;
use stdClass;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Country;
use UserSponsor;

use PromotionSearch;
use AdvertStoreSearch as AdvertSearch;

class PromotionListNewAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;

    /**
     * GET - get active promotion in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
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
    public function getSearchPromotion()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;
        $cacheKey = [];
        $serializedCacheKey = '';

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'promotion-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');
        $advertCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-adverts');

        try {
            $this->checkAuth();
            $user = $this->api->user;
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'name');
            $sortBy = OrbitInput::get('sortby', 'relevance');
            $sort_mode = OrbitInput::get('sortmode','desc');
            $sortMode = OrbitInput::get('sortmode','desc');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $categoryIds = OrbitInput::get('category_id', []);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('promotion');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $partnerToken = OrbitInput::get('token', null);
            $viewType = OrbitInput::get('view_type', 'grid');
            $myCCFilter = OrbitInput::get('my_cc_filter', false);

             // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $promotionHelper = PromotionHelper::create();
            $promotionHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:relevance,name,location,created_date,updated_date,rating',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sort_by, 'sort_mode' => $sort_mode, 'language' => $language,
                'location' => $location,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'with_premium' => $withPremium, 'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
                'country' => $countryFilter, 'cities' => $cityFilters,
                'my_cc_filter' => $myCCFilter
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $promotionHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s", strtotime('+1 day'));
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;
            $esTake = $take;
            if ($list_type === 'featured') {
                $esTake = 50;
            }

            $jsonQuery = array(
                'from' => $skip, 
                'size' => $esTake, 
                'fields' => array("_source"), 
                'query' => array(
                    'bool' => array(
                        'filter' => array( 
                            array(
                                'query' => array(
                                    'match' => array(
                                        'status' => 'active'
                                    )
                                )
                            ), 
                            array(
                                'range' => array(
                                    'begin_date' => array('lte' => $dateTimeEs)
                                )
                            ), 
                            array(
                                'range' => array(
                                    'end_date' => array('gte' => $dateTimeEs)
                                )
                            )
                        )
                    )
                )
            );

            // Get ES config only once, avoid calling Config::get() everytime. :)
            $esConfig = Config::get('orbit.elasticsearch');

            // Create the search...
            $esIndex = $esConfig['indices_prefix'] . $esConfig['indices']['promotions']['index'];
            $promotionSearch = new PromotionSearch($esConfig);

            $promotionSearch->setPaginationParams(['from' => $skip, 'size' => $take]);

            // Only search active promotions..
            $promotionSearch->isActive(compact('dateTimeEs'));
            
            // Filter by given keyword...
            $keyword = OrbitInput::get('keyword');
            if (! empty($keyword)) {
                $cacheKey['keyword'] = $keyword;
                $promotionSearch->filterByKeyword($keyword);
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

                $location = [];
            }

            // If under mall page, we should ignore any other area/location filter.
            if (! empty($mall)) {
                $promotionSearch->filterByMall($mallId);
            }

            // Filter by my credit card or choose manually
            $sponsorProviderIds = OrbitInput::get('sponsor_provider_ids', []);
            if ($myCCFilter) {
                $promotionSearch->filterByMyCC(compact('myCCFilter', 'user'));
            } else if (! empty($sponsorProviderIds)) {
                $cacheKey['sponsor_provider_ids'] = $sponsorProviderIds;
                $promotionSearch->filterBySponsors($sponsorProviderIds);
            }

            // return \Response::json($promotionSearch->getRequestParam());


            // Filter by selected categories...
            if (! empty($categoryIds)) {
                $promotionSearch->filterByCategories($categoryIds);
            }

            // Filter by partner...
            OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery, $prefix, &$searchFlag, &$cacheKey) {
                $cacheKey['partner_id'] = $partnerId;
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $searchFlag = $searchFlag || TRUE;
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'promotion');
                                                            })
                                                            ->where('partner_id', $partnerId)
                                                            ->first();

                    if (is_object($partnerAffected)) {
                        $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                        if (in_array($partnerId, $exception)) {
                            $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');
                            $promotionSearch->excludePartnerCompetitors($partnerIds);
                        }
                        else {
                           $promotionSearch->filterByPartner($partnerId); 
                        }
                    }
                }
            });

            // Filter by locations...
            
            // Filter by country/city only if no mall is set.
            if (empty($mall)) {
                // Otherwise, we filter based on user's selection of country 
                // and/or cities
                $promotionSearch->filterByCountryAndCities($area);

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
                
                // Also user's geo-location...
                // $promotionSearch->filterByLocation($location);
            }

            $withAdvert = true;
            if ($withAdvert) {

                // Get Advert_Store...
                $esAdvertPromotionIndex = $esConfig['indices_prefix'] . $esConfig['indices']['advert_promotions']['index'];
                $advertSearch = new AdvertSearch($esConfig, 'advert_promotions');

                $advertSearch->setPaginationParams(['from' => 0, 'size' => 100]);

                $locationId = ! empty($mall) ? $mallId : 0;
                $advertType = ($list_type === 'featured') ? ['featured_list', 'preferred_list_regular', 'preferred_list_large'] : ['preferred_list_regular', 'preferred_list_large'];

                $advertSearch->filterPromotions(compact('dateTimeEs', 'mallId', 'advertType', 'locationId'));

                $advertSearchResult = $advertSearch->getResult();

                if ($advertSearchResult['hits']['total'] > 0) {
                    $esIndex .= ',' . $esAdvertPromotionIndex;
                    $advertList = $advertSearchResult['hits']['hits'];
                    $excludeId = array();
                    $withPreferred = array();

                    foreach ($advertList as $adverts) {
                        $advertId = $adverts['_id'];
                        $newsId = $adverts['_source']['news_id'];
                        if(! in_array($newsId, $excludeId)) {
                            $excludeId[] = $newsId;
                        } else {
                            $excludeId[] = $advertId;
                        }

                        // if featured list_type check preferred too
                        if ($list_type === 'featured') {
                            if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                                if (empty($withPreferred[$newsId]) || $withPreferred[$newsId] != 'preferred_list_large') {
                                    $withPreferred[$newsId] = 'preferred_list_regular';
                                    if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                        $withPreferred[$newsId] = 'preferred_list_large';
                                    }
                                }
                            }
                        }
                    }

                    if (count($excludeId) > 0) {
                        $promotionSearch->exclude($excludeId);
                    }

                    if (count($withPreferred) > 0) {
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
                        $advertPromotionSorting = [
                            '_script' => [
                                'script' => $sortPageScript, 
                                'type' => 'string', 
                                'order' => 'desc'
                            ]
                        ];

                        $promotionSearch->sortBy($advertPromotionSorting);
                    }
                }
            }

            // Add script fiedls...
            $scriptFields = $promotionSearch->addReviewFollowScript(compact(
                'mallId', 'cityFilters', 'countryFilter', 'countryData', 'user', 'sortBy'
            ));
            
            // Next sorting based on Visitor's selection.
            switch ($sortBy) {
                case 'name':
                    $promotionSearch->sortByName();
                    break;
                case 'rating':
                    $promotionSearch->sortByRating($scriptFields['scriptFieldRating']);
                    // $promotionSearch->sortByRelevance();
                    break;
                case 'created_date':
                    $promotionSearch->sortByCreatedDate($sortMode);
                    break;
                case 'updated_date':
                    $promotionSearch->sortByUpdatedDate($sortMode);
                    break;
                default:
                    $promotionSearch->sortByRelevance();
                    break;
            }

            // Exclude specific document Ids, useful for some cases e.g You May Also Like
            // @todo rewrite deprected 'filtered' query to bool only
            $withAdvert = TRUE;
            OrbitInput::get('excluded_ids', function($excludedIds) use (&$jsonQuery, &$withAdvert) {
                $jsonExcludedIds = [];
                foreach ($excludedIds as $excludedId) {
                    $jsonExcludedIds[] = array('term' => ['_id' => $excludedId]);
                }
                
                if (count($jsonExcludedIds) > 0) {
                    $promotionSearch->exclude($jsonExcludedIds);
                }

                $withAdvert = FALSE;
            });

            $promotionSearch->setIndex($esIndex);

            // return \Response::json($promotionSearch->getRequestParam());

            $esParam = [
                'index'  => $esIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.promotions.type'),
                'body' => json_encode($jsonQuery)
            ];

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() use ($promotionSearch, &$esParam) {
                    return $promotionSearch->getResult();
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $promotionSearch->getResult();
            }

            $records = $response['hits'];

            // return \Response::json($records);

            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($records['hits'] as $record) {
                $data = array();
                $isOwned = false;
                $default_lang = '';
                $partnerTokens = isset($record['_source']['partner_tokens']) ? $record['_source']['partner_tokens'] : [];
                $pageView = 0;
                $data['placement_type'] = null;
                $data['placement_type_orig'] = null;
                $campaignId = '';
                foreach ($record['_source'] as $key => $value) {
                    if ($key === 'news_id') {
                        $campaignId = $value;
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
                }

                $data['average_rating'] = (! empty($record['fields']['average_rating'][0])) ? number_format(round($record['fields']['average_rating'][0], 1), 1) : 0;
                $data['total_review'] = (! empty($record['fields']['total_review'][0])) ? round($record['fields']['total_review'][0], 1) : 0;

                if (Config::get('orbit.page_view.source', 'mysql') === 'redis') {
                    $redisKey = 'promotion' . '||' . $campaignId . '||' . $locationId;
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
                        $activityNotes = sprintf('Page viewed:  View mall promotion list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_promotion_list')
                            ->setActivityNameLong('View mall promotion list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Promotion')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Promotion list');
                        $activity->setUser($user)
                            ->setActivityName('view_promotions_main_page')
                            ->setActivityNameLong('View Promotions Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Promotion')
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}