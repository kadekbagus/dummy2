<?php

use Orbit\Controller\API\v1\Pub\PromoCode\PromoCodeCheckAPIController;
/**
 * Promo Code use
 */
Route::post('/api/v1/pub/promocode/use', function () {
    return PromoCodeCheckAPIController::create()->postCheckPromoCode();
});

/**
 * Promo Code create
 */
Route::post('/api/v1/promo-code/new', function()
{
    return PromoCodeAPIController::create()->postNewPromoCode();
});

/**
 * Promo Code update
 */
Route::post('/api/v1/promo-code/update', function()
{
    return PromoCodeAPIController::create()->postUpdatePromoCode();
});

/**
 * Promo Code list
 */
Route::get('/api/v1/promo-code/list', function()
{
    return PromoCodeAPIController::create()->getSearchPromoCode();
});

/**
 * Promo Code Detail
 */
Route::get('/api/v1/promo-code/detail', function()
{
    return PromoCodeAPIController::create()->getDetailPromoCode();
});
