<?php
/**
 * Routes file for Sponsor Bank, E-wallet, Credit card related API
 */

/**
 * Create sponsor bank, e-wallet, credit card
 */
Route::post('/app/v1/sponsor-bank/new', 'IntermediateAuthController@SponsorBank_postNewSponsorBank');

/**
 * Update sponsor bank, e-wallet, credit card
 */
Route::post('/app/v1/sponsor-bank/update', 'IntermediateAuthController@SponsorBank_postUpdateSponsorBank');

/**
 * Get search sponsor bank, e-wallet, credit card
 */
Route::get('/app/v1/sponsor-bank/list', 'IntermediateAuthController@SponsorBank_getSearchSponsorBank');