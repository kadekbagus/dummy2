<?php
/**
 * List and/or Search mall country
 */
Route::get('/app/v1/mall-country/{search}', 'IntermediateAuthController@MallLocation_getSearchMallCountry')->where('search', '(list|search)');