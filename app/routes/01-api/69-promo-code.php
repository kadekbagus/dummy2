<?php

use Orbit\Controller\API\v1\Pub\PromoCode\PromoCodeCheckAPIController;
/**
 * Promo Code use
 */
Route::post('/api/v1/pub/promocode/use', function () {
    return PromoCodeCheckAPIController::create()->postCheckPromoCode();
});
