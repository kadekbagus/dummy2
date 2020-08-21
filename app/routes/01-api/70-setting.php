<?php

/**
 * List rating and review
 */
Route::get('/api/v1/pub/setting/page', function()
{
    return Orbit\Controller\API\v1\Pub\Setting\SettingPageAPIController::create()->getRatingList();
});

Route::get('/app/v1/pub/setting/page', ['as' => 'setting-page', 'uses' => 'IntermediatePubAuthController@Setting\SettingPage_getSetting']);