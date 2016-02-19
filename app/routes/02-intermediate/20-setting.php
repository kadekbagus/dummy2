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
    Route::get('/app/v1/agreement', ['as' => 'get-app-agreement', 'uses' => 'IntermediateLoginController@Setting_getAgreement']);

    /**
     * Create/update agreement setting
     */
    Route::post('/app/v1/agreement', ['as' => 'post-app-agreement', 'uses' => 'IntermediateLoginController@Setting_postUpdateAgreement']);

});


/**
 * get mobile ci signin language
 */
Route::get('/app/v1/mobile-ci-sign-in-language/search', 'IntermediateAuthController@Setting_getMobileCiSigninLanguage');
