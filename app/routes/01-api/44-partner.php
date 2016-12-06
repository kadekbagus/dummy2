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
 * Update partner
 */
Route::post('/api/v1/partner/update', function()
{
    return PartnerAPIController::create()->postUpdatePartner();
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


/**
 * Upload logo partner
 */
Route::post('/api/v1/partner/upload/logo', function()
{
    return UploadAPIController::create()->postUploadPartnerLogo();
});

/**
 * Upload logo partner
 */
Route::post('/api/v1/partner/upload/image', function()
{
    return UploadAPIController::create()->postUploadPartnerImage();
});

/**
 * Delete logo partner
 */
Route::post('/api/v1/partner/delete/logo', function()
{
    return UploadAPIController::create()->postDeletePartnerLogo();
});

/**
 * Delete logo partner
 */
Route::post('/api/v1/partner/delete/image', function()
{
    return UploadAPIController::create()->postDeletePartnerImage();
});
