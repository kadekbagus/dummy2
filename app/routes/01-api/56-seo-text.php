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
