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
use Activity;
use Mall;
use Advert;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Helper\EloquentRecordCounter as RecordCounter;
use \Carbon\Carbon as Carbon;

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
            $this->checkAuth();
            $user = $this->api->user;

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $no_total_records = OrbitInput::get('no_total_records', null);

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                    'list_type'   => $list_type,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date',
                    'list_type'   => 'in:featured,preferred',
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
                                $q->on('advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id');
                                $q->on('advert_link_types.advert_link_name', '=', DB::raw("'Coupon'"));
                            })
                            ->join('advert_locations', function ($q) use ($advert_location_id, $advert_location_type) {
                                $q->on('advert_locations.advert_id', '=', 'adverts.advert_id');
                                $q->on('advert_locations.location_id', '=', DB::raw("'" . $advert_location_id . "'"));
                                $q->on('advert_locations.location_type', '=', DB::raw("'" . $advert_location_type . "'"));
                            })
                            ->join('advert_placements', function ($q) use ($list_type) {
                                $q->on('advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id');
                                if ($list_type === 'featured') {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('featured_list', 'preferred_list_regular', 'preferred_list_large')"));
                                } else {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('preferred_list_regular', 'preferred_list_large')"));
                                }
                            })
                            ->where('adverts.status', '=', DB::raw("'active'"))
                            ->where('adverts.start_date', '<=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.end_date', '>=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"));

            $advertSql = $adverts->toSql();
            foreach($adverts->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : $this->quote($binding);
              $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            $coupons = Coupon::select(DB::raw("{$prefix}promotions.promotion_id as coupon_id,
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
                                        CASE WHEN advert_media.path is null THEN
                                            CASE WHEN {$prefix}media.path is null THEN (
                                                select m.path
                                                from {$prefix}coupon_translations ct
                                                join {$prefix}media m
                                                    on m.object_id = ct.coupon_translation_id
                                                    and m.media_name_long = 'coupon_translation_image_orig'
                                                where ct.promotion_id = {$prefix}promotions.promotion_id
                                                group by ct.promotion_id
                                            ) ELSE {$prefix}media.path END
                                        ELSE advert_media.path END
                                        as image_url
                                    "),
                            DB::raw("advert.placement_type, advert.placement_order"),
                            'promotions.created_at')
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
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants as t', DB::raw("t.merchant_id"), '=', 'promotion_retailer.retailer_id')
                            ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', DB::raw("t.parent_id"))
                            ->leftJoin(DB::raw("(SELECT promotion_id, COUNT(*) as tot FROM {$prefix}issued_coupons WHERE status = 'available' GROUP BY promotion_id) as available"), DB::raw("available.promotion_id"), '=', 'promotions.promotion_id')
                            ->leftJoin(DB::raw("({$advertSql}) as advert"), DB::raw("advert.link_object_id"), '=', 'promotions.promotion_id')
                            ->leftJoin('media as advert_media', function ($q) {
                                $q->on(DB::raw("advert_media.object_id"), '=', DB::raw("advert.advert_id"));
                                $q->on(DB::raw("advert_media.media_name_long"), '=', DB::raw("'advert_image_orig'"));
                            })
                            ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                            ->whereRaw("available.tot > 0")
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->orderBy(DB::raw("advert.placement_order"), 'desc')
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
                                            $q->on('merchant_geofences.merchant_id', '=', DB::raw("CASE WHEN t.object_type = 'tenant' THEN t.parent_id ELSE t.merchant_id END"));
                                    });
                }
            }

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($coupons, $prefix, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                if ($category_id === 'mall') {
                    $coupons = $coupons->where(DB::raw("t.object_type"), $category_id);
                } else {
                    $coupons = $coupons->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("t.merchant_id"));
                                    $q->on(DB::raw("t.object_type"), '=', DB::raw("'tenant'"));
                                })
                        ->where(DB::raw('cm.category_id'), $category_id);
                }
            });

            // filter by city
            OrbitInput::get('location', function($location) use ($coupons, $prefix, $lat, $lon, $distance, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $coupons = $coupons->leftJoin('merchants as mp', function($q) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("t.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("mp.status"), '=', DB::raw("'active'"));
                            });

                if ($location === 'mylocation' && !empty($lon) && !empty($lat)) {
                    $coupons = $coupons->havingRaw("distance <= {$distance}");
                } else {
                    $coupons = $coupons->where(DB::raw("(CASE WHEN t.object_type = 'tenant' THEN mp.city ELSE t.city END)"), $location);
                }
            });

            $querySql = $coupons->toSql();
            $coupon = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($coupons->getQuery());

            if ($list_type === 'featured') {
                $coupon = $coupon->select('coupon_id', 'coupon_name', DB::raw("sub_query.description"),
                                    DB::raw("sub_query.status"), 'campaign_status', 'is_started',
                                    'image_url', 'placement_order',DB::raw("sub_query.created_at"),
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
                                 ->groupBy('coupon_id');

            } else {
                $coupon = $coupon->select('coupon_id', 'coupon_name', DB::raw("sub_query.description"), DB::raw("sub_query.status"),
                                    'campaign_status', 'is_started', 'image_url',
                                    'placement_type', 'placement_order', DB::raw("sub_query.created_at"))
                                 ->groupBy('coupon_id');
            }

            if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
                $coupon = $coupon->addSelect(DB::raw("min(distance) as distance"))->orderBy('placement_order', 'desc')
                                 ->orderBy('distance', 'asc');

            } else {
                $coupon = $coupon->orderBy('placement_order', 'desc');
            }

            OrbitInput::get('mall_id', function ($mallId) use ($coupon, $prefix, &$mall) {
                $coupon->addSelect(DB::raw("CASE WHEN t.object_type = 'tenant' THEN t.parent_id ELSE t.merchant_id END as mall_id"));
                $coupon->addSelect(DB::raw("CASE WHEN t.object_type = 'tenant' THEN m.name ELSE t.name END as mall_name"));
                $coupon->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'coupon_id')
                    ->leftJoin('merchants as t', DB::raw("t.merchant_id"), '=', 'promotion_retailer.retailer_id')
                    ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', DB::raw("t.parent_id"));
                $coupon->where(function($q) use ($mallId) {
                    $q->where(DB::raw('t.merchant_id'), '=', DB::raw("{$this->quote($mallId)}"));
                    $q->orWhere(DB::raw('m.merchant_id'), '=', DB::raw("{$this->quote($mallId)}"));
                });

                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            if ($sort_by !== 'location') {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'            => 'coupon_name',
                    'created_date'    => 'created_at'
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
                $coupon = $coupon->orderBy($sort_by, $sort_mode);
            }

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

            OrbitInput::get('keyword', function ($keyword) use ($coupon, $prefix, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
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

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Coupon',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name')
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
            }
            $_coupon = clone $coupon;


            $totalRec = 0;
            // Set defaul 0 when get variable no_total_records = yes
            if ($no_total_records === 'yes') {
                $totalRec = 0;
            } else {
                $recordCounter = RecordCounter::create($_coupon);
                OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

                $totalRec = $recordCounter->count();
            }

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($coupon);

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $coupon->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();

            $data = new \stdclass();
            $data->returned_records = count($listcoupon);
            $data->total_records = $totalRec;
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listcoupon;

            // random featured adv
            $randomCoupon = $_coupon->get();
            if ($list_type === 'featured') {
                $advertedCampaigns = array_filter($randomCoupon, function($v) {
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

                    $data->returned_records = count($listcoupon);
                    $data->total_records = count($random);
                    $data->records = $random;
                }
            }

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall coupon list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_coupon_list')
                            ->setActivityNameLong('View mall coupon list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Coupon')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
