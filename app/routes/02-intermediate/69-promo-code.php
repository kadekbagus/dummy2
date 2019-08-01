<?php

/**
 * Promo Code use
 */
Route::post('/app/v1/pub/promocode/use', ['as' => 'promocode-use', 'uses' => 'IntermediatePubAuthController@PromoCode\PromoCodeCheck_postCheckPromoCode']);
