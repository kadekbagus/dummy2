<?php namespace Orbit\Controller\API\v1\Pub\Coupon;
/**
 * Controller for Coupon detail.
 */
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
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Lang;
use \Exception;
use Mall;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;

class CouponDetailAPIController extends ControllerAPI
{
    public function getCouponItem()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try{
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);
            $role = $user->role->role_name;

            $couponId = OrbitInput::get('coupon_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
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

            $valid_language = $couponHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            // This condition only for guest can issued multiple coupon with multiple email
            if ($role == 'Guest') {
                $getCouponStatusSql = " 'false' as get_coupon_status ";
            } else {
                $getCouponStatusSql = " CASE WHEN {$prefix}issued_coupons.user_id is NULL
                                            THEN 'false'
                                            ELSE 'true'
                                        END as get_coupon_status ";
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
                            DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END as mall_id"),
                            // 'media.path as original_media_path',
                            DB::Raw($getCouponStatusSql),
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
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
                        ->where('promotions.promotion_id', $couponId)
                        ->where('coupon_translations.merchant_language_id', '=', $valid_language->language_id);

            OrbitInput::get('mall_id', function($mallId) use ($coupon, &$mall) {
                $coupon->havingRaw("mall_id = {$this->quote($mallId)}");
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            $coupon = $coupon->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
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
                ->setLocation($mall)
                ->setModuleName('Coupon')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            // add facebook share url dummy page
            $coupon->facebook_share_url = SocMedAPIController::getSharedUrl('coupon', $coupon->promotion_id, $coupon->promotion_name);
            // remove mall_id from result
            unset($coupon->mall_id);

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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
