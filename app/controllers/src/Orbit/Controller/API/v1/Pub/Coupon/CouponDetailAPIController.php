<?php namespace Orbit\Controller\API\v1\Pub\Coupon;
/**
 * Controller for Coupon detail.
 */
use OrbitShop\API\v1\PubControllerAPI;
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
use Lang;
use \Exception;
use Mall;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;

class CouponDetailAPIController extends PubControllerAPI
{
    public function getCouponItem()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try{
            $user = $this->getUser();

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

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $coupon = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
                                    (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id) AS original_media_path
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
                            "),
                            // query for getting timezone for countdown on the frontend
                            DB::raw("
                                (SELECT
                                    ot.timezone_name
                                FROM {$prefix}promotion_retailer opt
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                    LEFT JOIN {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                ORDER BY CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) ASC
                                LIMIT 1
                                ) as timezone
                            ")
                        )
                        ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                            $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                              ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->leftJoin('issued_coupons', function ($q) use ($user) {
                                $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $q->on('issued_coupons.user_id', '=', DB::Raw("{$this->quote($user->user_id)}"));
                                $q->on('issued_coupons.status', '=', DB::Raw("'issued'"));
                            })
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
                        ->with(['keywords' => function ($q) {
                                $q->addSelect('keyword', 'object_id');
                            }])
                        ->where('promotions.promotion_id', $couponId);

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

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View mall coupon detail');
                $activity->setUser($user)
                    ->setActivityName('view_mall_coupon_detail')
                    ->setActivityNameLong('View mall coupon detail')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
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
            }



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
