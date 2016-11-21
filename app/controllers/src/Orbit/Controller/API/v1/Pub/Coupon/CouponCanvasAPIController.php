<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Lang;
use \Exception;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Security\Encrypter;
use Activity;
use Coupon;
use IssuedCoupon;


class CouponCanvasAPIController extends PubControllerAPI
{
    /**
     * GET - check available issued coupon
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string cid
     * @param string pid
     * @param string language
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCheckValidityCoupon()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $issuedCoupon = NULL;
        $coupon = NULL;
        $issuedCouponCode = NULL;
        $isAvailable = NULL;

        try {
            $this->checkAuth();
            $user = $this->api->user;

            $language = OrbitInput::get('language', 'id');
            $issuedCouponCode = OrbitInput::get('cid');
            $promotioId = OrbitInput::get('pid');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'cid' => $issuedCouponCode,
                    'pid' => $promotioId,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'cid' => 'required',
                    'pid' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $couponHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $issuedCouponCode = OrbitInput::get('cid', NULL);
            $promotioId = OrbitInput::get('pid', NULL);

            $userIdentifier = OrbitInput::get('uid', NULL);

            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $issuedCouponCode = $encrypter->decrypt($issuedCouponCode);
            $promotioId = $encrypter->decrypt($promotioId);

            // Validate issued coupon link from sms
            $checkIssuedCoupon = IssuedCoupon::where('issued_coupon_code', $issuedCouponCode)
                ->where('promotion_id', $promotioId)
                ->where('status', 'available')
                ->first();

            // Check user must have one issued coupon code per coupon
            $checkUserCoupon = IssuedCoupon::where('promotion_id', $promotioId)
                ->where('user_id', '=', $user->user_id)
                ->where('status', 'issued')
                ->count();

            if (! empty($checkIssuedCoupon) && $checkUserCoupon == 0) {
                $coupon = Coupon::select(
                                'promotions.promotion_id as promotion_id',
                                DB::Raw("
                                        CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                        CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN {$prefix}promotions.description ELSE {$prefix}coupon_translations.description END as description,
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
                                'issued_coupons.status as coupon_status',
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
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                            ->leftJoin('media', function ($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin('issued_coupons', function ($q) {
                                    $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                    $q->on('issued_coupons.status', '=', DB::Raw("'available'"));
                                })
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
                            ->where('promotions.promotion_id', $promotioId)
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->first();
            }

            if (is_object($coupon)) {
                $this->response->message = 'Request Ok';
                $this->response->data = $coupon;
                $activityNotes = 'SMS / Email';
                $activity->setUser($user)
                    ->setActivityName('view_link_page')
                    ->setActivityNameLong('View Link Page')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setLocation(null)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $this->response->message = 'Failed to view coupon via sms';
                $this->response->data = NULL;
                $activityNotes = 'SMS / Email';;
                $activity->setUser($user)
                    ->setActivityName('view_link_page_failed')
                    ->setActivityNameLong('View Link Page Failed')
                    ->setObject($coupon)
                    ->setModuleName('Coupon')
                    ->setLocation(null)
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseFailed()
                    ->save();
            }

            $this->response->code = 0;
            $this->response->status = 'success';
        } catch (ACLForbiddenException $e) {
            $activityNotes = 'SMS / Email';
            $activity->setUser($user)
                ->setActivityName('view_link_page_failed')
                ->setActivityNameLong('View Link Page Failed')
                ->setObject($coupon)
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
            $activityNotes = 'SMS / Email';
            $activity->setUser($user)
                ->setActivityName('view_link_page_failed')
                ->setActivityNameLong('View Link Page Failed')
                ->setObject($coupon)
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
            $activityNotes = 'SMS / Email';
            $activity->setUser($user)
                ->setActivityName('view_link_page_failed')
                ->setActivityNameLong('View Link Page Failed')
                ->setObject($coupon)
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
            $activityNotes = 'SMS / Email';
            $activity->setUser($user)
                ->setActivityName('view_link_page_failed')
                ->setActivityNameLong('View Link Page Failed')
                ->setObject($coupon)
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


    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
