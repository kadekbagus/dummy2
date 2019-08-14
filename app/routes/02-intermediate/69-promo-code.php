<?php

/**
 * Promo Code use
 */
Route::post('/app/v1/pub/promocode/use', ['as' => 'promocode-use', 'uses' => 'IntermediatePubAuthController@PromoCode\PromoCodeCheck_postCheckPromoCode']);

/**
 * Get detail promo code
 */
Route::get('/app/v1/pub/promocode/detail', 'IntermediatePubAuthController@PromoCode\PromoCodeDetail_getPromoCode');

/**
 * Routes file for Intermediate Promo Code API
 */

/**
 * Create new promo code
 */
Route::post('/app/v1/promo-code/new', 'IntermediateAuthController@PromoCode_postNewPromoCode');

/**
 * Update promo code
 */
Route::post('/app/v1/promo-code/update', 'IntermediateAuthController@PromoCode_postUpdatePromoCode');

/**
 * Get search promo code
 */
Route::get('/app/v1/promo-code/list', 'IntermediateAuthController@PromoCode_getSearchPromoCode');

/**
 * Get detail promo code
 */
Route::get('/app/v1/promo-code/detail', 'IntermediateAuthController@PromoCode_getDetailPromoCode');
