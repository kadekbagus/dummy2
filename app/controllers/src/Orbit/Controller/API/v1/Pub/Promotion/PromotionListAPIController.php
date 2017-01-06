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
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;

class PromotionListAPIController extends PubControllerAPI
{
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
        $featuredRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                          ->setKeyPrefix($cacheContext . '-featured');
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');

        try {
            $this->checkAuth();
            $user = $this->api->user;

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
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('promotion');
            $skip = PaginationNumber::parseSkipFromGet();

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
                'with_premium' => $withPremium, 'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,

            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $promotionHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $withMallId = '';
            if (! empty($mallId)) {
                $withMallId = "AND (CASE WHEN om.object_type = 'tenant' THEN oms.merchant_id ELSE om.merchant_id END) = {$this->quote($mallId)}";
            }

            $advert_location_type = 'gtm';
            $advert_location_id = '0';
            if (! empty($mallId)) {
                $advert_location_type = 'mall';
                $advert_location_id = $mallId;
            }

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone

            $adverts = Advert::select('adverts.advert_id',
                                    'adverts.link_object_id',
                                    'advert_placements.placement_type',
                                    'advert_placements.placement_order',
                                    'advert_locations.location_type',
                                    'advert_link_types.advert_link_name')
                            ->join('advert_link_types', function ($q) {
                                $q->on('advert_link_types.advert_link_name', '=', DB::raw("'Promotion'"));
                                $q->on('advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id');
                            })
                            ->join('advert_locations', function ($q) use ($advert_location_id, $advert_location_type) {
                                $q->on('advert_locations.location_type', '=', DB::raw("'" . $advert_location_type . "'"));
                                $q->on('advert_locations.location_id', '=', DB::raw("'" . $advert_location_id . "'"));
                                $q->on('advert_locations.advert_id', '=', 'adverts.advert_id');
                            })
                            ->join('advert_placements', function ($q) use ($list_type) {
                                $q->on('advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id');
                                if ($list_type === 'featured') {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('featured_list', 'preferred_list_regular', 'preferred_list_large')"));
                                } else {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('preferred_list_regular', 'preferred_list_large')"));
                                }
                            })
                            ->where('adverts.start_date', '<=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.end_date', '>=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.status', '=', DB::raw("'active'"));

            $advertSql = $adverts->toSql();
            foreach($adverts->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : $this->quote($binding);
              $advertSql = preg_replace('/\?/', $value, $advertSql, 1);
            }

            $promotions = News::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
                                CASE WHEN advert_media.path is null THEN
                                    CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END
                                ELSE advert_media.path END
                                as image_url
                            "),
                            'news.object_type',
                            // query for get status active based on timezone
                            DB::raw("
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                            THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                        FROM {$prefix}news_merchant onm
                                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                        WHERE onm.news_id = {$prefix}news.news_id
                                                                                        {$withMallId})
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                    CASE WHEN (SELECT count(onm.merchant_id)
                                                FROM {$prefix}news_merchant onm
                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                WHERE onm.news_id = {$prefix}news.news_id
                                                {$withMallId}
                                                AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                    THEN 'true' ELSE 'false' END AS is_started
                                "),
                            DB::raw("advert.placement_type, advert.placement_order"),
                            'news.created_at',
                            'news.begin_date')
                            ->leftJoin('news_translations', function ($q) use ($valid_language) {
                                $q->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                                $q->on('news_translations.news_id', '=', 'news.news_id');
                            })
                            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                            ->leftJoin('media', function ($q) {
                                $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                                $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            })
                            ->leftJoin(DB::raw("({$advertSql}) as advert"), DB::raw("advert.link_object_id"), '=', 'news.news_id')
                            ->leftJoin('media as advert_media', function ($q) {
                                $q->on(DB::raw("advert_media.media_name_long"), '=', DB::raw("'advert_image_orig'"));
                                $q->on(DB::raw("advert_media.object_id"), '=', DB::raw("advert.advert_id"));
                            })
                            ->whereRaw("{$prefix}news.object_type = 'promotion'")
                            ->whereRaw("{$prefix}news.status = 'active'")
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->orderBy(DB::raw("advert.placement_order"), 'desc')
                            ->orderBy('news_name', 'asc');

            // left join when need link to mall
            if ($sort_by == 'location' || ! empty($category_id) || ! empty($mallId) || ! empty($location)) {
                $promotions = $promotions->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants as m', function ($q) {
                                        $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                                        $q->on(DB::raw("m.merchant_id"), '=', 'news_merchant.merchant_id');
                                    });
            }

            //calculate distance if user using my current location as filter and sort by location for listing
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

                if (! empty($lon) && ! empty($lat)) {
                    $promotions = $promotions->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance")
                                                )
                                            ->Join('merchant_geofences', function ($q) use($prefix) {
                                                        $q->on('merchant_geofences.merchant_id', '=',
                                                        DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END"));
                                                });
                }
            }

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($promotions, $prefix, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $promotions = $promotions->whereIn(DB::raw("m.object_type"), $category_id);
                } else {
                    $promotions = $promotions->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                    $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                                })
                        ->whereIn(DB::raw('cm.category_id'), $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($promotions, $prefix, &$searchFlag, &$cacheKey) {
                $cacheKey['partner_id'] = $partner_id;
                $searchFlag = $searchFlag || TRUE;
                $promotions = ObjectPartnerBuilder::getQueryBuilder($promotions, $partner_id, 'promotion');
            });

            // filter promotions by mall id
            OrbitInput::get('mall_id', function($mallid) use ($promotions) {
                $promotions->where(function($q) use($mallid) {
                            $q->where(DB::raw("m.parent_id"), '=', $mallid)
                                ->orWhere(DB::raw("m.merchant_id"), '=', $mallid);
                        })
                        ->where('news.object_type', '=', 'promotion');
            });

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            // filter by city
            OrbitInput::get('location', function($location) use ($promotions, $prefix, $lon, $lat, $userLocationCookieName, $distance, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $promotions = $promotions->leftJoin('merchants as mp', function($q) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("m.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                            });

                if ($location === 'mylocation' && !empty($lon) && !empty($lat)) {
                    $promotions = $promotions->havingRaw("distance <= {$distance}");
                } else {
                    $promotions = $promotions->where(DB::raw("(CASE WHEN m.object_type = 'tenant' THEN mp.city ELSE m.city END)"), $location);
                }
            });

            $querySql = $promotions->toSql();

            $promotion = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($promotions->getQuery());

            if ($list_type === 'featured') {
                $promotion = $promotion->select(DB::raw("sub_query.news_id"), 'news_name', 'description',
                                        DB::raw("sub_query.object_type"), 'image_url', 'campaign_status',
                                        'is_started', 'placement_order',
                                        DB::raw("sub_query.created_at"),
                                        DB::raw("sub_query.begin_date"),
                                        DB::raw("placement_type AS placement_type_orig"),
                                        DB::raw("CASE WHEN SUM(
                                                CASE
                                                    WHEN (placement_type = 'preferred_list_regular' OR placement_type = 'preferred_list_large')
                                                    THEN 1
                                                    ELSE 0
                                                END) > 0
                                            THEN 'preferred_list_large'
                                            ELSE placement_type
                                            END AS placement_type")
                                    )
                                    ->groupBy(DB::Raw("sub_query.news_id"));
            } else {
                $promotion = $promotion->select(DB::raw("sub_query.news_id"), 'news_name', 'description',
                                            DB::raw("sub_query.object_type"), 'image_url', 'campaign_status',
                                            'is_started', 'placement_type', 'placement_order', DB::raw("sub_query.created_at"), DB::raw("sub_query.begin_date"))
                                        ->groupBy(DB::Raw("sub_query.news_id"));
            }

            if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
                $searchFlag = $searchFlag || TRUE;
                $promotion = $promotion->addSelect(DB::raw("min(distance) as distance"))
                                    ->orderBy('placement_order', 'desc')
                                    ->orderBy('distance', 'asc');
            } else {
                $promotion = $promotion->orderBy('placement_order', 'desc');
            }

            if ($sort_by !== 'location') {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'            => 'news_name',
                    'created_date'    => 'begin_date',
                );

                $sort_by = $sortByMapping[$sort_by];
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            if ($sort_by !== 'location') {
                $promotion = $promotion->orderBy($sort_by, $sort_mode);
            }

            OrbitInput::get('keyword', function($keyword) use ($promotion, $prefix, &$searchFlag, &$cacheKey) {
                $cacheKey['keyword'] = $keyword;

                $searchFlag = $searchFlag || TRUE;
                if (! empty($keyword)) {
                    $promotion = $promotion->leftJoin('keyword_object', DB::Raw("sub_query.news_id"), '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix){
                                    //Search per word
                                    $words = explode(' ', $keyword);
                                    foreach ($words as $key => $word) {
                                        if (strlen($word) === 1 && $word === '%') {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->whereRaw("sub_query.news_name like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword = '|{$word}' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->where(DB::raw('sub_query.news_name'), 'like', '%' . $word . '%')
                                                  ->orWhere('keywords.keyword', '=', $word);
                                            });
                                        }
                                    }
                                });
                }
            });

            OrbitInput::get('filter_name', function ($filterName) use ($promotion, $prefix, &$cacheKey) {
                $cacheKey['filter_name'] = $filterName;

                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $promotion->whereRaw("SUBSTR(sub_query.news_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $promotion->whereRaw("SUBSTR(sub_query.news_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Promotion',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name'),
                    'partner' => OrbitInput::get('partner_id', NULL)
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
            }

            $_promotion = clone($promotion);
            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);

            $totalRec = 0;
            // Set defaul 0 when get variable no_total_records = yes
            if ($no_total_records !== 'yes') {
                $recordCounter = RecordCounter::create($_promotion);

                // Try to get the result from cache
                $totalRec = $totalRecordCache->get($serializedCacheKey, function() use ($recordCounter) {
                    return $recordCounter->count();
                });
                // Put the result in cache if it is applicable
                $totalRecordCache->put($serializedCacheKey, $totalRec);
            }

            $promotion->take($take);
            $promotion->skip($skip);

            // Try to get the result from cache
            $listOfRec = $recordCache->get($serializedCacheKey, function() use ($promotion) {
                return $promotion->get();
            });
            $recordCache->put($serializedCacheKey, $listOfRec);

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listOfRec;

            // random featured adv
            // @todo fix for random -- this is not the right way to do random, it could lead to memory leak
            if ($list_type === 'featured') {
                $randomPromotionBuilder = clone $_promotion;
                // Take 100 value right now to prevent memory leak
                $randomPromotionBuilder->whereRaw("placement_type = 'featured_list'")->take(100);

                $randomPromotion = $featuredRecordCache->get($serializedCacheKey, function() use ($randomPromotionBuilder) {
                    return $randomPromotionBuilder->get();
                });
                $featuredRecordCache->put($serializedCacheKey, $randomPromotion);

                $advertedCampaigns = array_filter($randomPromotion, function($v) {
                    return ($v->placement_type_orig === 'featured_list');
                });

                if (count($advertedCampaigns) > $take) {
                    $random = array();
                    $listSlide = array_rand($advertedCampaigns, $take);
                    if (count($listSlide) > 1) {
                        foreach ($listSlide as $key => $value) {
                            $random[] = $advertedCampaigns[$value];
                        }
                    } else {
                        $random = $advertedCampaigns[$listSlide];
                    }

                    $data->returned_records = count($listOfRec);
                    $data->total_records = count($random);
                    $data->records = $random;
                }
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