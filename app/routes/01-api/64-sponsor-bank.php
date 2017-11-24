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
 * Get link to sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/link-to-sponsor/list', function()
{
    return LinkToSponsorAPIController::create()->getLinkToSponsor();
});
