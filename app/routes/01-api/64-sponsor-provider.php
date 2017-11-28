<?php
/**
 * Routes file for Sponsor Bank, E-wallet, Credit card related API
 */

/**
 * Create sponsor bank, e-wallet, credit card
 */
Route::post('/api/v1/sponsor-provider/new', function()
{
    return SponsorProviderAPIController::create()->postNewSponsorProvider();
});

/**
 * Update sponsor bank, e-wallet, credit card
 */
Route::post('/api/v1/sponsor-provider/update', function()
{
    return SponsorProviderAPIController::create()->postUpdateSponsorProvider();
});

/**
 * Get search sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/sponsor-provider/list', function()
{
    return SponsorProviderAPIController::create()->getSearchSponsorProvider();
});

/**
 * Get search detail sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/sponsor-provider/detail', function()
{
    return SponsorProviderAPIController::create()->getSearchSponsorProviderDetail();
});


/**
 * Get link to sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/link-to-sponsor/list', function()
{
    return LinkToSponsorAPIController::create()->getLinkToSponsor();
});

/**
 * sponsor bank list
 */
Route::get('/api/v1/pub/user-sponsor/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\SponsorListAPIController::create()->getSponsorList();
});

Route::get('/app/v1/pub/user-sponsor/list', ['as' => 'pub-user-sponsor-list', 'uses' => 'IntermediatePubAuthController@Sponsor\SponsorList_getSponsorList']);