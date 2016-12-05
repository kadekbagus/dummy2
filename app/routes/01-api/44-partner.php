<?php
/**
 * Routes file for Partner related API
 */

/**
 * Create new partner
 */
Route::post('/api/v1/partner/new', function()
{
    return PartnerAPIController::create()->postNewPartner();
});

/**
 * Get search partner
 */
Route::get('/api/v1/partner/list', function()
{
    return PartnerAPIController::create()->getSearchPartner();
});

/**
 * Pub get search partner
 */
Route::get('/api/v1/pub/partner/list', function()
{
    return Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create()->getSearchPartner();
});

/**
 * Pub get search detail
 */
Route::get('/api/v1/pub/partner/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Partner\PartnerDetailAPIController::create()->getPartnerDetail();
});
