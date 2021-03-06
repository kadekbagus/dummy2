<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Coupon;
use stdClass;
use DB;
use Validator;
use Activity;
use Lang;
use IssuedCoupon;
use Orbit\Helper\Security\Encrypter;
use \Exception;

class CouponRedemptionPageAPIController extends PubControllerAPI
{
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
        $isAvailable = NULL;

        try {
            $user = $this->getUser();

            $language = OrbitInput::get('language', 'id');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();

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

            $valid_language = $couponHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $couponId = OrbitInput::get('cid', NULL);
            $userIdentifier = OrbitInput::get('uid', NULL);

            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $couponId = $encrypter->decrypt($couponId);

            // requested coupon before validation
            $coupon = Coupon::excludeDeleted('promotions')
                ->where('promotion_id', $couponId)
                ->first();

            $validator = Validator::make(
                array(
                    'cid' => $couponId,
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

            if (! empty($userIdentifier)) {
                $userIdentifier = $encrypter->decrypt($userIdentifier);
            }

            // detect encoding to avoid query error
            if (! mb_detect_encoding($couponId, 'ASCII', true)) {
                $errorMessage = 'Invalid cid';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $isAvailable = (new IssuedCoupon())->issueCouponViaEmail($couponId, $userIdentifier, $user->user_id);
            if (! is_object($isAvailable)) {
                $errorMessage = 'This coupon is out of limit';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,
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
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                            $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                              ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('coupon_translations as default_translation', function ($q) {
                            $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                              ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                        })
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                        })
                        ->join('issued_coupons', function ($q) {
                            $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                            $q->on('issued_coupons.status', 'IN', DB::Raw("('available', 'issued')"));
                        })
                        ->where('issued_coupons.issued_coupon_id', '=', $isAvailable->issued_coupon_id)
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->first();

            $message = 'Request Ok';
            if (! is_object($coupon)) {
                OrbitShopAPI::throwInvalidArgument('Issued coupon that you specify is not found');
            }

            // customize user property before saving activity
            $user = $couponHelper->customizeUserProps($user, $userIdentifier);
            $issuedCouponId = $isAvailable->issued_coupon_id;

            $activityNotes = sprintf('Page viewed: Coupon Redemption Page. Issued Coupon Id: %s', $issuedCouponId);
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('View Redemption Page')
                ->setObject($coupon)
                ->setCoupon($coupon)
                ->setLocation(NULL)
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
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
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
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
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
            $activityNotes = sprintf('Failed view redemption page. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_redemption_page')
                ->setActivityNameLong('Failed to View Redemption Page')
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
