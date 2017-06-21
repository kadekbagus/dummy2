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


/**
 * Get search seo text for frontend app
 */
Route::get('/api/v1/pub/seo-text', function()
{
    return Orbit\Controller\API\v1\Pub\SeoTextAPIController::create()->getSeoText();
});

Route::get('/app/v1/pub/seo-text', ['as' => 'pub-seo-text', 'uses' => 'IntermediatePubAuthController@SeoText_getSeoText']);
