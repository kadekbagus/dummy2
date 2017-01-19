<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;


/**
 * Listen on:    `orbit.mall.postnewmall.after.save`
 * Purpose:      Handle file upload on mallgroup creation
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postnewmall.after.save', function($controller, $mall)
{
    //Upload mall logo
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $mall->merchant_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        // queue for data amazon s3
        $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

        if ($usingCdn) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
            if ($response->data['extras']->isUpdate) {
                $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
            }

            Queue::push($queueFile, [
                'object_id' => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path' => $response->data['extras']->oldPath
            ], 'cdn_upload');
        }
    }
    $mall->load('mediaLogo');

    // Update mall maps
    $maps = OrbitInput::files('maps');
    if (! empty($maps)) {
        $_POST['merchant_id'] = $mall->merchant_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $mall->setRelation('media_map', $response->data);
        $mall->media_map = $response->data;

        // queue for data amazon s3
        $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

        if ($usingCdn) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
            if ($response->data['extras']->isUpdate) {
                $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
            }

            Queue::push($queueFile, [
                'object_id' => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path' => $response->data['extras']->oldPath
            ], 'cdn_upload');
        }
    }
    $mall->load('mediaMap');
});


/**
 * Listen on:     `orbit.mall.postupdatemall.after.save`
 * Purpose:       Handle file upload on mall update
 *
 * @author Irianto <irianto@dominopos.com>
 * @param MallAPIController $controller - The instance of the MallAPIController or its subclass
 * @param Mall $mall - Instance of object Mall
 */
Event::listen('orbit.mall.postupdatemall.after.save', function($controller, $mall)
{
    $logo = OrbitInput::files('logo');
    $_POST['merchant_id'] = $mall->merchant_id;

    // Update logo
    if (empty($logo)) {
        // Delete mall logo
        OrbitInput::post('logo', function($logo_string) use ($controller, $mall) {
            if (empty(trim($logo_string))) {
                // This will be used on UploadAPIController
                App::instance('orbit.upload.user', $controller->api->user);

                $response = UploadAPIController::create('raw')
                                               ->setCalledFrom('mall.update')
                                               ->postDeleteMallLogo();
                if ($response->code !== 0)
                {
                    throw new \Exception($response->message, $response->code);
                }

                // queue for data amazon s3
                $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

                if ($usingCdn) {
                    $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadDeleteQueue';
                    Queue::push($queueFile, [
                        'object_id' => $mall->merchant_id,
                        'media_name_id' => 'mall_logo',
                    ], 'cdn_upload');
                }
            }
        });
    } else {
        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        // queue for data amazon s3
        $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

        if ($usingCdn) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
            if ($response->data['extras']->isUpdate) {
                $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
            }

            Queue::push($queueFile, [
                'object_id' => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path' => $response->data['extras']->oldPath
            ], 'cdn_upload');
        }
    }
    $mall->load('mediaLogo');

    // Upload map
    $maps = OrbitInput::files('maps');
    if (! empty($maps)) {
        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $mall->setRelation('media_map', $response->data);
        $mall->media_map = $response->data;

        // queue for data amazon s3
        $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

        if ($usingCdn) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
            if ($response->data['extras']->isUpdate) {
                $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
            }

            Queue::push($queueFile, [
                'object_id' => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path' => $response->data['extras']->oldPath
            ], 'cdn_upload');
        }
    }
    $mall->load('mediaMap');

});

