<?php
/**
 * Routes file for Sponsor Bank, E-wallet, Credit card related API
 */

/**
 * Create sponsor bank, e-wallet, credit card
 */
Route::post('/app/v1/sponsor-provider/new', 'IntermediateAuthController@SponsorProvider_postNewSponsorProvider');

/**
 * Update sponsor bank, e-wallet, credit card
 */
Route::post('/app/v1/sponsor-provider/update', 'IntermediateAuthController@SponsorProvider_postUpdateSponsorProvider');

/**
 * Get search sponsor bank, e-wallet, credit card
 */
Route::get('/app/v1/sponsor-provider/list', 'IntermediateAuthController@SponsorProvider_getSearchSponsorProvider');

/**
 * Get search detail sponsor bank, e-wallet, credit card
 */
Route::get('/app/v1/sponsor-provider/detail', 'IntermediateAuthController@SponsorProvider_getSearchSponsorProviderDetail');

/**
 * Get link to sponsor bank, e-wallet, credit card
 */
Route::get('/app/v1/link-to-sponsor/list', 'IntermediateAuthController@LinkToSponsor_getLinkToSponsor');