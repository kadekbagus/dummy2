<?php
/**
 * Routes file for Popular related API
 */

/**
 * List of popular campaign
 */
Route::get('/api/v1/pub/popular-list', function()
{
    return Orbit\Controller\API\v1\Pub\PopularListAPIController::create()->getSearchPopular();
});