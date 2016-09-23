<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Coupon;
use PromotionRetailer;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use URL;
use Validator;
use OrbitShop\API\v1\ResponseProvider;
use Activity;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Language;
use Lang;
use CouponRetailer;
use Carbon\Carbon;
use IssuedCoupon;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Helper\Security\Encrypter;
use \Queue;
use \App;
use \Exception;
use \UserVerificationNumber;

class CouponAPIController extends ControllerAPI
{

    protected $valid_language = NULL;

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
        $httpCode = 200;
        try {
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

            $this->registerCustomValidation();
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

            $valid_language = $this->valid_language;

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
                                    "))
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
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
                $coupons = $coupons->leftJoin('category_merchant as cm', function($q) {
                                $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                            })
                ->where(DB::raw('cm.category_id'), $category_id);
            });

            // filter by city
            OrbitInput::get('location', function($location) use ($coupons, $prefix, $lat, $lon, $distance) {
                $coupons = $coupons->leftJoin('merchants as mp', function($q) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("m.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                            });

                if ($location === 'mylocation' && !empty($lon) && !empty($lat)) {
                    $coupons = $coupons->havingRaw("distance <= {$distance}");
                } else {
                    $coupons = $coupons->where(DB::raw("(CASE WHEN m.object_type = 'tenant' THEN mp.city ELSE m.city END)"), $location);
                }
            });

            $querySql = $coupons->toSql();
            $coupon = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($coupons->getQuery());

            if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
                $sort_by = 'distance';
                $coupon = $coupon->select('coupon_id', 'coupon_name', 'description', DB::raw("sub_query.status"), 'campaign_status', 'is_started', 'image_url', DB::raw("min(distance) as distance"))
                                 ->groupBy('coupon_id')
                                 ->orderBy($sort_by, $sort_mode)
                                 ->orderBy('coupon_name', $sort_mode);
            } else {
                $coupon = $coupon->select('coupon_id', 'coupon_name', 'description', DB::raw("sub_query.status"), 'campaign_status', 'is_started', 'image_url')
                                 ->groupBy('coupon_id')
                                 ->orderBy('coupon_name', $sort_mode);
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

    /**
     * GET - get mall list after click store name
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
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallCouponList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $couponId = OrbitInput::get('coupon_id');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                ),
                array(
                    'coupon_id' => 'required',
                ),
                array(
                    'required' => 'Coupon ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $replaceIdPattern = '---REPLACE_ME_WITH_ID---';
            $urlToCI = URL::route('ci-coupon-detail', array('id' => $replaceIdPattern), false);
            $mall = PromotionRetailer::select('promotion_retailer.promotion_id as coupon_id','promotions.begin_date as begin_date','promotions.end_date as end_date',
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.ci_domain ELSE {$prefix}merchants.ci_domain END as ci_domain"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.description ELSE {$prefix}merchants.description END as description"),
                                            DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), REPLACE('{$urlToCI}', '$replaceIdPattern', {$prefix}promotion_retailer.promotion_id)) as coupon_url"),
                                            DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                        FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                        WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                    ) as tz"))
                                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                        ->leftJoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                                        ->where('promotion_retailer.promotion_id', '=', $couponId)
                                        ->groupBy('merchant_id')
                                        ->havingRaw('tz <= end_date AND tz >= begin_date');

            OrbitInput::get('filter_name', function ($filterName) use ($mall, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $mall->whereRaw("SUBSTR((CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END),1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $mall->whereRaw("SUBSTR((CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END),1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            $mall = $mall->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
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
     * POST - add to wallet
     *
     * @param string coupon_id
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function postAddToWallet()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = null;
        $coupon_id = OrbitInput::post('coupon_id', NULL);
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon|orbit.notexists.couponwallet',
                ),
                array(
                    'orbit.exists.coupon' => Lang::get('validation.orbit.empty.coupon'),
                    'orbit.notexists.couponwallet' => 'Coupon already added to wallet'
                )
            );

            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::excludeDeleted()
                ->where('promotion_id', '=', $coupon_id)
                ->first();

            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issue($coupon, $user->user_id, $user);
            $this->commit();

            if ($issuedCoupon) {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
                $activityNotes = sprintf('Added to wallet Coupon Id: %s. Issued Coupon Id: %s', $coupon->promotion_id, $issuedCoupon->issued_coupon_id);
                $activity->setUser($user)
                    ->setActivityName('click_add_to_wallet')
                    ->setActivityNameLong('Click Landing Page Add To Wallet')
                    ->setLocation($retailer)
                    ->setObject($issuedCoupon)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $this->response->message = 'Fail to issue coupon';
                $this->response->data = NULL;
            }

        } catch (ACLForbiddenException $e) {
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Landing Page Add To Wallet')
                ->setObject($coupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Landing Page Add To Wallet Failed')
                ->setObject($issuedCoupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Landing Page Add To Wallet Failed')
                ->setObject($issuedCoupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render();
    }

    /**
     * POST - add coupon to email
     *
     * @param string coupon_id
     * @param string email
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function postAddCouponToEmail()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = null;
        $email = NULL;
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $this->registerCustomValidation();
            $coupon_id = OrbitInput::post('coupon_id');
            $email = OrbitInput::post('email');
            $mallId = OrbitInput::post('mall_id');

            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                    'email' => $email,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon',
                    'email' => 'required|email',
                ),
                array(
                    'orbit.exists.coupon' => Lang::get('validation.orbit.empty.coupon'),
                )
            );

            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::excludeDeleted()
                ->where('promotion_id', '=', $coupon_id)
                ->first();

            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issue($coupon);
            $this->commit();

            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $hashedIssuedCouponCid = rawurlencode($encrypter->encrypt($issuedCoupon->issued_coupon_id));
            $hashedIssuedCouponUid = rawurlencode($encrypter->encrypt($email));

            // cid=%s&uid=%s
            $redeem_url = sprintf(Config::get('orbit.coupon.direct_redemption_url'), $hashedIssuedCouponCid, $hashedIssuedCouponUid);

            // queue to send coupon redemption page url
            Queue::push('Orbit\\Queue\\IssuedCouponMailQueue', [
                'email' => $email,
                'issued_coupon_id' => $issuedCoupon->issued_coupon_id,
                'redeem_url' => $redeem_url
            ]);

            // customize user property before saving activity
            $user = $this->customizeUserProps($user, $email);

            if (! empty($mallId)) {
                $retailer = Mall::excludeDeleted()
                    ->where('merchant_id', $mallId)
                    ->first();
            }

            if ($issuedCoupon) {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
                $activityNotes = sprintf('Issued to email: %s. Coupon Id: %s. Issued Coupon Id: %s', $email, $coupon->promotion_id, $issuedCoupon->issued_coupon_id);
                $activity->setUser($user)
                    ->setActivityName('issue_coupon')
                    ->setActivityNameLong('Issue Coupon by Email')
                    ->setObject($issuedCoupon)
                    ->setLocation($retailer)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $this->response->message = 'Fail to issue coupon';
                $this->response->data = NULL;
                $activityNotes = sprintf('Failed to issue to email: %s. Coupon Id: %s.', $email, $coupon->promotion_id);
                $activity->setUser($user)
                    ->setActivityName('issue_coupon')
                    ->setActivityNameLong('Failed to Issue Coupon by Email')
                    ->setObject($issuedCoupon)
                    ->setLocation($retailer)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseFailed()
                    ->save();
            }

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to email. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('issue_coupon')
                ->setActivityNameLong('Failed to Issue Coupon by Email')
                ->setObject($issuedCoupon)
                ->setLocation($retailer)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to email. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('issue_coupon')
                ->setActivityNameLong('Failed to Issue Coupon by Email')
                ->setObject($issuedCoupon)
                ->setLocation($retailer)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
            $this->rollback();
            $activityNotes = sprintf('Failed to add to email. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('issue_coupon')
                ->setActivityNameLong('Failed to Issue Coupon by Email')
                ->setObject($issuedCoupon)
                ->setLocation($retailer)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render();
    }

    /**
     * GET - get coupon redemption page
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string cid (hashed issued coupon id coming from url in email that sent to user)
     * @param string uid (hashed user identifier coming from url in email that sent to user)
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponItemRedemption()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $issuedCoupon = NULL;
        $coupon = NULL;
        $issuedCouponId = NULL;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $issuedCouponId = OrbitInput::get('cid', NULL);
            $userIdentifier = OrbitInput::get('uid', NULL);
            $validator = Validator::make(
                array(
                    'cid' => $issuedCouponId,
                ),
                array(
                    'cid' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // decrypt hashed coupon id
            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $issuedCouponId = $encrypter->decrypt($issuedCouponId);
            if (! empty($userIdentifier)) {
                $userIdentifier = $encrypter->decrypt($userIdentifier);
            }

            // detect encoding to avoid query error
            if (! mb_detect_encoding($issuedCouponId, 'ASCII', true)) {
                $errorMessage = 'Invalid cid';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN {$prefix}coupon_translations.promotion_name = '' THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN {$prefix}coupon_translations.description = '' THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
                                    CASE WHEN {$prefix}media.path is null THEN (
                                            select m.path
                                            from {$prefix}coupon_translations ct
                                            join {$prefix}media m
                                                on m.object_id = ct.coupon_translation_id
                                                and m.media_name_long = 'coupon_translation_image_orig'
                                            where ct.promotion_id = {$prefix}promotions.promotion_id
                                            group by ct.promotion_id
                                        ) ELSE {$prefix}media.path END as original_media_path
                                "),
                            'promotions.end_date',
                            // query for get status active based on timezone
                            DB::raw("
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                            THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                        FROM {$prefix}promotion_retailer opr
                                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                        WHERE opr.promotion_id = {$prefix}promotions.promotion_id)
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                    CASE WHEN (SELECT count(opr.retailer_id)
                                                FROM {$prefix}promotion_retailer opr
                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                                AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                    THEN 'true' ELSE 'false' END AS is_started
                            ")
                        )
                        ->join('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->leftJoin('media', function($q) {
                            $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                        })
                        ->join('issued_coupons', function ($q) {
                            $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                            $q->on('issued_coupons.status', '=', DB::Raw("'active'"));
                        })
                        ->where('issued_coupons.issued_coupon_id', '=', $issuedCouponId)
                        ->where('coupon_translations.merchant_language_id', '=', $valid_language->language_id)
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->first();

            $message = 'Request Ok';
            if (! is_object($coupon)) {
                OrbitShopAPI::throwInvalidArgument('Issued coupon that you specify is not found');
            }

            // customize user property before saving activity
            $user = $this->customizeUserProps($user, $userIdentifier);

            $issuedCoupon = IssuedCoupon::where('issued_coupon_id', $issuedCouponId)->first();

            $activityNotes = sprintf('Page viewed: Coupon Redemption Page. Issued Coupon Id: %s', $issuedCouponId);
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('View Redemption Page')
                ->setObject($issuedCoupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            $this->response->data = $coupon;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;
        } catch (ACLForbiddenException $e) {
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
                ->setObject($issuedCoupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
                ->setObject($issuedCoupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
                ->setObject($issuedCoupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();

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
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
                ->setObject($issuedCoupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();

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
     * POST - Pub Redeem Coupon
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string      `cid`                             (required) - Hashed issued coupon ID
     * @param string      `uid`                             (optional) - Hashed user identifier
     * @param string      `merchant_id`                     (required) - ID of the mall
     * @param string      `merchant_verification_number`    (required) - Merchant/Tenant verification number
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postPubRedeemCoupon()
    {
        $activity = Activity::mobileci()
                          ->setActivityType('coupon');

        $user = NULL;
        $mall = NULL;
        $mall_id = NULL;
        $issuedcoupon = NULL;
        $coupon = NULL;
        $httpCode = 200;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $this->registerCustomValidation();

            $mallId = OrbitInput::post('mall_id');
            $issuedCouponId = OrbitInput::post('cid'); // hashed issued coupon id
            $userIdentifier = OrbitInput::post('uid', NULL); // hashed user identifier
            $verificationNumber = OrbitInput::post('merchant_verification_number');

            $validator = Validator::make(
                array(
                    'mall_id'                       => $mallId,
                    'cid'                           => $issuedCouponId,
                    'merchant_verification_number'  => $verificationNumber,
                ),
                array(
                    'mall_id'                       => 'required|orbit.empty.merchant',
                    'cid'                           => 'required|orbit.empty.issuedcoupon',
                    'merchant_verification_number'  => 'required'
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($userIdentifier)) {
                $encryptionKey = Config::get('orbit.security.encryption_key');
                $encryptionDriver = Config::get('orbit.security.encryption_driver');
                $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

                $userIdentifier = $encrypter->decrypt($userIdentifier);
            }

            $tenant = Tenant::active()
                ->where('parent_id', $mallId)
                ->where('masterbox_number', $verificationNumber)
                ->first();

            $csVerificationNumber = UserVerificationNumber::
                where('merchant_id', $mallId)
                ->where('verification_number', $verificationNumber)
                ->first();

            $redeem_retailer_id = NULL;
            $redeem_user_id = NULL;
            if (! is_object($tenant) && ! is_object($csVerificationNumber)) {
                // @Todo replace with language
                $message = 'Store is not found.';
                OrbitShopAPI::throwInvalidArgument($message);
            } else {
                if (is_object($tenant)) {
                    $redeem_retailer_id = $tenant->merchant_id;
                }
                if (is_object($csVerificationNumber)) {
                    $redeem_user_id = $csVerificationNumber->user_id;
                }
            }

            $mall = App::make('orbit.empty.merchant');
            $issuedcoupon = App::make('orbit.empty.issuedcoupon');

            // The coupon information
            $coupon = $issuedcoupon->coupon;

            $issuedcoupon->redeemed_date = date('Y-m-d H:i:s');
            $issuedcoupon->redeem_retailer_id = $redeem_retailer_id;
            $issuedcoupon->redeem_user_id = $redeem_user_id;
            $issuedcoupon->redeem_verification_code = $verificationNumber;
            $issuedcoupon->status = 'redeemed';

            $issuedcoupon->save();

            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.coupon');

            // Commit the changes
            $this->commit();

            $this->response->message = 'Coupon has been successfully redeemed.';
            $this->response->data = null;

            // customize user property before saving activity
            $user = $this->customizeUserProps($user, $userIdentifier);

            $activityNotes = sprintf('Coupon Redeemed: %s. Issued Coupon Id: %s.', $issuedcoupon->coupon->promotion_name, $issuedcoupon->issued_coupon_id);
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Successful')
                    ->setObject($issuedcoupon)
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseOK();

        } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($issuedcoupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($issuedcoupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($issuedcoupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
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

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($issuedcoupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($issuedcoupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - get all coupon wallet in all mall
     *
     * @author Ahmad <ahmad@dominopos.com>
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
    public function getCouponWalletList()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $sort_by = OrbitInput::get('sortby', 'coupon_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;


            $prefix = DB::getTablePrefix();

            $coupon = Coupon::select(DB::raw("
                                {$prefix}promotions.promotion_id as promotion_id,
                                CASE WHEN {$prefix}coupon_translations.promotion_name = '' THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                                CASE WHEN {$prefix}coupon_translations.description = '' THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}coupon_translations ct
                                        join {$prefix}media m
                                            on m.object_id = ct.coupon_translation_id
                                            and m.media_name_long = 'coupon_translation_image_orig'
                                        where ct.promotion_id = {$prefix}promotions.promotion_id
                                        group by ct.promotion_id
                                    ) ELSE {$prefix}media.path END as original_media_path,
                                {$prefix}promotions.end_date,
                                {$prefix}promotions.status,
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                    THEN {$prefix}campaign_status.campaign_status_name
                                    ELSE (
                                        CASE WHEN {$prefix}promotions.end_date < (
                                            SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                            FROM {$prefix}promotion_retailer opt
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        )
                                        THEN 'expired'
                                        ELSE {$prefix}campaign_status.campaign_status_name
                                        END
                                    )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(opt.promotion_retailer_id)
                                    FROM {$prefix}promotion_retailer opt
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                    AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                {$prefix}issued_coupons.issued_coupon_id
                            "))
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->join('issued_coupons', function ($join) {
                                $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $join->where('issued_coupons.status', '=', 'active');
                            })
                            ->where('issued_coupons.user_id', $user->user_id)
                            ->where('coupon_translations.merchant_language_id', $valid_language->language_id)
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('promotion_id');

            OrbitInput::get('filter_name', function ($filterName) use ($coupon, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $coupon->whereRaw("SUBSTR({$prefix}coupon_translations.promotion_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $coupon->whereRaw("SUBSTR({$prefix}coupon_translations.promotion_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            OrbitInput::get('keyword', function ($keyword) use ($coupon, $prefix) {
                if (! empty($keyword)) {
                    $coupon = $coupon->leftJoin('keyword_object', 'promotions.promotion_id', '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix)
                                {
                                    $word = explode(" ", $keyword);
                                    foreach ($word as $key => $value) {
                                        if (strlen($value) === 1 && $value === '%') {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->whereRaw("{$prefix}coupon_translations.promotion_name like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}coupon_translations.description like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$value}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->where('coupon_translations.promotion_name', 'like', '%' . $value . '%')
                                                  ->orWhere('coupon_translations.description', 'like', '%' . $value . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $value . '%');
                                            });
                                        }
                                    }
                                });
                }
            });

            $coupon = $coupon->orderBy($sort_by, $sort_mode);

            $_coupon = clone $coupon;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $coupon->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();
            $count = RecordCounter::create($_coupon)->count();

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Landing Page Coupon Wallet List Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_coupon_wallet_list')
                    ->setActivityNameLong('View GoToMalls Coupon Wallet List')
                    ->setObject(NULL)
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

    /**
     * GET - get list of coupon wallet locations
     *
     * @author Ahmad <ahmad@dominopos.com>
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
    public function getCouponWalletLocations()
    {
        $httpCode = 200;
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $couponId = OrbitInput::get('coupon_id');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                ),
                array(
                    'coupon_id' => 'required',
                ),
                array(
                    'required' => 'Coupon ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            $replaceIdPattern = '---REPLACE_ME_WITH_ID---';
            $urlToCI = URL::route('ci-coupon-detail', array('id' => $replaceIdPattern), false);
            $mall = PromotionRetailer::select(
                    DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                    DB::raw("{$prefix}merchants.object_type as location_type"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE CONCAT('Customer Service at ', {$prefix}merchants.name) END as name"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.ci_domain ELSE {$prefix}merchants.ci_domain END as ci_domain"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                    DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.description ELSE {$prefix}merchants.description END as description"),
                    DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), REPLACE('{$urlToCI}', '$replaceIdPattern', {$prefix}promotion_retailer.promotion_id)) as coupon_url"),
                    'promotion_retailer.promotion_id as coupon_id',
                    'promotions.begin_date as begin_date',
                    'promotions.end_date as end_date',
                    DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                FROM {$prefix}merchants om
                                LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                            ) as tz"),
                    DB::Raw("img.path as location_logo"),
                    DB::Raw("{$prefix}merchants.phone as phone")
                )
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->leftJoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                ->join('issued_coupons', function ($join) {
                    $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('issued_coupons.status', '=', 'active');
                })
                ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix) {
                    $q->on(DB::raw('img.object_id'), '=', DB::Raw("
                                                        (select CASE WHEN t.object_type = 'tenant'
                                                                    THEN m.merchant_id
                                                                    ELSE t.merchant_id
                                                                END as mall_id
                                                        from orb_merchants t
                                                        join orb_merchants m
                                                            on m.merchant_id = t.parent_id
                                                        where t.merchant_id = {$prefix}merchants.merchant_id)
                                            "))
                        ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                })
                ->where('issued_coupons.user_id', $user->user_id)
                ->where('promotion_retailer.promotion_id', '=', $couponId)
                ->groupBy('merchant_id')
                ->havingRaw('tz <= end_date AND tz >= begin_date');

            OrbitInput::get('filter_name', function ($filterName) use ($mall, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $mall->whereRaw("SUBSTR((CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END),1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $mall->whereRaw("SUBSTR((CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END),1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            $mall = $mall->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
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

    public function getCouponItem()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $couponId = OrbitInput::get('coupon_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                    'language' => $language,
                ),
                array(
                    'coupon_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Coupon ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $coupon = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN {$prefix}coupon_translations.promotion_name = '' THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN {$prefix}coupon_translations.description = '' THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
                                    CASE WHEN {$prefix}media.path is null THEN (
                                            select m.path
                                            from {$prefix}coupon_translations ct
                                            join {$prefix}media m
                                                on m.object_id = ct.coupon_translation_id
                                                and m.media_name_long = 'coupon_translation_image_orig'
                                            where ct.promotion_id = {$prefix}promotions.promotion_id
                                            group by ct.promotion_id
                                        ) ELSE {$prefix}media.path END as original_media_path
                                "),
                            'promotions.end_date',
                            // 'media.path as original_media_path',
                            DB::Raw("
                                    CASE WHEN {$prefix}issued_coupons.user_id is NULL
                                        THEN 'false'
                                        ELSE 'true'
                                    END as get_coupon_status
                                "),
                            // query for get status active based on timezone
                            DB::raw("
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                            THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                        FROM {$prefix}promotion_retailer opr
                                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                        WHERE opr.promotion_id = {$prefix}promotions.promotion_id)
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                    CASE WHEN (SELECT count(opr.retailer_id)
                                                FROM {$prefix}promotion_retailer opr
                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                                AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                    THEN 'true' ELSE 'false' END AS is_started
                            ")
                        )
                        ->join('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->leftJoin('media', function($q) {
                            $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                        })
                        ->leftJoin('issued_coupons', function ($q) use ($user) {
                                $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $q->on('issued_coupons.user_id', '=', DB::Raw("{$this->quote($user->user_id)}"));
                                $q->on('issued_coupons.status', '=', DB::Raw("'active'"));
                            })
                        ->where('promotions.promotion_id', $couponId)
                        ->where('coupon_translations.merchant_language_id', '=', $valid_language->language_id)
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->first();

            $message = 'Request Ok';
            if (! is_object($coupon)) {
                OrbitShopAPI::throwInvalidArgument('Coupon that you specify is not found');
            }

            $activityNotes = sprintf('Page viewed: Landing Page Coupon Detail Page');
            $activity->setUser($user)
                ->setActivityName('view_landing_page_coupon_detail')
                ->setActivityNameLong('View GoToMalls Coupon Detail')
                ->setObject($coupon)
                ->setCoupon($coupon)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            // add facebook share url dummy page
            $coupon->facebook_share_url = SocMedAPIController::getSharedUrl('coupon', $coupon->promotion_id, $coupon->promotion_name);

            $this->response->data = $coupon;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;

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

    public function getCouponLocations()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $couponId = OrbitInput::get('coupon_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'coupon_id' => $couponId,
                ),
                array(
                    'coupon_id' => 'required',
                ),
                array(
                    'required' => 'Coupon ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $couponLocations = PromotionRetailer::select(
                                            DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE CONCAT('Customer Service at ', {$prefix}merchants.name) END as name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.name ELSE 'Customer Service' END as store_name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as mall_name"),
                                            DB::raw("{$prefix}merchants.object_type as location_type"),
                                            DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), '/customer/mallcoupondetail?id=', {$prefix}promotion_retailer.promotion_id) as url"),
                                            'promotions.begin_date as begin_date',
                                            'promotions.end_date as end_date',
                                            DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                        FROM {$prefix}merchants om
                                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                        WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                    ) as tz"),
                                            DB::Raw("img.path as location_logo"),
                                            DB::Raw("{$prefix}merchants.phone as phone")
                                        )
                                    ->leftJoin('promotions', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix) {
                                        $q->on(DB::raw('img.object_id'), '=', DB::Raw("
                                                        (select CASE WHEN t.object_type = 'tenant'
                                                                    THEN m.merchant_id
                                                                    ELSE t.merchant_id
                                                                END as mall_id
                                                        from orb_merchants t
                                                        join orb_merchants m
                                                            on m.merchant_id = t.parent_id
                                                        where t.merchant_id = {$prefix}merchants.merchant_id)
                                            "))
                                            ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                                    })
                                    ->where('promotions.promotion_id', $couponId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_couponLocations = clone($couponLocations);

            $take = PaginationNumber::parseTakeFromGet('promotions');
            $couponLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $couponLocations->skip($skip);

            $couponLocations->orderBy($sort_by, $sort_mode);

            $listOfRec = $couponLocations->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_couponLocations)->count();
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

    protected function registerCustomValidation() {
        // Check the existance of issued coupon id
        Validator::extend('orbit.empty.issuedcoupon', function ($attribute, $value, $parameters) {

            // decrypt hashed issued coupon id
            try {
                $encryptionKey = Config::get('orbit.security.encryption_key');
                $encryptionDriver = Config::get('orbit.security.encryption_driver');
                $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

                $value = $encrypter->decrypt($value);
            } catch (Exception $e) {
                $errorMessage = 'Invalid cid';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $now = date('Y-m-d H:i:s');
            $number = OrbitInput::post('merchant_verification_number');
            $mall_id = OrbitInput::post('mall_id');

            $prefix = DB::getTablePrefix();

            $issuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                        ->where('issued_coupons.issued_coupon_id', $value)
                        ->whereNull('issued_coupons.user_id')
                        ->with('coupon')
                        ->whereHas('coupon', function($q) use($now) {
                            $q->where('promotions.status', 'active');
                            $q->where('promotions.coupon_validity_in_date', '>=', $now);
                        })
                        ->first();

            if (empty($issuedCoupon)) {
                $errorMessage = 'Issued coupon ID is not found.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            //Checking verification number in cs and tenant verification number
            //Checking in tenant verification number first
            if ($issuedCoupon->coupon->is_all_retailer === 'Y') {
                $checkIssuedCoupon = Tenant::where('parent_id','=', $mall_id)
                            ->where('status', 'active')
                            ->where('masterbox_number', $number)
                            ->first();
            } elseif ($issuedCoupon->coupon->is_all_retailer === 'N') {
                $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                            ->join('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where('issued_coupons.issued_coupon_id', $value)
                            ->whereNull('issued_coupons.user_id')
                            ->whereHas('coupon', function($q) use($now) {
                                $q->where('promotions.status', 'active');
                                $q->where('promotions.coupon_validity_in_date', '>=', $now);
                            })
                            ->where('merchants.masterbox_number', $number)
                            ->first();
            }

            // Continue checking to tenant verification number
            if (empty($checkIssuedCoupon)) {
                // Checking cs verification number
                if ($issuedCoupon->coupon->is_all_employee === 'Y') {
                    $checkIssuedCoupon = UserVerificationNumber::join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('status', 'active')
                                ->where('merchant_id', $mall_id)
                                ->where('verification_number', $number)
                                ->first();
                } elseif ($issuedCoupon->coupon->is_all_employee === 'N') {
                    $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed'])
                                ->join('promotion_employee', 'promotion_employee.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->join('user_verification_numbers', 'user_verification_numbers.user_id', '=', 'promotion_employee.user_id')
                                ->join('employees', 'employees.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('employees.status', 'active')
                                ->where('issued_coupons.issued_coupon_id', $value)
                                ->whereNull('issued_coupons.user_id')
                                ->whereHas('coupon', function($q) use($now) {
                                    $q->where('promotions.status', 'active');
                                    $q->where('promotions.coupon_validity_in_date', '>=', $now);
                                })
                                ->where('user_verification_numbers.verification_number', $number)
                                ->first();
                }
            }

            if (! isset($checkIssuedCoupon) || empty($checkIssuedCoupon)) {
                $errorMessage = Lang::get('mobileci.coupon.wrong_verification_number');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($checkIssuedCoupon)) {
                App::instance('orbit.empty.issuedcoupon', $issuedCoupon);
            }

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::with('timezone')->excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check coupon, it should exists
        Validator::extend(
            'orbit.exists.coupon',
            function ($attribute, $value, $parameters) {
                $prefix = DB::getTablePrefix();
                // use nearest mall to check the eligibility
                $nearestMallByTimezoneOffset = CouponRetailer::selectRaw("
                        CASE WHEN {$prefix}promotion_retailer.object_type = 'tenant' THEN mall.merchant_id ELSE {$prefix}merchants.merchant_id END as id,
                        CASE WHEN {$prefix}promotion_retailer.object_type = 'tenant' THEN mall.name ELSE {$prefix}merchants.name END as name,
                        mall.timezone_id,
                        {$prefix}promotion_retailer.object_type,
                        {$prefix}timezones.timezone_name,
                        TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), CONVERT_TZ(UTC_TIMESTAMP(), 'Etc/UTC', {$prefix}timezones.timezone_name)) as offset
                    ")
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                    ->leftJoin(DB::raw("{$prefix}merchants as mall"), DB::raw('mall.merchant_id'), '=', 'merchants.parent_id')
                    ->leftJoin('timezones', DB::raw("CASE WHEN {$prefix}promotion_retailer.object_type = 'tenant' THEN mall.timezone_id ELSE {$prefix}merchants.timezone_id END"), '=', 'timezones.timezone_id')
                    ->where('promotion_id', $value)
                    ->orderBy('offset')
                    ->first();

                $mallTime = Carbon::now($nearestMallByTimezoneOffset->timezone_name);
                $coupon = Coupon::active()
                                ->where('promotion_id', $value)
                                ->where('begin_date', "<=", $mallTime)
                                ->where('end_date', '>=', $mallTime)
                                ->where('coupon_validity_in_date', '>=', $mallTime)
                                ->first();

                if (! is_object($coupon)) {
                    return false;
                }

                \App::instance('orbit.validation.coupon', $coupon);

                return true;
            }
        );

        // Check coupon, it should not exists in user wallet
        Validator::extend(
            'orbit.notexists.couponwallet',
            function ($attribute, $value, $parameters) {
                // check if coupon already add to wallet
                $user = UserGetter::getLoggedInUserOrGuest($this->session);
                $wallet = IssuedCoupon::where('promotion_id', '=', $value)
                                      ->where('user_id', '=', $user->user_id)
                                      ->where('status', '=', 'active')
                                      ->first();

                if (is_object($wallet)) {
                    return false;
                }

                return true;
            }
        );

        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * Modify user object property
     */
    private function customizeUserProps($user, $email)
    {
        $role = $user->role->role_name;
        if (strtolower($role) === 'consumer') {
            // change first name and last name to full name + (user_email)
            $user->user_firstname = $user->getFullName();
            $user->user_lastname = sprintf("(%s)", $user->user_email);
        }

        // change user email to email provided by query string
        $user->user_email = $email;

        return $user;
    }
}
