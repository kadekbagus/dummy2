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
 * Get link to sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/link-to-sponsor/list', function()
{
    return LinkToSponsorAPIController::create()->getLinkToSponsor();
});
