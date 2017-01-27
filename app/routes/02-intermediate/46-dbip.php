<?php
/**
 * Routes file for DBIP related API
 */

/**
 * Get search county based on dbip country
 */
Route::get('/app/v1/dbip-country/list', 'IntermediateAuthController@DBIP_getSearchDBIPCountry');

/**
 * Get search city base on dbip city
 */
Route::get('/app/v1/dbip-city/list', 'IntermediateAuthController@DBIP_getSearchDBIPCity');