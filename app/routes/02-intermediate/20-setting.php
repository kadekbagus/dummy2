<?php
/**
 * Route file for Intermediate Setting API
 */

/**
 * Update setting
 */
Route::post('/app/v1/setting/update', 'IntermediateAuthController@Setting_postUpdateSetting');

/**
 * List/Search setting
 */
Route::get('/app/v1/setting/search', 'IntermediateAuthController@Setting_getSearchSetting');

/**
 * Update mall setting
 */
Route::post('/app/v1/mall-setting/update', 'IntermediateAuthController@Setting_postUpdateMallSetting');

/**
 * List/Search mall setting
 */
Route::get('/app/v1/mall-setting/list', 'IntermediateAuthController@Setting_getSearchMallSetting');

Route::group(['before' => 'orbit-settings'], function() {

    /**
     * Get agreement setting
     */
    Route::get('/app/v1/agreement', 'IntermediateLoginController@Setting_getAgreement');

    /**
     * Create/update agreement setting
     */
    Route::post('/app/v1/agreement', 'IntermediateLoginController@Setting_postUpdateAgreement');

});
