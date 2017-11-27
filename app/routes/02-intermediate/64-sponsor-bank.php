<?php
/**
 * Routes file for Sponsor Bank, E-wallet, Credit card related API
 */

/**
 * Create sponsor bank, e-wallet, credit card
 */
Route::post('/app/v1/sponsor-bank/new', 'IntermediateAuthController@SponsorProvider_postNewSponsorProvider');

/**
 * Update sponsor bank, e-wallet, credit card
 */
Route::post('/app/v1/sponsor-bank/update', 'IntermediateAuthController@SponsorProvider_postUpdateSponsorProvider');

/**
 * Get search sponsor bank, e-wallet, credit card
 */
Route::get('/app/v1/sponsor-bank/list', 'IntermediateAuthController@SponsorProvider_getSearchSponsorProvider');

/**
 * Get link to sponsor bank, e-wallet, credit card
 */
Route::get('/app/v1/link-to-sponsor/list', 'IntermediateAuthController@LinkToSponsor_getLinkToSponsor');