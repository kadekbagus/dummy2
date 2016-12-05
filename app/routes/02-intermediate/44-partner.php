<?php
/**
 * Routes file for Intermediate Partner API
 */

/**
 * Create new partner
 */
Route::post('/app/v1/partner/new', 'IntermediateAuthController@Partner_postNewPartner');

/**
 * Update partner
 */
Route::post('/app/v1/partner/update', 'IntermediateAuthController@Partner_postUpdatePartner');

/**
 * Get search partner
 */
Route::get('/app/v1/partner/list', 'IntermediateAuthController@Partner_getSearchPartner');

/**
 * Pub get partner detail
 */
Route::get('/app/v1/pub/partner/detail', ['as' => 'pub-partner-detail', 'uses' => 'IntermediatePubAuthController@Partner\PartnerDetail_getPartnerDetail']);