/**
 * Listen on:    `orbit.mall.postnewmall.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Rio Astamal <rio@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postnewmall.after.commit', function($controller, $mall)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallCreateQueue', [
        'mall_id' => $mall->merchant_id
    ]);
});

/**
 * Listen on:    `orbit.mall.postupdatemall.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postupdatemall.after.commit', function($controller, $mall)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallUpdateQueue', [
        'mall_id' => $mall->merchant_id
    ]);

    // find coupon relate with mall to update ESCoupon
    // check coupon before update elasticsearch
    $prefix = DB::getTablePrefix();
    $coupons = Coupon::excludeDeleted('promotions')
                ->select(DB::raw("
                    {$prefix}promotions.promotion_id,
                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                        THEN {$prefix}campaign_status.campaign_status_name
                        ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                        FROM {$prefix}promotion_retailer opt
                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                    )
                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END)
                    END AS campaign_status,
                    COUNT({$prefix}issued_coupons.issued_coupon_id) as available
                "))
                ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                ->leftJoin('issued_coupons', function($q) {
                    $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                        ->where('issued_coupons.status', '=', "available");
                })
                ->join('promotion_retailer as pr', DB::raw('pr.promotion_id'), '=', 'promotions.promotion_id')
                ->leftJoin('merchants as mp', function ($q) {
                    $q->on(DB::raw('mp.merchant_id'), '=', DB::raw('pr.retailer_id'))
                      ->on(DB::raw('mp.object_type'), '=', DB::raw("'tenant'"));
                })
                ->whereRaw("CASE WHEN pr.object_type = 'mall' THEN pr.retailer_id ELSE mp.parent_id END = '{$mall->merchant_id}'")
                ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                ->groupBy('promotions.promotion_id')
                ->get();

    foreach ($coupons as $coupon) {
        if ($coupon->campaign_status === 'stopped' || $coupon->campaign_status === 'expired' || $coupon->available === 0) {
            // Notify the queueing system to delete Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);
        } else {
            // Notify the queueing system to update Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);
        }
    }

    // check news data related to the mall, for update or delete elasticsearch news
    $news = News::select(DB::raw("
                {$prefix}news.news_id,
                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                THEN {$prefix}campaign_status.campaign_status_name
                ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id)
               THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
            "))
            ->excludeDeleted('news')
            ->join('news_merchant as nm', DB::raw('nm.news_id'), '=', 'news.news_id')
            ->leftJoin('merchants as mp', function ($q) {
                    $q->on(DB::raw('mp.merchant_id'), '=', DB::raw('nm.merchant_id'))
                      ->on(DB::raw('mp.object_type'), '=', DB::raw("'tenant'"));
              })
            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
            ->whereRaw("CASE WHEN nm.object_type = 'mall' THEN nm.merchant_id ELSE mp.parent_id END = '{$mall->merchant_id}'")
            ->where('news.object_type', '=', 'news')
            ->get();

    if (!(count($news) < 1)) {
        foreach ($news as $_news) {

            if ($_news->campaign_status === 'stopped' || $_news->campaign_status === 'expired') {
                // Notify the queueing system to delete Elasticsearch document
                Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsDeleteQueue', [
                    'news_id' => $_news->news_id
                ]);
            } else {
                // Notify the queueing system to update Elasticsearch document
                Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', [
                    'news_id' => $_news->news_id
                ]);
            }
        }
    }


    // check promotions data related to the mall, for update or delete elasticsearch promotions
    $promotions = News::select(DB::raw("
                {$prefix}news.news_id,
                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                THEN {$prefix}campaign_status.campaign_status_name
                ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id)
               THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
            "))
            ->excludeDeleted('news')
            ->join('news_merchant as nm', DB::raw('nm.news_id'), '=', 'news.news_id')
            ->leftJoin('merchants as mp', function ($q) {
                    $q->on(DB::raw('mp.merchant_id'), '=', DB::raw('nm.merchant_id'))
                      ->on(DB::raw('mp.object_type'), '=', DB::raw("'tenant'"));
              })
            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
            ->whereRaw("CASE WHEN nm.object_type = 'mall' THEN nm.merchant_id ELSE mp.parent_id END = '{$mall->merchant_id}'")
            ->where('news.object_type', '=', 'promotion')
            ->get();

    if (!(count($promotions) < 1)) {
        foreach ($promotions as $_promotions) {

            if ($_promotions->campaign_status === 'stopped' || $_promotions->campaign_status === 'expired') {
                // Notify the queueing system to delete Elasticsearch document
                Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionDeleteQueue', [
                    'news_id' => $_promotions->news_id
                ]);
            } else {
                // Notify the queueing system to update Elasticsearch document
                Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', [
                    'news_id' => $_promotions->news_id
                ]);
            }
        }
    }

    // check all store that belongs to the mall and then update store index on es
    $store = Tenant::select('merchants.name')
                    ->excludeDeleted('merchants')
                    ->join(DB::raw("(
                            select merchant_id
                            from {$prefix}merchants
                            where status = 'active'
                            and object_type = 'mall'
                        ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                    ->where('merchants.status', '=', 'active')
                    ->where(DB::raw('oms.merchant_id'), '=', $mall->merchant_id)
                    ->get();

    if (!$store->isEmpty()) {
        foreach ($store as $_store) {
            // Notify the queueing system to update Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
                'name' => $_store->name
            ]);
        }
    }

});

/**
 * Listen on:    `orbit.mall.postdeletemall.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postdeletemall.after.commit', function($controller, $mall)
{
    // Notify the queueing system to delete Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallDeleteQueue', [
        'mall_id' => $mall->merchant_id
    ]);
});