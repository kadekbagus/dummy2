<?php
/**
 * Route file for Setting related API
 */

/**
 * Update setting
 */
Route::post('/api/v1/setting/update', function()
{
    return SettingAPIController::create()->postUpdateSetting();
});

/**
 * List/Search setting
 */
Route::get('/api/v1/setting/search', function()
{
    return SettingAPIController::create()->getSearchSetting();
});
