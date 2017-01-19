<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;


/**
 * Listen on:    `orbit.tenant.postnewtenant.after.save`
 * Purpose:      Handle file upload on tenant creation with object type tenant or store
 *
 * @author Tian <tian@dominopos.com>
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $tenant - Instance of object Event
 * @param Event $tenant->object_type - value : tenant or service
 */
Event::listen('orbit.tenant.postnewtenant.after.save', function($controller, $tenant)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $tenant->load('mediaLogo');

    $maps = OrbitInput::files('maps');

    if (! empty($maps)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_map', $response->data);
        $tenant->media_map = $response->data;
    }
    $tenant->load('mediaMap');

    $pictures = OrbitInput::files('pictures');

    if (! empty($pictures)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_image', $response->data);
        $tenant->media_image = $response->data;
    }
    $tenant->load('mediaImage');
});


/**
 * Listen on:       `orbit.tenant.postupdatetenant.after.save`
 *   Purpose:       Handle file upload on tenant update
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param TenantAPIController $controller - The instance of the TenantAPIController or its subclass
 * @param Retailer $tenant - Instance of object Merchant
 * @param Event $tenant->object_type - value : tenant or service
 *
 */
Event::listen('orbit.tenant.postupdatetenant.after.save', function($controller, $tenant)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $tenant->load('mediaLogo');

    $maps = OrbitInput::files('maps');

    if (! empty($maps)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_map', $response->data);
        $tenant->media_map = $response->data;
    }
    $tenant->load('mediaMap');

    $pictures = OrbitInput::files('pictures');

    if (! empty($pictures)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_image', $response->data);
        $tenant->media_image = $response->data;
    }
    $tenant->load('mediaImage');
});

/**
 * Listen on:    `orbit.tenant.postnewtenant.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param TenantAPIController $controller - The instance of the TenantAPIController or its subclass
 * @param Tenant $tenant - Instance of object Tenant
 */
Event::listen('orbit.tenant.postupdatetenant.after.commit', function($controller, $tenant)
{
    // find coupon relate with tenant to update ESCoupon
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
                ->join('promotion_retailer', function($q) {
                    $q->on('promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                      ->on('promotion_retailer.object_type', '=', DB::raw("'tenant'"));
                })
                ->where('promotion_retailer.retailer_id', '=', $tenant->merchant_id)
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

    //check news data for update elasticsearch
    $news = News::excludeDeleted('news')
            ->select(DB::raw("
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
            ->join('news_merchant', function($q) {
                    $q->on('news_merchant.news_id', '=', 'news.news_id')
                      ->on('news_merchant.object_type', '=', DB::raw("'retailer'"));
              })
            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
            ->where('news_merchant.merchant_id', '=', $tenant->merchant_id)
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

    //check promotions data for update elasticsearch
    $promotions = News::excludeDeleted('news')
            ->select(DB::raw("
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
            ->join('news_merchant', function($q) {
                    $q->on('news_merchant.news_id', '=', 'news.news_id')
                      ->on('news_merchant.object_type', '=', DB::raw("'retailer'"));
              })
            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
            ->where('news_merchant.merchant_id', '=', $tenant->merchant_id)
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

    // update the store on elasticsearch
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
        'name' => $tenant->name
    ]);
});

Event::listen('orbit.tenant.postnewtenant.after.commit', function($controller, $tenant)
{
    // update the store on elasticsearch
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
        'name' => $tenant->name
    ]);
});
