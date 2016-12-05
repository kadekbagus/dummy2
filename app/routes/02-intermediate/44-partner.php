<?php
/**
 * Routes file for Intermediate Partner API
 */

/**
 * Create new partner
 */
Route::post('/app/v1/partner/new', 'IntermediateAuthController@Partner_postNewPartner');

/**
 * Get search partner
 */
Route::get('/app/v1/partner/list', 'IntermediateAuthController@Partner_getSearchPartner');

/**
 * Pub get search partner
 */
Route::get('/app/v1/pub/partner/list', ['as' => 'pub-partner-list', 'uses' => 'IntermediatePubAuthController@Partner\PartnerList_getSearchPartner']);

/**
 * Pub get partner detail
 */
Route::get('/app/v1/pub/partner/detail', ['as' => 'pub-partner-detail', 'uses' => 'IntermediatePubAuthController@Partner\PartnerDetail_getPartnerDetail']);
