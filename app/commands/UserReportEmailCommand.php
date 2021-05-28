<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UserReportEmailCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-report:email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'command for sending report email to user';
    protected $userRank;
    protected $userPoints;
    protected $pulsaPurchased;
    protected $couponPurchased;
    protected $plnTokenPurchased;
    protected $gameVoucherPurchased;
    protected $mallView;
    protected $storeView;
    protected $couponView;
    protected $promotionView;
    protected $eventView;
    protected $articleView;
    protected $productView;
    protected $mallData;
    protected $storeData;
    protected $couponData;
    protected $promotionData;
    protected $eventData;
    protected $articleData;
    protected $productData;
    protected $validLanguage;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {
            $input = ! empty($this->option('id')) ? $this->option('id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $user = User::where('user_id', '=', $input)->where('status', '=', 'active')->first();
            if (!$user) {
               throw new Exception(sprintf('user with id "%s" not found', $input), 1);
            }

            // get language
            $language = 'id';
            $this->validLanguage = Language::where('status', '=', 'active')
                                            ->where('name', $language)
                                            ->first();

            // get beginning and ending of the current month
            $currentMonthAndYear = strtotime(date('M Y', time()));
            $startDate = date('Y-m-01 00:00:00', $currentMonthAndYear);
            $endDate  = date('Y-m-t 12:59:59', $currentMonthAndYear);

            // get user rank and point
            $this->getRankAndPoint($input);

            // get purchased pulsa
            $this->getPurchasedPulsa($input, $startDate, $endDate);

            // get purchased coupon
            $this->getPurchasedCoupon($input, $startDate, $endDate);

            // get purchased game voucher
            $this->getPurchasedGameVoucher($input, $startDate, $endDate);

            // get purchased PLN Token
            $this->getPurchasedPLNToken($input, $startDate, $endDate);

            // get mall view and 3 latest malls
            $this->getViewMall($input, $startDate, $endDate);

            // get store view and 3 latest stores
            $this->getViewStore($input, $startDate, $endDate);

            // get coupon view and 3 latest coupons
            $this->getViewCoupon($input, $startDate, $endDate);

            // get promotion view and 3 latest promotions
            $this->getViewPromotion($input, $startDate, $endDate);

            // get event view and 3 latest events
            $this->getViewEvent($input, $startDate, $endDate);

            // get article view and 3 latest articles
            $this->getViewArticle($input, $startDate, $endDate);

            // get product view and 3 latest products
            $this->getViewProduct($input, $startDate, $endDate);

            if ($this->option('dry-run')) {
                $this->info(sprintf('user "%s" rank "%s"', $user->user_id, $this->userRank));
                $this->info(sprintf('user "%s" point "%s"', $user->user_id, $this->userPoints));
                $this->info(sprintf('user "%s" pulsa purchased "%s"', $user->user_id, $this->pulsaPurchased));
                $this->info(sprintf('user "%s" coupon purchased "%s"', $user->user_id, $this->couponPurchased));
                $this->info(sprintf('user "%s" game voucher purchased "%s"', $user->user_id, $this->gameVoucherPurchased));
                $this->info(sprintf('user "%s" pln token purchased "%s"', $user->user_id, $this->plnTokenPurchased));
                $this->info(sprintf('user "%s" mall view "%s"', $user->user_id, $this->mallView));
                $this->info(sprintf('user "%s" store view "%s"', $user->user_id, $this->storeView));
                $this->info(sprintf('user "%s" coupon view "%s"', $user->user_id, $this->couponView));
                $this->info(sprintf('user "%s" promotion view "%s"', $user->user_id, $this->promotionView));
                $this->info(sprintf('user "%s" event view "%s"', $user->user_id, $this->eventView));
                $this->info(sprintf('user "%s" article view "%s"', $user->user_id, $this->articleView));
                $this->info(sprintf('user "%s" product view "%s"', $user->user_id, $this->productView));
            } else {
                // send email
                Queue::push('Orbit\\Queue\\UserReportMailQueue', [
                    'user_name'             => $user->user_firstname . ' ' . $user->user_lastname,
                    'user_email'            => $user->user_email,
                    'user_rank'             => $this->userRank,
                    'user_point'            => $this->userPoints,
                    'pulsa_purchased'       => $this->pulsaPurchased,
                    'coupon_purchased'      => $this->couponPurchased,
                    'game_voucher_purchased'=> $this->gameVoucherPurchased,
                    'pln_purchased'         => $this->plnTokenPurchased,
                    'mall_view'             => $this->mallView,
                    'store_view'            => $this->storeView,
                    'coupon_view'           => $this->couponView,
                    'promotion_view'        => $this->promotionView,
                    'event_view'            => $this->eventView,
                    'article_view'          => $this->articleView,
                    'product_view'          => $this->productView,
                    'mall_data'             => $this->mallData,
                    'store_data'            => $this->storeData,
                    'coupon_data'           => $this->couponData,
                    'promotion_data'        => $this->promotionData,
                    'event_data'            => $this->eventData,
                    'article_data'          => $this->articleData,
                    'product_data'          => $this->productData
                ]);
                $this->info(sprintf('email for user "%s" has been sent!', $user->user_id));
            }

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function getRankAndPoint($userId)
    {
        $prefix = DB::getTablePrefix();
        $userRankData = DB::select(
            DB::raw("
                SELECT
                    total_game_points,
                    FIND_IN_SET(total_game_points, (
                        SELECT
                            GROUP_CONCAT(DISTINCT total_game_points ORDER BY total_game_points DESC)
                        FROM
                            {$prefix}extended_users eu

                    )) AS rank
                FROM
                    {$prefix}extended_users eu
                    where eu.user_id = " . DB::getPdo()->quote($userId)
            )
        );

        $this->userRank = (!empty($userRankData[0]->rank)) ? $userRankData[0]->rank : 'N/A';
        $this->userPoints = (!empty($userRankData[0]->total_game_points)) ? $userRankData[0]->total_game_points : 'N/A';
    }

    public function getPurchasedPulsa($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $pulsaPurchased = PaymentTransaction::selectRaw("COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id) as total")
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->where('payment_transactions.user_id','=', $userId)
                                            ->whereIn('payment_transaction_details.object_type', ['pulsa','data_plan'])
                                            ->where('payment_transactions.payment_method', '!=', 'normal')
                                            ->whereNotIn('payment_transactions.status', ['starting','denied','abort'])
                                            ->whereBetween('payment_transactions.created_at', [$startDate, $endDate])
                                            ->first();

        $this->pulsaPurchased = $pulsaPurchased->total;
    }

    public function getPurchasedGameVoucher($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $gameVoucherPurchased = PaymentTransaction::selectRaw("COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id) as total")
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->leftJoin('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                            ->where('payment_transactions.user_id','=', $userId)
                                            ->where('payment_transaction_details.object_type', 'digital_product')
                                            ->where('payment_transactions.payment_method', '!=', 'normal')
                                            ->where('payment_transactions.status', 'success')
                                            ->where('digital_products.product_type', 'game_voucher')
                                            ->whereBetween('payment_transactions.created_at', [$startDate, $endDate])
                                            ->first();

        $this->gameVoucherPurchased = $gameVoucherPurchased->total;
    }

    public function getPurchasedPLNToken($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $plnTokenPurchased = PaymentTransaction::selectRaw("COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id) as total")
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->leftJoin('digital_products', 'digital_products.digital_product_id', '=', 'payment_transaction_details.object_id')
                                            ->where('payment_transactions.user_id','=', $userId)
                                            ->where('payment_transaction_details.object_type', 'digital_product')
                                            ->where('payment_transactions.payment_method', '!=', 'normal')
                                            ->where('payment_transactions.status', 'success')
                                            ->where('digital_products.product_type', 'electricity')
                                            ->whereBetween('payment_transactions.created_at', [$startDate, $endDate])
                                            ->first();

        $this->plnTokenPurchased = $plnTokenPurchased->total;
    }

    public function getPurchasedCoupon($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $couponPurchased = PaymentTransaction::selectRaw("COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id) as total")
                                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                                            ->where('payment_transactions.user_id','=', $userId)
                                            ->where('payment_transaction_details.object_type', 'coupon')
                                            ->where('payment_transactions.payment_method', '!=', 'normal')
                                            ->whereNotIn('payment_transactions.status', ['starting','denied','abort'])
                                            ->whereBetween('payment_transactions.created_at', [$startDate, $endDate])
                                            ->first();

        $this->couponPurchased = $couponPurchased->total;
    }

    public function getViewMall($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
        if ($usingCdn) {
            $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
        }

        $mall = Activity::select(DB::raw("DISTINCT {$prefix}activities.object_id,
                                 (select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name = 'view_mall' and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                    {$image},
                                    {$prefix}merchants.name"))
                         ->leftJoin('merchants', 'merchants.merchant_id', '=', 'activities.object_id')
                         ->leftJoin('media', 'media.object_id', '=', 'activities.object_id')
                         ->where('activities.user_id', $userId)
                         // ->where('media.media_name_long', 'mall_logo_orig')
                         ->where('activities.activity_name', 'view_mall')
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         // ->orderBy('activities.created_at', 'desc')
                         ->groupBy('activities.object_id')
                         ->take(3)
                         ->get();

        if (count($mall)) {
            foreach ($mall as $key => $value) {
               $object_type = 'malls';
               $mall[$key]->link_url = $this->generateCampaignUrl($object_type, $value->object_id, $value->name);
            }
        }
        $this->mallView = (count($mall)) ? $mall[0]->total : 0;
        $this->mallData = (count($mall)) ? $mall : null;
    }

    public function getViewStore($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
        if ($usingCdn) {
            $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
        }

        $store = Activity::select(DB::raw("DISTINCT {$prefix}activities.object_id,
                                 (select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name in ('view_landing_page_coupon_detail', 'view_mall_coupon_detail') and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                    {$image},
                                    {$prefix}merchants.name"))
                         ->leftJoin('merchants', 'merchants.merchant_id', '=', 'activities.object_id')
                         ->leftJoin('media', 'media.object_id', '=', 'activities.object_id')
                         // ->where('media.media_name_long', 'retailer_logo_orig')
                         ->where('activities.user_id', $userId)
                         ->whereIn('activities.activity_name', ['view_landing_page_store_detail', 'view_mall_store_detail'])
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         // ->orderBy('activities.created_at', 'desc')
                         ->groupBy('activities.object_id')
                         ->take(3)
                         ->get();

        if (count($store)) {
            foreach ($store as $key => $value) {
               $object_type = 'stores';
               $store[$key]->link_url = $this->generateCampaignUrl($object_type, $value->object_id, $value->name);
            }
        }
        $this->storeView = (count($store)) ? $store[0]->total : 0;
        $this->storeData = (count($store)) ? $store : null;
    }

    public function getViewCoupon($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
        if ($usingCdn) {
            $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
        }

        $coupon = Activity::select(DB::raw("DISTINCT {$prefix}activities.object_id,
                                 (select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name in ('view_landing_page_coupon_detail', 'view_mall_coupon_detail') and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                   CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as promotion_name,
                                   CASE WHEN (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($this->validLanguage->language_id)}
                                        LIMIT 1
                                        ) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = default_translation.coupon_translation_id
                                        AND default_translation.merchant_language_id = {$this->quote($this->validLanguage->language_id)}
                                        LIMIT 1
                                        )
                                    ELSE
                                        (SELECT {$image}
                                        FROM {$prefix}media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($this->validLanguage->language_id)}
                                        LIMIT 1)
                                    END AS cdn_url
                                    "))
                         ->join('promotions', 'promotions.promotion_id', '=','activities.object_id')
                         ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                         ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                         ->leftJoin('coupon_translations', function ($q) {
                            $q->on('coupon_translations.promotion_id', '=', 'activities.object_id')
                              ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($this->validLanguage->language_id)}"));
                            })
                         ->leftJoin('coupon_translations as default_translation', function ($q) {
                            $q->on(DB::raw('default_translation.promotion_id'), '=', 'activities.object_id')
                              ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                            })
                         ->whereIn('activities.activity_name', ['view_landing_page_coupon_detail', 'view_mall_coupon_detail'])
                         ->where('activities.user_id', $userId)
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         ->orderBy('activities.created_at', 'desc')
                         ->take(3)
                         ->get();

        if (count($coupon)) {
            foreach ($coupon as $key => $value) {
               $object_type = 'coupons';
               $coupon[$key]->link_url = $this->generateCampaignUrl($object_type, $value->object_id, $value->promotion_name);
            }
        }
        $this->couponView = (count($coupon)) ? $coupon[0]->total : 0;
        $this->couponData = (count($coupon)) ? $coupon : null;
    }

    public function getViewPromotion($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
        if ($usingCdn) {
            $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
        }

        $promotion = Activity::select(DB::raw("DISTINCT {$prefix}activities.object_id,
                                 (select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name in ('view_landing_page_promotion_detail', 'view_mall_promotion_detail') and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                    CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                    CASE WHEN (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'news_translation_image_orig'
                                        AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'news_translation_image_orig'
                                        AND m.object_id = default_translation.news_translation_id)
                                    ELSE
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'news_translation_image_orig'
                                        AND m.object_id = {$prefix}news_translations.news_translation_id)
                                    END AS cdn_url
                                    "))
                        ->join('news', function ($q){
                            $q->on('news.news_id', '=', 'activities.object_id')
                              ->where('news.object_type', '=', 'promotion');
                            })
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations', function ($q) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($this->validLanguage->language_id)}"));
                            })
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                            })
                         ->whereIn('activities.activity_name', ['view_landing_page_promotion_detail', 'view_mall_promotion_detail'])
                         ->where('activities.user_id', $userId)
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         ->orderBy('activities.created_at', 'desc')
                         ->take(3)
                         ->get();

        if (count($promotion)) {
            foreach ($promotion as $key => $value) {
               $object_type = 'promotions';
               $promotion[$key]->link_url = $this->generateCampaignUrl($object_type, $value->object_id, $value->news_name);
            }
        }
        $this->promotionView = (count($promotion)) ? $promotion[0]->total : 0;
        $this->promotionData = (count($promotion)) ? $promotion : null;
    }

    public function getViewEvent($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
        if ($usingCdn) {
            $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
        }

        $event = Activity::select(DB::raw("DISTINCT {$prefix}activities.object_id,
                                 (select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name in ('view_landing_page_news_detail', 'view_mall_event_detail', 'view_landing_page_promotional_event_detail', 'view_mall_promotional_event_detail') and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                    CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                    CASE WHEN (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'news_translation_image_orig'
                                        AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'news_translation_image_orig'
                                        AND m.object_id = default_translation.news_translation_id)
                                    ELSE
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'news_translation_image_orig'
                                        AND m.object_id = {$prefix}news_translations.news_translation_id)
                                    END AS cdn_url,
                                    {$prefix}news.is_having_reward
                                    "))
                        ->join('news', function ($q){
                            $q->on('news.news_id', '=', 'activities.object_id')
                              ->where('news.object_type', '=', 'news');
                            })
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations', function ($q) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($this->validLanguage->language_id)}"));
                            })
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                            })
                         ->whereIn('activities.activity_name', ['view_landing_page_news_detail', 'view_mall_event_detail', 'view_landing_page_promotional_event_detail', 'view_mall_promotional_event_detail'])
                         ->where('activities.user_id', $userId)
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         ->orderBy('activities.created_at', 'desc')
                         ->take(3)
                         ->get();

        if (count($event)) {
            foreach ($event as $key => $value) {
               $object_type = ($value->is_having_reward === 'Y') ? 'promotional-events' : 'events';
               $event[$key]->link_url = $this->generateCampaignUrl($object_type, $value->object_id, $value->news_name);
            }
        }
        $this->eventView = (count($event)) ? $event[0]->total : 0;
        $this->eventData = (count($event)) ? $event : null;
    }

    public function getViewArticle($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
        if ($usingCdn) {
            $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
        }

        $article = Activity::select(DB::raw("DISTINCT {$prefix}activities.object_id,
                                 (select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name = 'view_article_detail' and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                    {$image},
                                    {$prefix}articles.title"))
                         ->leftJoin('articles', 'articles.article_id', '=', 'activities.object_id')
                         ->leftJoin('media', 'media.object_id', '=', 'activities.object_id')
                         // ->where('media.media_name_long', 'article_cover_image_orig')
                         ->where('activities.user_id', $userId)
                         ->where('activities.activity_name', 'view_article_detail')
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         // ->orderBy('activities.created_at', 'desc')
                         ->groupBy('activities.object_id')
                         ->take(3)
                         ->get();

        if (count($article)) {
            foreach ($article as $key => $value) {
               $object_type = 'articles';
               $article[$key]->link_url = $this->generateArticleUrl($object_type, $value->title);
            }
        }
        $this->articleView = (count($article)) ? $article[0]->total : 0;
        $this->articleData = (count($article)) ? $article : null;
    }

    public function getViewProduct($userId, $startDate, $endDate)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
        if ($usingCdn) {
            $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
        }

        // @todo: change temporary query after fixing view_product_detail_page issue
        $product = Activity::select(DB::raw("(select count({$prefix}activities.activity_id) from {$prefix}activities
                                   where activity_name = 'view_brand_product_detail_page' and user_id = ".DB::getPdo()->quote($userId)."
                                   and (created_at between ".DB::getPdo()->quote($startDate)." and ".DB::getPdo()->quote($endDate).")) as total,
                                    post_data"))
                         ->where('activities.user_id', $userId)
                         ->where('activities.activity_name', 'view_brand_product_detail_page')
                         ->whereBetween('activities.created_at', [$startDate, $endDate])
                         ->groupBy('activities.object_id')
                         ->take(3)
                         ->get();

        foreach ($product as $item) {
            $postData = serialize($item->post_data);
            $item->object_id = isset($postData['object_id']) ? $postData['object_id'] : null;
            $product = Product::select(DB::raw("
                    {$prefix}products.product_id,
                    {$prefix}products.name,
                    {$image}"
                ))
                ->leftJoin('media', 'media.object_id', '=', 'activities.object_id')
                ->where('product_id', $item->object_id)
                ->firstOrFail();
            $item->title = $product->name;
            $item->cdn_url = $product->cdn_url;
        }


        if (count($product)) {
            foreach ($product as $key => $value) {
               $product[$key]->link_url = $this->generateProductUrl($value->title);
            }
        }
        $this->productView = (count($product)) ? $product[0]->total : 0;
        $this->productData = (count($product)) ? $product : null;
    }

    public function generateCampaignUrl($objectType = '', $campaignId, $campaignName)
    {
        $format = "/{$objectType}/%s/%s?country=Indonesia";
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . sprintf($format, $campaignId, Str::slug($campaignName));
    }

    public function generateArticleUrl($objectType = '', $articleName)
    {
        $format = "/{$objectType}/%s?country=Indonesia";
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . sprintf($format, Str::slug($articleName));
    }

    public function generateProductUrl($productName)
    {
        $format = "/products/affiliate/%s?country=Indonesia";
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . sprintf($format, Str::slug($productName));
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
             array('id', null, InputOption::VALUE_OPTIONAL, 'the user id.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent', null),
        );
    }

}
