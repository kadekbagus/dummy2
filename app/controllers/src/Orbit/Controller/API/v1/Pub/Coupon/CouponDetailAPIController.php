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
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\IssuedCouponRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\PaymentRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\TimezoneRepository;
use App;

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
            $forRedeem = OrbitInput::get('for_redeem', 'N');
            $selectedIssuedCouponId = OrbitInput::get('issued_coupon_id', null);

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
                $getCouponStatusSql = " CASE WHEN {$prefix}issued_coupons.user_id IS NOT NULL OR {$prefix}issued_coupons.original_user_id IS NOT NULL
                                            THEN 'true'
                                            ELSE 'false'
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
            $couponTimezoneHelper = App::make(TimezoneRepository::class);
            $currentTenantTime = $couponTimezoneHelper->getTenantCurrentTime($couponId);

            $coupon = Coupon::select(
                            'promotions.promotion_id as promotion_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,
                                    CASE WHEN (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)}
                                        LIMIT 1
                                        ) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = default_translation.coupon_translation_id
                                        AND default_translation.merchant_language_id = {$this->quote($valid_language->language_id)}
                                        LIMIT 1
                                        )
                                    ELSE
                                        (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)}
                                        LIMIT 1)
                                    END AS original_media_path
                                "),
                            'promotions.begin_date',
                            'promotions.end_date',
                            DB::raw("default_translation.promotion_name as default_name"),
                            DB::raw("{$prefix}issued_coupons.expired_date as coupon_validity_in_date"),
                            'promotions.is_exclusive',
                            'promotions.available',
                            'promotions.is_unique_redeem',
                            'promotions.maximum_redeem',
                            'promotions.maximum_issued_coupon',
                            'promotions.promotion_type as coupon_type',
                            'promotions.price_old',
                            'promotions.price_selling as price_new',
                            'promotions.max_quantity_per_purchase',
                            'promotions.max_quantity_per_user',
                            'promotions.currency',
                            DB::raw("
                                CASE WHEN ({$prefix}promotions.promotion_type = 'sepulsa') THEN
                                    {$prefix}coupon_sepulsa.how_to_buy_and_redeem
                                ELSE
                                    CASE WHEN (
                                        {$prefix}coupon_translations.how_to_buy_and_redeem = '' OR
                                        {$prefix}coupon_translations.how_to_buy_and_redeem is null
                                    ) THEN
                                        default_translation.how_to_buy_and_redeem
                                    ELSE
                                        {$prefix}coupon_translations.how_to_buy_and_redeem
                                    END
                                END as how_to_buy_and_redeem
                            "),
                            'coupon_sepulsa.terms_and_conditions',
                            'issued_coupons.url as redeem_url',
                            'issued_coupons.user_id',
                            'issued_coupons.original_user_id',
                            'issued_coupons.transfer_status',
                            DB::raw("m.country as coupon_country"),
                            'promotions.promotion_type',
                            DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END as mall_id"),

                            // 'media.path as original_media_path',
                            DB::Raw($getCouponStatusSql),
                            DB::Raw($issuedCouponId),

                            // query for get status active based on timezone
                            DB::raw("
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                            THEN {$prefix}campaign_status.campaign_status_name
                                            ELSE (CASE WHEN {$prefix}promotions.end_date < ('$currentTenantTime')
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                    CASE WHEN {$prefix}issued_coupons.expired_date < ('$currentTenantTime')
                                    THEN 'true' ELSE 'false' END as is_exceeding_validity_date
                            "),
                            DB::raw("
                                CASE WHEN reserved_issued_coupons.status = 'reserved'
                                    THEN 'true'
                                ELSE 'false' END as is_reserved"),
                            DB::raw("
                                (SELECT
                                    count({$prefix}issued_coupons.issued_coupon_id) as issued_coupons_per_user
                                FROM {$prefix}issued_coupons
                                WHERE
                                    {$prefix}issued_coupons.promotion_id = '{$couponId}' AND
                                    {$prefix}issued_coupons.user_id = '{$user->user_id}' AND
                                    {$prefix}issued_coupons.transfer_status IS NULL AND
                                    {$prefix}issued_coupons.status IN ('issued', 'redeemed', 'reserved')
                                ) as used_coupons_count
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
                        ->leftJoin('issued_coupons', function ($q) use ($user, $prefix, $forRedeem, $selectedIssuedCouponId) {
                                $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $q->on(DB::raw("({$prefix}issued_coupons.user_id = {$this->quote($user->user_id)} OR {$prefix}issued_coupons.original_user_id"), '=', DB::raw("{$this->quote($user->user_id)})"));

                                if ($forRedeem === 'Y' && ! empty($selectedIssuedCouponId)) {
                                    $q->on(DB::raw("({$prefix}issued_coupons.status = 'issued' OR {$prefix}issued_coupons.status"), '=', DB::Raw("'redeemed')"));
                                }
                                else {
                                    $q->on('issued_coupons.status', '=', DB::raw("'issued'"));
                                }

                                $q->on('issued_coupons.expired_date', '>=', DB::Raw("CONVERT_TZ(NOW(), '+00:00', 'Asia/jakarta')"));
                            })
                        ->leftJoin('issued_coupons as reserved_issued_coupons', function ($q) use ($user) {
                                $q->on(DB::raw('reserved_issued_coupons.promotion_id'), '=', 'promotions.promotion_id');
                                $q->on(DB::raw('reserved_issued_coupons.user_id'), '=', DB::Raw("{$this->quote($user->user_id)}"));
                                $q->on(DB::raw('reserved_issued_coupons.status'), '=', DB::Raw("'reserved'"));
                        })

                        // get the last user payment in this coupon
                        /*
                        ->leftJoin(
                                    DB::raw("
                                        (
                                            SELECT
                                                object_id,
                                                pt.payment_transaction_id,
                                                payment_midtrans_info,
                                                pt.created_at,
                                                status
                                            FROM {$prefix}payment_transactions as pt
                                            INNER JOIN {$prefix}payment_transaction_details ptd ON ptd.payment_transaction_id = pt.payment_transaction_id
                                            LEFT JOIN {$prefix}payment_midtrans pm ON pm.payment_transaction_id = pt.payment_transaction_id
                                            WHERE 1=1
                                            AND pt.user_id = ".$this->quote($user->user_id)."
                                            AND ptd.object_id= ".$this->quote($couponId)."
                                            AND ptd.object_type = 'coupon'
                                            ORDER BY pt.created_at DESC
                                            LIMIT 1
                                        ) as payment")
                                    , DB::raw('payment.object_id'), '=', 'promotions.promotion_id')
                        */
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
                        ->leftJoin('coupon_sepulsa', 'coupon_sepulsa.promotion_id', '=', 'promotions.promotion_id')
                        ->with(['keywords' => function ($q) {
                                $q->addSelect('keyword', 'object_id');
                                $q->groupBy('keyword');
                            }])
                        ->with(['product_tags' => function ($pt) {
                                $pt->addSelect('product_tag', 'object_id');
                                $pt->groupBy('product_tag');
                            }])
                        ->where('promotions.promotion_id', $couponId)
                        ->where('promotions.is_visible', 'Y')
                        ->orderBy('issued_coupons.expired_date', 'asc');

            OrbitInput::get('mall_id', function($mallId) use ($coupon, &$mall) {
                $coupon->havingRaw("mall_id = {$this->quote($mallId)}");
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            if ($forRedeem === 'Y' && ! empty($selectedIssuedCouponId)) {
                $coupon->where('issued_coupons.issued_coupon_id', $selectedIssuedCouponId);
            }

            $coupon = $coupon->first();
            $message = 'Request Ok';
            if (! is_object($coupon)) {
                throw new OrbitCustomException('Coupon that you specify is not found', Coupon::NOT_FOUND_ERROR_CODE, NULL);
            }

            // Set currency and payment method information
            // so frontend can load proper payment gateway UI.
            // TODO: Set currency value for all paid coupon in DB (might need data migration)
            if (! empty($coupon->coupon_country) && $coupon->coupon_country !== 'Indonesia') {
                $coupon->currency = 'SGD';
                $coupon->payment_method = 'stripe';
            }
            else {
                $coupon->currency = 'IDR';
                $coupon->payment_method = 'midtrans';
            }

            // calculate remaining coupon for the user.
            // 9999 means unlimited quantity per user.
            $coupon->available_coupons_count = 9999;
            if (! empty($coupon->max_quantity_per_user)) {
                $coupon->available_coupons_count = $coupon->max_quantity_per_user - $coupon->used_coupons_count;
            }

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();
            }

            // Use default max quantity per purchase for old data (that doesn't have max_quantity_per_purchase set)
            if (empty($coupon->max_quantity_per_purchase)) {
                $coupon->max_quantity_per_purchase = Config::get('orbit.transaction.max_quantity_per_purchase', 5);
            }

            // Only campaign having status ongoing and is_started true can going to detail page
            if (! in_array($coupon->campaign_status, ['ongoing', 'expired']) || ($coupon->campaign_status == 'ongoing' && $coupon->is_started == 'false')) {
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

            $coupon->category_ids = $this->getCouponCategory($couponId);
            $couponPaymentHelper = App::make(PaymentRepository::class);
            $coupon = $couponPaymentHelper->addPaymentInfo($coupon, $user);
            $coupon = $couponTimezoneHelper->addTimezoneInfo($coupon);

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

            // Determine if issued coupon was transfered or not
            $coupon->is_transferred = false;
            if (! empty($coupon->original_user_id) && $coupon->original_user_id === $user->user_id
                && $coupon->transfer_status === 'complete') {
                $coupon->is_transferred = true;
            }

            $issuedCouponHelper = App::make(IssuedCouponRepository::class);
            $coupon = $issuedCouponHelper->addIssuedCouponData($coupon, $user);

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

            if ($coupon->promotion_type === 'mall') {
                $notes = 'normal';
            } else {
                $notes = $coupon->promotion_type;
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
            $this->response->data = $e->getCustomData();
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }

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

    /**
     * Get coupon categories.
     *
     * @param  string $couponId [description]
     * @return [type]           [description]
     */
    private function getCouponCategory($couponId = '')
    {
        return Coupon::select('category_merchant.category_id')
                       ->leftJoin('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                       ->leftJoin('merchants', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                       ->leftJoin('category_merchant', 'merchants.merchant_id', '=', 'category_merchant.merchant_id')
                       ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                       ->where('categories.merchant_id', 0)
                       ->where('categories.status', 'active')
                       ->where('promotions.promotion_id', $couponId)
                       ->groupBy('categories.category_id')
                       ->get()->lists('category_id');
    }
}
