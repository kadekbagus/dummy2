<?php
/**
 * Routes file for Tenant related API
 */

/**
 * Create new tenant
 */
Route::post('/api/v1/mall/new', function()
{
    return MallAPIController::create()->postNewMall();
});

/**
 * Delete tenant
 */
Route::post('/api/v1/mall/delete', function()
{
    return MallAPIController::create()->postDeleteMall();
});

?>