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
