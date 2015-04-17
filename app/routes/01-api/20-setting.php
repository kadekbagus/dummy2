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
 * Update mall setting
 */
Route::post('/api/v1/mall-setting/update', function()
{
    return SettingAPIController::create()->postUpdateMallSetting();
});

/**
 * List/Search setting
 */
Route::get('/api/v1/setting/search', function()
{
    return SettingAPIController::create()->getSearchSetting();
});

/**
 * List mall setting
 */
Route::post('/api/v1/mall-setting/list', function()
{
    return SettingAPIController::create()->getSearchMallSetting();
});
