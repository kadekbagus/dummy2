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
use IssuedCoupon;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Lang;
use CouponPaymentProvider;
use \Exception;
use Mall;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Partner;
use \Orbit\Helper\Exception\OrbitCustomException;
use TotalObjectPageView;
use Redis;
use Orbit\Helper\MongoDB\Client as MongoClient;

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
            $country = OrbitInput::get('country', null);
            $cities = OrbitInput::get('cities', null);
            $couponId = OrbitInput::get('coupon_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);
            $partnerToken = OrbitInput::get('token', null);
            $notificationId = OrbitInput::get('notification_id', null);

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
            if ($role === 'Guest') {
                $getCouponStatusSql = " 'false' as get_coupon_status ";
                $issuedCouponId = " NULL as issued_coupon_id ";
            } else {
                $getCouponStatusSql = " CASE WHEN {$prefix}issued_coupons.user_id is NULL
                                            THEN 'false'
                                            ELSE 'true'
                                        END as get_coupon_status ";
                $issuedCouponId = " CASE WHEN {$prefix}issued_coupons.user_id = " . $this->quote($user->user_id) . "
                                            THEN {$prefix}issued_coupons.issued_coupon_id
                                            ELSE NULL
                                        END as issued_coupon_id ";
            }

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $location = $mallId;
            if (empty($location)) {
                $location = 0;
            }

            if (! empty($notificationId)) {
                $mongoConfig = Config::get('database.mongodb');
                $mongoClient = MongoClient::create($mongoConfig);

                $bodyUpdate = [
                    '_id'     => $notificationId,
                    'is_read' => true
                ];

                $response = $mongoClient->setFormParam($bodyUpdate)
                                        ->setEndPoint('user-notifications') // express endpoint
                                        ->request('PUT');
            }

            $coupon = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,
                                    CASE WHEN (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = default_translation.coupon_translation_id)
                                    ELSE
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id)
                                    END AS original_media_path
                                "),
                            'promotions.end_date',
                            'promotions.coupon_validity_in_date',
                            'promotions.is_exclusive',
                            'promotions.available',
                            'promotions.is_unique_redeem',
                            'promotions.maximum_redeem',
                            'promotions.maximum_issued_coupon',
                            DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END as mall_id"),
                            // 'media.path as original_media_path',
                            DB::Raw($getCouponStatusSql),
                            DB::Raw($issuedCouponId),
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
                                    CASE WHEN {$prefix}promotions.coupon_validity_in_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                        FROM {$prefix}promotion_retailer opr
                                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                        WHERE opr.promotion_id = {$prefix}promotions.promotion_id)
                                    THEN 'true' ELSE 'false' END as is_exceeding_validity_date,
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
                        ->leftJoin('issued_coupons', function ($q) use ($user) {
                                $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $q->on('issued_coupons.user_id', '=', DB::Raw("{$this->quote($user->user_id)}"));
                                $q->on('issued_coupons.status', '=', DB::Raw("'issued'"));
                            })
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
                        ->with(['keywords' => function ($q) {
                                $q->addSelect('keyword', 'object_id');
                                $q->groupBy('keyword');
                            }])
                        ->with(['product_tags' => function ($pt) {
                                $pt->addSelect('product_tag', 'object_id');
                                $pt->groupBy('product_tag');
                            }])
                        ->where('promotions.promotion_id', $couponId)
                        ->where('promotions.is_visible', 'Y');

            OrbitInput::get('mall_id', function($mallId) use ($coupon, &$mall) {
                $coupon->havingRaw("mall_id = {$this->quote($mallId)}");
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            $coupon = $coupon->first();

            $message = 'Request Ok';
            if (! is_object($coupon)) {
                throw new OrbitCustomException('Coupon that you specify is not found', Coupon::NOT_FOUND_ERROR_CODE, NULL);
            }

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();
            }

            if (! empty($coupon) && $coupon->campaign_status != 'ongoing' && $coupon->is_started != 'true') {
                $mallName = 'gtm';
                if (! empty($mall)) {
                    $mallName = $mall->name;
                }

                $customData = new \stdClass;
                $customData->type = 'coupon';
                $customData->location = $location;
                $customData->mall_name = $mallName;
                throw new OrbitCustomException('Coupon is inactive', Coupon::INACTIVE_ERROR_CODE, $customData);
            }

            // Config page_views
            $configPageViewSource = Config::get('orbit.page_view.source', FALSE);
            $configPageViewRedisDb = Config::get('orbit.page_view.redis.connection', FALSE);
            $totalPageViews = 0;

            // Get total page views, depend of config what DB used
            if ($configPageViewSource === 'redis') {
                $keyRedis = 'coupon||' . $couponId . '||' . $location;
                $redis = Redis::connection($configPageViewRedisDb);
                $totalPageViewRedis = $redis->get($keyRedis);
                $totalPageViews = 0;

                if (! empty($totalPageViewRedis)) {
                    $totalPageViews = $totalPageViewRedis;
                } else {
                    $totalObjectPageView = TotalObjectPageView::where('object_type', 'coupon')
                                                                 ->where('object_id', $couponId)
                                                                 ->where('location_id', $location)
                                                                 ->first();

                    if (! empty($totalObjectPageView->total_view)) {
                        $totalPageViews = $totalObjectPageView->total_view;
                    }
                }

            } else {
                $totalObjectPageView = TotalObjectPageView::where('object_type', 'coupon')
                                                             ->where('object_id', $couponId)
                                                             ->where('location_id', $location)
                                                             ->first();

                if (! empty($totalObjectPageView->total_view)) {
                    $totalPageViews = $totalObjectPageView->total_view;
                }
            }
            $coupon->total_view = $totalPageViews;

            // ---- START RATING ----
            $reviewCounter = \Orbit\Helper\MongoDB\Review\ReviewCounter::create(Config::get('database.mongodb'))
                ->setObjectId($coupon->promotion_id)
                ->setObjectType('coupon')
                ->setMall($mall)
                ->request();

            $coupon->rating_average = $reviewCounter->getAverage();
            $coupon->review_counter = $reviewCounter->getCounter();
            // ---- END OF RATING ----

            if ($coupon->is_exclusive === 'Y') {
                // check token
                $partnerTokens = Partner::leftJoin('object_partner', 'partners.partner_id', '=', 'object_partner.partner_id')
                                    ->where('object_partner.object_type', 'coupon')
                                    ->where('object_partner.object_id', $coupon->promotion_id)
                                    ->where('partners.is_exclusive', 'Y')
                                    ->where('partners.token', $partnerToken)
                                    ->first();

                if (is_object($partnerTokens)) {
                    $coupon->is_exclusive = 'N';
                }
            }

			// unique coupon
            $coupon->get_unique_coupon = 'true';
            if ($coupon->is_unique_redeem === 'Y' && $role != 'Guest') {
                $checkIssued = IssuedCoupon::where('promotion_id', $coupon->promotion_id)
                                           ->where('user_id', $user->user_id)
                                           ->whereNotIn('status', ['issued', 'deleted'])
                                           ->first();

                if (is_object($checkIssued)) {
                    $coupon->get_unique_coupon = 'false';
                }
            }

            $availableForRedeem = $coupon->available;
            // get total redeemed
            $totalRedeemed = IssuedCoupon::where('status', '=', 'redeemed')
                                        ->where('promotion_id', $coupon->promotion_id)
                                        ->count();
            $coupon->total_redeemed = $totalRedeemed;

            if ($coupon->maximum_redeem > 0) {
                $availableForRedeem = $coupon->maximum_redeem - $totalRedeemed;
                if ($totalRedeemed >= $coupon->maximum_redeem) {
                    $availableForRedeem = 0;
                }
            }
            $coupon->available_for_redeem = $availableForRedeem;

            // get total issued
            $totalIssued = IssuedCoupon::whereIn('status', ['issued', 'redeemed'])
                                        ->where('promotion_id', $coupon->promotion_id)
                                        ->count();
            $coupon->total_issued = $totalIssued;

            // set maximum redeemed to maximum issued when empty
            if ($coupon->maximum_redeem === '0') {
                $coupon->maximum_redeem = $coupon->maximum_issued_coupon;
            }

            // check payment method / wallet operator
            $imageWallet = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $imageWallet = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }
            $walletOperators = CouponPaymentProvider::select('payment_providers.payment_name as operator_name', DB::raw("{$imageWallet} AS operator_logo_url"), 'payment_providers.deeplink_url', 'payment_providers.payment_provider_id as operator_id')
                                                    ->join('payment_providers', 'coupon_payment_provider.payment_provider_id', '=', 'payment_providers.payment_provider_id')
                                                    ->leftJoin('media', function ($q) {
                                                        $q->on('media.object_id', '=', 'payment_providers.payment_provider_id');
                                                        $q->on('media.media_name_id', '=', DB::Raw("'wallet_operator_logo'"));
                                                        $q->on('media.media_name_long', '=', DB::Raw("'wallet_operator_logo_orig'"));
                                                    })
                                                    ->leftJoin('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_retailer_redeem_id', '=', 'coupon_payment_provider.promotion_retailer_redeem_id')
                                                    ->where('promotion_retailer_redeem.promotion_id', $couponId)
                                                    ->groupBy('payment_providers.payment_provider_id')
                                                    ->get();

            $coupon->wallet_operator = null;
            if (! $walletOperators->isEmpty()) {
                 $coupon->wallet_operator = $walletOperators;
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
            $coupon->facebook_share_url = SocMedAPIController::getSharedUrl('coupon', $coupon->promotion_id, $coupon->promotion_name, $country, $cities);
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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
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
