<?php
/**
 * Routes file for Popular related API
 */

/**
 * List of popular campaign
 */
Route::get('/app/v1/pub/popular-list', ['as' => 'pub-popular-list', 'uses' => 'IntermediatePubAuthController@PopularList_getSearchPopular']);