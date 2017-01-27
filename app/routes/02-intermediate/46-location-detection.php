<?php
/**
 * Routes file for Location Detection
 */

/**
 * Get country and city for GPS enabled/disabled API
 */
Route::get('/app/v1/pub/detect-location', ['as' => 'pub-detect-location', 'uses' => 'IntermediatePubAuthController@LocationDetection_getCountryAndCity']);
