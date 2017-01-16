<?php
/**
 * Event listener for Advert related events.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.advert.postnewadvert.after.save`
 * Purpose:      Handle file upload on advert creation
 *
 * @param AdvertAPIController $controller - The instance of the AdvertAPIController or its subclass
 * @param Advert $advert - Instance of object Advert
 */
Event::listen('orbit.advert.postnewadvert.after.save', function($controller, $advert)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['advert_id'] = $advert->advert_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('advert.new')
                                   ->postUploadAdvertImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['advert_id']);

    $advert->setRelation('media', $response->data);
    $advert->media = $response->data;
    $advert->image = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.advert.postupdateadvert.after.save`
 *   Purpose:       Handle file upload on advert update
 *
 * @param AdvertAPIController $controller - The instance of the AdvertAPIController or its subclass
 * @param Advert $advert - Instance of object Advert
 */
Event::listen('orbit.advert.postupdateadvert.after.save', function($controller, $advert)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['advert_id'] = $advert->advert_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('advert.update')
                                       ->postUploadAdvertImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $advert->load('media');
        $advert->image = $response->data[0]->path;
    }

});

/**
 * Listen on:    `orbit.advert.postnewadvert.after.`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param AdvertAPIController $controller - The instance of the AdvertAPIController or its subclass
 * @param Advert $advert - Instance of object Advert
 */
Event::listen('orbit.advert.postnewadvert.after.commit', function($controller, $advert)
{
    // find coupon relate with advert to update ESCoupon
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
                ->join('adverts', 'adverts.link_object_id', '=', 'promotions.promotion_id')
                ->where('adverts.advert_id', '=', $advert->advert_id)
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

    // checking promotions for updating elasticsearch data
    $promotion = News::excludeDeleted('news')
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
            ->join('adverts', 'adverts.link_object_id', '=', 'news.news_id')
            ->where('adverts.advert_id', '=', $advert->advert_id)
            ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
            ->where('news.object_type', '=', 'promotion')
            ->groupBy('news.news_id')
            ->first();

    if (is_object($promotion)) {
        if ($promotion->campaign_status === 'stopped' || $promotion->campaign_status === 'expired') {
            // Notify the queueing system to delete Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionDeleteQueue', [
                'news_id' => $promotion->news_id
            ]);
        } else {
            // Notify the queueing system to update Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', [
                'news_id' => $promotion->news_id
            ]);
        }
    }

});