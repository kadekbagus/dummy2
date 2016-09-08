<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
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

class CouponAPIController extends ControllerAPI
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
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'coupon_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $prefix = DB::getTablePrefix();

            $coupon = Coupon::select(DB::raw("{$prefix}promotions.promotion_id as coupon_id,
                                {$prefix}coupon_translations.promotion_name as coupon_name, {$prefix}promotions.status,
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
                                THEN 'true' ELSE 'false' END AS is_started"), 'promotions.description', DB::raw('media.path as image_url'))
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin(DB::raw("( SELECT * FROM {$prefix}media WHERE media_name_long = 'coupon_translation_image_orig' ) as media"), DB::raw('media.object_id'), '=', 'coupon_translations.coupon_translation_id')
                            ->where('languages.name', '=', 'en')
                            ->where('coupon_translations.promotion_name', '!=', '')
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('coupon_id');

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
     * @return string
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
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $this->registerCustomValidation();
            $coupon_id = OrbitInput::post('coupon_id');

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
                $activityNotes = sprintf('Failed to add to wallet Coupon Id: %s.', $coupon->promotion_id);
                $activity->setUser($user)
                    ->setActivityName('click_add_to_wallet')
                    ->setActivityNameLong('Landing Page Failed to Add To Wallet')
                    ->setLocation($retailer)
                    ->setObject($issuedCoupon)
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
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Landing Page Failed to Add To Wallet')
                ->setObject($issuedCoupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
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
                ->setActivityNameLong('Landing Page Failed to Add To Wallet')
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
                ->setActivityNameLong('Landing Page Failed to Add To Wallet')
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

            // Get language_if of english
            $languageEnId = null;
            $language = Language::where('name', 'en')->first();

            if (! empty($language)) {
                $languageEnId = $language->language_id;
            }

            $sort_by = OrbitInput::get('sortby', 'coupon_name');
            $sort_mode = OrbitInput::get('sortmode','asc');

            $prefix = DB::getTablePrefix();

            $coupon = Coupon::with(['translations' => function($q) use ($languageEnId) {
                                $q->addSelect(['coupon_translation_id', 'promotion_id']);
                                $q->with(['media' => function($q2) {
                                    $q2->addSelect(['object_id', 'media_name_long', 'path']);
                                }]);
                                $q->where('merchant_language_id', $languageEnId);
                            }])
                            ->select(DB::raw("
                                {$prefix}promotions.promotion_id as promotion_id,
                                {$prefix}coupon_translations.promotion_name as coupon_name,
                                {$prefix}coupon_translations.description as description,
                                {$prefix}promotions.end_date,
                                {$prefix}media.path as original_media_path,
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
                            ->where('languages.name', '=', 'en')
                            ->where('coupon_translations.promotion_name', '!=', '')
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
                ->leftJoin(DB::raw("{$prefix}media as img"), DB::raw('img.object_id'), '=', 'merchants.merchant_id')
                ->join('issued_coupons', function ($join) {
                    $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                    $join->where('issued_coupons.status', '=', 'active');
                })
                ->whereIn(DB::raw('img.media_name_long'), ['mall_logo_orig', 'retailer_logo_orig'])
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

            // Get language_if of english
            $languageEnId = null;
            $language = Language::where('name', 'en')->first();

            if (! empty($language)) {
                $languageEnId = $language->language_id;
            }

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

            $coupon = Coupon::with(['translations' => function($q) use ($languageEnId) {
                            $q->addSelect(['coupon_translation_id', 'promotion_id']);
                            $q->with(['media' => function($q2) {
                                $q2->addSelect(['object_id', 'media_name_long', 'path']);
                            }]);
                            $q->where('merchant_language_id', $languageEnId);
                        }])
                        ->select(
                            'promotions.promotion_id as promotion_id',
                            'coupon_translations.promotion_name as promotion_name',
                            'coupon_translations.description as description',
                            'promotions.end_date',
                            'media.path as original_media_path',
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
                        ->where('coupon_translations.merchant_language_id', '=', $languageEnId)
                        ->where('coupon_translations.promotion_name', '!=', '')
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
                ->setNews($coupon)
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
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE CONCAT('Customer Service at ', {$prefix}merchants.name) END as name"),
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
                                    ->leftJoin(DB::raw("{$prefix}media as img"), DB::raw('img.object_id'), '=', 'merchants.merchant_id')
                                    ->whereIn(DB::raw('img.media_name_long'), ['mall_logo_orig', 'retailer_logo_orig'])
                                    ->where('promotions.promotion_id', $couponId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_couponLocations = clone($couponLocations);

            $take = PaginationNumber::parseTakeFromGet('promotions');
            $couponLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $couponLocations->skip($skip);

            $couponLocations->orderBy('name', 'asc');

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
        // Check coupon, it should exists
        Validator::extend(
            'orbit.exists.coupon',
            function ($attribute, $value, $parameters) {
                $prefix = DB::getTablePrefix();
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
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
