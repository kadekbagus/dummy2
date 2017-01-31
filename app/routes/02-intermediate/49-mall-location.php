<?php
/**
 * List and/or Search mall country
 */
Route::get('/app/v1/mall-country/{search}', 'IntermediateAuthController@MallLocation_getSearchMallCountry')->where('search', '(list|search)');

/**
 * List and/or Search mall city
 */
Route::get('/app/v1/mall-city/{search}', 'IntermediateAuthController@MallLocation_getSearchMallCity')->where('search', '(list|search)');