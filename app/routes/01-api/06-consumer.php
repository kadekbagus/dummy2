<?php
/**
 * Routes file for Consumer related API
 */


/**
 * List/Search Consumer
 */
Route::get('/api/v1/consumer/search', function()
{
    return UserAPIController::create()->getConsumerListing();
});