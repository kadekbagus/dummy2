<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

/**
 * @author Irianto <irianto@dominopos.com>
 * @desc Controller for promotion list you might also like
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

class PromotionAlsoLikeListAPIController extends PubControllerAPI
{
    /**
     * GET - get active promotion you might also like
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPromotion()
    {
        $httpCode = 200;
        $user = null;
        $mall = null;

        try{
            $this->checkAuth();
            $user = $this->api->user;
            $show_total_record = OrbitInput::get('show_total_record', null);
            // variable for function
            $except_id = OrbitInput::get('except_id');
            $category_id = OrbitInput::get('category_id');
            $partner_id = OrbitInput::get('partner_id');
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);

            $param = [
                'except_id'   => $except_id,
                'category_id' => $category_id,
                'partner_id'  => $partner_id,
                'location'    => $location,
                'ul'          => $ul,
                'lon'         => $lon,
                'lat'         => $lat,
                'mallId'      => $mallId,
                'filter'      => 'Y',
            ];

            if (! empty($category_id)) {
                $promotion_clp = $this->generateQuery($param);

                $param['category_id'] = null;
            }

            $promotion_dplp = $this->generateQuery($param);

            $param['partner_id'] = null;
            $param['location'] = null;
            $param['filter'] = 'N'; // pass filter category
            $promotion_nofilter = $this->generateQuery($param);

            if (! empty($category_id)) {
                $promotion_union = $promotion_clp->union($promotion_dplp)->union($promotion_nofilter);
            } else {
                $promotion_union = $promotion_dplp->union($promotion_nofilter);
            }

            // promotion union subquery to take and skip
            $promotionSql = $promotion_union->toSql();
            $promotion = DB::table(DB::raw("({$promotionSql}) as promotion_union"))->mergeBindings($promotion_union);

            $totalRec = 0;
            // Set defaul 0 when get variable no_total_records = yes
            if ($show_total_record === 'yes') {
                $_promotion = clone($promotion);

                $recordCounter = RecordCounter::create($_promotion);
                OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

                $totalRec = $recordCounter->count();
            }

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($promotion);

            $take = PaginationNumber::parseTakeFromGet('you_might_also_like');
            $promotion->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotion->skip($skip);

            $listOfRec = $promotion->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listOfRec;

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

    protected function generateQuery($param = array()) {
        $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
        $distance = Config::get('orbit.geo_location.distance', 10);
        $sort_by = OrbitInput::get('sortby', 'created_date');
        $sort_mode = OrbitInput::get('sortmode','desc');
        $language = OrbitInput::get('language', 'id');

        $except_id   = $param['except_id'];
        $category_id = $param['category_id'];
        $partner_id  = $param['partner_id'];
        $location    = $param['location'];
        $ul          = $param['ul'];
        $lon         = $param['lon'];
        $lat         = $param['lat'];
        $mallId      = $param['mallId'];
        $filter      = $param['filter'];

        $promotionHelper = PromotionHelper::create();
        $promotionHelper->registerCustomValidation();
        $validator = Validator::make(
            array(
                'language'  => $language,
                'except_id' => $except_id,
                'sortby'    => $sort_by,
            ),
            array(
                'language'  => 'required|orbit.empty.language_default',
                'except_id' => 'required', //need check promotion id is exists
                'sortby'    => 'in:name,location,created_date',
            )
        );

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

        $promotions = News::select(
                        'news.news_id as news_id',
                        DB::Raw("
                            CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                            CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
                            CASE WHEN {$prefix}media.path is null THEN (
                                select m.path
                                from {$prefix}news_translations nt
                                join {$prefix}media m
                                    on m.object_id = nt.news_translation_id
                                    and m.media_name_long = 'news_translation_image_orig'
                                where nt.news_id = {$prefix}news.news_id
                                group by nt.news_id
                            ) ELSE {$prefix}media.path END as image_url
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
                        'news.created_at')
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            $q->on('news_translations.news_id', '=', 'news.news_id');
                        })
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                        })
                        ->whereRaw("{$prefix}news.object_type = 'promotion'")
                        ->whereRaw("{$prefix}news.status = 'active'")
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->orderBy('news_name', 'asc');

        // left join when need link to mall
        if ($filter === 'Y') {
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

        // except promotion on detail page
        $promotions = $promotions->where('news.news_id', '!=', $except_id);

        if ($filter === 'Y') {
            if (empty($category_id)) {
                // handle if category is empty will find with same of category on detail page
                $promotions = $promotions->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                    $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                                })
                            ->whereRaw("
                                EXISTS (
                                    SELECT 1
                                    FROM {$prefix}news nw
                                    JOIN {$prefix}news_merchant nwm
                                        ON nwm.news_id = nw.news_id
                                    LEFT JOIN {$prefix}merchants te
                                        ON te.merchant_id = nwm.merchant_id
                                        AND te.object_type = 'tenant'
                                    LEFT JOIN {$prefix}category_merchant ctm
                                        ON ctm.merchant_id = te.merchant_id
                                    WHERE nw.news_id = '{$except_id}'
                                        AND ctm.category_id != ''
                                        AND cm.category_id = ctm.category_id
                                    GROUP BY cm.category_id)
                            ");
            } else {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                $promotions = $promotions->leftJoin('category_merchant as cm', function($q) {
                                $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                            })
                            ->whereIn(DB::raw('cm.category_id'), $category_id);
            }
        }

        if (! empty($partner_id)) {
            $promotions = ObjectPartnerBuilder::getQueryBuilder($promotions, $partner_id, 'promotion');
        }

        if (! empty($mallId)) {
            $promotions = $promotions->where(function($q) use ($mallId) {
                                $q->where(DB::raw("m.parent_id"), '=', $mallId)
                                    ->orWhere(DB::raw("m.merchant_id"), '=', $mallId);
                            })
                            ->where('news.object_type', '=', 'promotion');
        }

        // frontend need the mall name
        $mall = null;
        if (! empty($mallId)) {
            $mall = Mall::where('merchant_id', '=', $mallId)->first();
        }

        // filter by city
        if (! empty($location)) {
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
        }

        // first subquery
        $querySql = $promotions->toSql();
        $promotions_query = $promotions->getQuery();
        $promotions = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($promotions_query);

        if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
            $promotions = $promotions->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started', DB::raw("min(distance) as distance"), DB::raw("sub_query.created_at"))
                                   ->orderBy('distance', 'asc');
        } else {
            $promotions = $promotions->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started', DB::raw("sub_query.created_at"));
        }

        $promotions = $promotions->groupBy(DB::Raw("sub_query.news_id"));

        if ($sort_by !== 'location') {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'name'            => 'news_name',
                'created_date'    => 'created_at',
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
            $promotions = $promotions->orderBy($sort_by, $sort_mode);
        }

        $take = Config::get('orbit.pagination.you_might_also_like.max_record');
        $promotions->take($take);

        // second subquery merging for keep sort by before union
        $_promotions = clone $promotions;
        $_querysql = $_promotions->toSql();
        $_promotions = DB::table(DB::raw("({$_querysql}) as promotions_subquery"))->mergeBindings($_promotions);

        return $_promotions;
    }
}