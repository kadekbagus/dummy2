<?php
/**
 * Routes file for Location Detection
 */

/**
 * Get country and city for GPS enabled/disabled API
 */
Route::get('/api/v1/pub/detect-location', function()
{
    return LocationDetectionAPIController::create()->getCountryAndCity();
});
