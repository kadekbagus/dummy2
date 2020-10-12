<?php

// route for setting list
Route::get('/api/v1/setting-page/list', function()
{
    return Orbit\Controller\API\v1\Product\Setting\SettingListAPIController::create()->getList();
});

Route::get('/app/v1/setting-page/list', ['as' => 'settings-page-list', 'uses' => 'IntermediateProductAuthController@Setting\SettingList_getList']);


Route::post('/api/v1/setting-page/toggle', function()
{
    return Orbit\Controller\API\v1\Product\Setting\SettingToggleAPIController::create()->postToggle();
});
Route::post('/app/v1/setting-page/toggle', ['as' => 'settings-page-toggle', 'uses' => 'IntermediateProductAuthController@Setting\SettingToggle_postToggle']);