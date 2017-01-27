<?php
/**
 * Routes file for DBIP related API
 */

/**
 * Get search county based on dbip country
 */
Route::get('/api/v1/dbip-country/list', function()
{
    return DBIPAPIController::create()->getSearchDBIPCountry();
});

/**
 * Get search city base on dbip city
 */
Route::get('/api/v1/dbip-city/list', function()
{
    return DBIPAPIController::create()->getSearchDBIPCity();
});