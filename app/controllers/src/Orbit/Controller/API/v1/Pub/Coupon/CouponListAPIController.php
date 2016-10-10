<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Coupon;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use OrbitShop\API\v1\ResponseProvider;
use Activity;
use Mall;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;

class CouponListAPIController extends ControllerAPI
{
    /**
     * GET - get all coupon in all mall
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $mall = NULL;
        $user = NULL;
        $httpCode = 200;
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $sort_by = OrbitInput::get('sortby', 'coupon_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
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

            $coupons = Coupon::select(DB::raw("{$prefix}promotions.promotion_id as coupon_id,
                                CASE WHEN {$prefix}coupon_translations.promotion_name = '' THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                                CASE WHEN {$prefix}coupon_translations.description = '' THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
                                {$prefix}promotions.status,
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name
                                    ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}promotion_retailer opt
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE opt.promotion_id = {$prefix}promotions.promotion_id)
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(opt.promotion_retailer_id)
                                            FROM {$prefix}promotion_retailer opt
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE opt.promotion_id = {$prefix}promotions.promotion_id
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
                                            ) ELSE {$prefix}media.path END as image_url
                                    "),
                            'promotions.created_at')
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->where('coupon_translations.merchant_language_id', $valid_language->language_id)
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->orderBy('coupon_name', 'asc');

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

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($coupons, $prefix) {
                if ($category_id === 'mall') {
                    $coupons = $coupons->where(DB::raw("m.object_type"), $category_id);
                } else {
                    $coupons = $coupons->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                    $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                                })
                        ->where(DB::raw('cm.category_id'), $category_id);
                }
            });

            // filter by city
            OrbitInput::get('location', function($location) use ($coupons, $prefix, $lat, $lon, $distance) {
                $coupons = $coupons->leftJoin('merchants as mp', function($q) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("t.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                            });

                if ($location === 'mylocation' && !empty($lon) && !empty($lat)) {
                    $coupons = $coupons->havingRaw("distance <= {$distance}");
                } else {
                    $coupons = $coupons->where(DB::raw("(CASE WHEN t.object_type = 'tenant' THEN mp.city ELSE t.city END)"), $location);
                }
            });

            $querySql = $coupons->toSql();
            $coupon = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($coupons->getQuery());

            if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
                $coupon = $coupon->select('coupon_id', 'coupon_name', DB::raw("sub_query.description"), DB::raw("sub_query.status"), 'campaign_status', 'is_started', 'image_url', DB::raw("min(distance) as distance"))
                                 ->groupBy('coupon_id')
                                 ->orderBy('distance', 'asc');
            } else {
                $coupon = $coupon->select('coupon_id', 'coupon_name', DB::raw("sub_query.description"), DB::raw("sub_query.status"), 'campaign_status', 'is_started', 'image_url')
                                 ->groupBy('coupon_id');
            }

            OrbitInput::get('mall_id', function ($mallId) use ($coupon, $prefix, &$mall) {
                $coupon->addSelect(DB::raw('m.merchant_id as mall_id'));
                $coupon->addSelect(DB::raw('m.name as mall_name'));
                $coupon->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'coupon_id')
                    ->leftJoin('merchants as t', DB::raw("t.merchant_id"), '=', 'promotion_retailer.retailer_id')
                    ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', DB::raw("t.parent_id"));
                $coupon->where(DB::raw('m.merchant_id'), '=', DB::raw("{$this->quote($mallId)}"));

                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            OrbitInput::get('sortby', function($_sortBy) use (&$sort_by)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'            => 'coupon_name',
                    'created_date'    => 'created_at'
                );

                $sort_by = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            $coupon = $coupon->orderBy($sort_by, $sort_mode);

            OrbitInput::get('filter_name', function ($filterName) use ($coupon, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $coupon->whereRaw("SUBSTR(sub_query.coupon_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $coupon->whereRaw("SUBSTR(sub_query.coupon_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            OrbitInput::get('keyword', function ($keyword) use ($coupon, $prefix) {
                if (! empty($keyword)) {
                    $coupon = $coupon->leftJoin('keyword_object', DB::Raw("sub_query.coupon_id"), '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix)
                                {
                                    $word = explode(" ", $keyword);
                                    foreach ($word as $key => $value) {
                                        if (strlen($value) === 1 && $value === '%') {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->whereRaw("sub_query.coupon_name like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("sub_query.description like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$value}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->where(DB::raw('sub_query.coupon_name'), 'like', '%' . $value . '%')
                                                  ->orWhere(DB::raw('sub_query.description'), 'like', '%' . $value . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $value . '%');
                                            });
                                        }
                                    }
                                });
                }
            });

            $_coupon = clone $coupon;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $coupon->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();
            $count = count($_coupon->get());

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                $activityNotes = sprintf('Page viewed: Coupon list');
                $activity->setUser($user)
                    ->setActivityName('view_coupons_main_page')
                    ->setActivityNameLong('View Coupons Main Page')
                    ->setObject(null)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listcoupon);
            $this->response->data->records = $listcoupon;
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
}
