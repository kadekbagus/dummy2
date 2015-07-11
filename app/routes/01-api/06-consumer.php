<?php
/**
 * Routes file for Consumer related API
 */


/**
 * List/Search Consumer
 */
Route::group(['before' => 'orbit-settings'], function()
{
    Route::get('/api/v1/{consumer}/{search}', function()
    {
        return UserAPIController::create()->getConsumerListing();
    })->where(['consumer' => '(consumer|membership)', 'search' => '(search|list)']);
});