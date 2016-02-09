<?php
/**
 * Routes file for Intermediate Consumer API
 */


/**
 * List and/or Search consumer
 *
 * @deprecated use 01-user.php instead
 */

Route::get('/app/v1/consumer/search', 'IntermediateAuthController@User_getConsumerListing');
Route::get('/app/v1/consumer/list', 'IntermediateAuthController@User_getConsumerListing');
