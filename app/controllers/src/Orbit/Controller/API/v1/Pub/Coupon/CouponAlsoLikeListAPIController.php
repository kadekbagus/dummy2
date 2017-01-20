<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

/**
 * @author Irianto <irianto@dominopos.com>
 * @desc Controller for coupon list you might also like
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Coupon;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DB;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Validator;
use Activity;
use Mall;
use Advert;
use Lang;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;

class CouponAlsoLikeListAPIController extends PubControllerAPI
{
    /**
     * GET - get coupon you might also like
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
    public function getCouponList()
    {
        $httpCode = 200;
        $mall = NULL;
        $user = NULL;

        try {
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
                $coupon_clp = $this->generateQuery($param);

                $param['category_id'] = null;
            }

            $coupon_dplp = $this->generateQuery($param);

            $param['partner_id'] = null;
            $param['location'] = null;
            $param['filter'] = 'N'; // pass filter category

            $coupon_all = $this->generateQuery($param);

            if (! empty($category_id)) {
                $coupon_union = $coupon_clp->union($coupon_dplp)->union($coupon_all);
            } else {
                $coupon_union = $coupon_dplp->union($coupon_all);
            }

            // coupon union subquery to take and skip
            $couponSql = $coupon_union->toSql();
            $coupon = DB::table(DB::raw("({$couponSql}) as coupon_union"))->mergeBindings($coupon_union);

            $totalRec = 0;
            // Set defaul 0 when get variable show_total_record = yes
            if ($show_total_record === 'yes') {
                $_coupon = clone $coupon;

                $recordCounter = RecordCounter::create($_coupon);
                OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

                $totalRec = $recordCounter->count();
            }

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($coupon);

            $take = PaginationNumber::parseTakeFromGet('you_might_also_like');
            $coupon->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $localPath = '';
            $cdnPath = '';
            $listId = '';

            if (! empty($listcoupon)) {
                foreach ($listcoupon as $list) {
                    if ($listId != $list->coupon_id) {
                        $localPath = '';
                        $cdnPath = '';
                        $list->image_url = '';
                    }
                    $localPath = (! empty($list->localPath)) ? $list->localPath : $localPath;
                    $cdnPath = (! empty($list->cdnPath)) ? $list->cdnPath : $cdnPath;
                    $list->image_url = $imgUrl->getImageUrl($localPath, $cdnPath);
                    $listId = $list->coupon_id;
                }
            }

            $data = new \stdclass();
            $data->returned_records = count($listcoupon);
            $data->total_records = $totalRec;
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listcoupon;

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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function generateQuery($param = array()) {
        $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
        $distance = Config::get('orbit.geo_location.distance', 10);
        $sort_by = 'created_date';
        $sort_mode = 'desc';
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

        $couponHelper = CouponHelper::create();
        $couponHelper->couponCustomValidator();
        // search by key word or filter or sort by flag
        $searchFlag = FALSE;

        $validator = Validator::make(
            array(
                'language'  => $language,
                'except_id' => $except_id,
                'sortby'    => $sort_by,
            ),
            array(
                'language' => 'required|orbit.empty.language_default',
                'except_id' => 'required', // need check coupon id is exists
                'sortby'   => 'in:name,location,created_date',
            ),
            array(
            )
        );

        // Run the validation
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $valid_language = $couponHelper->getValidLanguage();

        $prefix = DB::getTablePrefix();
        $withMallId = '';
        if (! empty($mallId)) {
            $withMallId = "AND (CASE WHEN om.object_type = 'tenant' THEN oms.merchant_id ELSE om.merchant_id END) = {$this->quote($mallId)}";
        }

        $coupons = DB::table('promotions')->select(DB::raw("{$prefix}promotions.promotion_id as coupon_id,
                            CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                            CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
                            {$prefix}promotions.status,
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                FROM {$prefix}promotion_retailer opt
                                                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                                    {$withMallId})
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                            CASE WHEN (SELECT count(opt.promotion_retailer_id)
                                        FROM {$prefix}promotion_retailer opt
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        {$withMallId}
                                        AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                            THEN 'true' ELSE 'false' END AS is_started"),
                            DB::raw("
                                    CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}coupon_translations ct
                                        join {$prefix}media m
                                            on m.object_id = ct.coupon_translation_id
                                            and m.media_name_long = 'coupon_translation_image_orig'
                                        where ct.promotion_id = {$prefix}promotions.promotion_id
                                        group by ct.promotion_id
                                    ) ELSE {$prefix}media.cdn_url END as localPath,
                                    CASE WHEN {$prefix}media.cdn_url is null THEN (
                                        select m.cdn_url
                                        from {$prefix}coupon_translations ct
                                        join {$prefix}media m
                                            on m.object_id = ct.coupon_translation_id
                                            and m.media_name_long = 'coupon_translation_image_orig'
                                        where ct.promotion_id = {$prefix}promotions.promotion_id
                                        group by ct.promotion_id
                                    ) ELSE {$prefix}media.cdn_url END as cdnPath
                                "),
                            'promotions.begin_date')
                        ->leftJoin('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                        ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                            $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                              ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                        })
                        ->leftJoin(DB::raw("(SELECT promotion_id, COUNT(*) as tot FROM {$prefix}issued_coupons WHERE status = 'available' GROUP BY promotion_id) as available"), DB::raw("available.promotion_id"), '=', 'promotions.promotion_id')
                        ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                        ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                        ->whereRaw("available.tot > 0")
                        ->whereRaw("{$prefix}promotions.status = 'active'")
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->orderBy('coupon_name', 'asc');

        // left join when need link to mall
        if ($filter === 'Y' || ! empty($mallId) || $sort_by == 'location') {
            $coupons = $coupons->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants as m', function ($q) {
                            $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                            $q->on(DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id');
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

            if (!empty($lon) && !empty($lat)) {
                $coupons = $coupons->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                ->leftJoin('merchant_geofences', function ($q) use($prefix) {
                                        $q->on('merchant_geofences.merchant_id', '=', DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END"));
                                });
            }
        }

        $coupons = $coupons->where('promotions.promotion_id', '!=', $except_id);

        // filter by category_id
        if ($filter === 'Y') {
            if (empty($category_id)) {
                $coupons = $coupons->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                    $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                                })
                                ->whereRaw("
                                    EXISTS (
                                        SELECT 1
                                        FROM {$prefix}promotions pc
                                        JOIN {$prefix}promotion_retailer prc
                                            ON prc.promotion_id = pc.promotion_id
                                        LEFT JOIN {$prefix}merchants te
                                            ON te.merchant_id = prc.retailer_id
                                            AND te.object_type = 'tenant'
                                        LEFT JOIN {$prefix}category_merchant ctm
                                            ON ctm.merchant_id = te.merchant_id
                                        WHERE pc.promotion_id = '{$except_id}'
                                            AND ctm.category_id != ''
                                            AND cm.category_id = ctm.category_id
                                        GROUP BY cm.category_id)
                                ");
            } else {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                $coupons = $coupons->leftJoin('category_merchant as cm', function($q) {
                                $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                            })
                    ->whereIn(DB::raw('cm.category_id'), $category_id);
            }
        }

        if (! empty($partner_id)) {
            $coupons = ObjectPartnerBuilder::getQueryBuilder($coupons, $partner_id, 'coupon');
        }

        if (! empty($mallId)) {
            // filter coupon by mall id
            $coupons = $coupons->where(function($q) use ($mallId){
                        $q->where(DB::raw("m.parent_id"), '=', $mallId)
                          ->orWhere(DB::raw("m.merchant_id"), '=', $mallId);
                    });
        }

         // frontend need the mall name
         $mall = null;
         if (! empty($mallId)) {
            $mall = Mall::where('merchant_id', '=', $mallId)->first();
         }

        // filter by city
         if (! empty($location)) {
            $coupons = $coupons->leftJoin('merchants as mp', function($q) {
                            $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("m.parent_id"));
                            $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                            $q->on(DB::raw("mp.status"), '=', DB::raw("'active'"));
                        });

            if ($location === 'mylocation' && !empty($lon) && !empty($lat)) {
                $coupons = $coupons->havingRaw("distance <= {$distance}");
            } else {
                $coupons = $coupons->where(DB::raw("(CASE WHEN m.object_type = 'tenant' THEN mp.city ELSE m.city END)"), $location);
            }
         }

        // first subquery
        $querySql = $coupons->toSql();
        $coupons = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($coupons);

        if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
            $coupons = $coupons->select('coupon_id', 'coupon_name', DB::raw("sub_query.description"), DB::raw("sub_query.status"), 'localPath', 'cdnPath', 'campaign_status', 'is_started', DB::raw("min(distance) as distance"), DB::raw("sub_query.begin_date"))
                                   ->orderBy('distance', 'asc');
        } else {
            $coupons = $coupons->select('coupon_id', 'coupon_name', DB::raw("sub_query.description"), DB::raw("sub_query.status"), 'localPath', 'cdnPath', 'campaign_status', 'is_started', DB::raw("sub_query.begin_date"));
        }

        $coupons = $coupons->groupBy(DB::Raw("coupon_id"));

        if ($sort_by !== 'location') {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'name'         => 'coupon_name',
                'created_date' => 'begin_date'
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
            $coupons = $coupons->orderBy($sort_by, $sort_mode);
        }

        $take = Config::get('orbit.pagination.you_might_also_like.max_record');
        $coupons->take($take);

        // second subquery merging for keep sort by before union
        $_coupons = clone $coupons;
        $_querysql = $_coupons->toSql();
        $_coupons = DB::table(DB::raw("({$_querysql}) as coupons_subquery"))->mergeBindings($_coupons);

        return $_coupons;
    }
}
