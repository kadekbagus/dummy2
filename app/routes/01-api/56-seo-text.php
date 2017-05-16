<?php
/**
 * Routes file for Seo Text related API
 */

/**
 * Create new seo text
 */
Route::post('/api/v1/seo-text/new', function()
{
    return SeoTextAPIController::create()->postNewSeoText();
});

/**
 * Update seo text
 */
Route::post('/api/v1/seo-text/update', function()
{
    return SeoTextAPIController::create()->postUpdateSeoText();
});

/**
 * Get search seo text
 */
Route::get('/api/v1/seo-text/list', function()
{
    return SeoTextAPIController::create()->getSearchSeoText();
});
