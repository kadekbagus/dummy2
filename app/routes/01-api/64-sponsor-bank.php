<?php
/**
 * Routes file for Sponsor Bank, E-wallet, Credit card related API
 */

/**
 * Create sponsor bank, e-wallet, credit card
 */
Route::post('/api/v1/sponsor-bank/new', function()
{
    return SponsorBankAPIController::create()->postNewSponsorBank();
});

/**
 * Update sponsor bank, e-wallet, credit card
 */
Route::post('/api/v1/sponsor-bank/update', function()
{
    return SponsorBankAPIController::create()->postUpdateSponsorBank();
});

/**
 * Get search sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/sponsor-bank/list', function()
{
    return SponsorBankAPIController::create()->getSearchSponsorBank();
});


/**
 * Also like List of news
 */
Route::get('/api/v1/pub/sponsor-provider/list', function()
{
    return Orbit\Controller\API\v1\Pub\SponsorProviderListAPIController::create()->getSponsorProviderList();
});

Route::get('/app/v1/pub/sponsor-provider/list', ['as' => 'pub-sponsor-provider-list', 'uses' => 'IntermediatePubAuthController@SponsorProviderList_getSponsorProviderList']);