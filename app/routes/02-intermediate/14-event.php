<?php
/**
 * Routes file for Intermediate Event API
 */

/**
 * Create new event
 */
Route::post('/app/v1/event/new', 'IntermediateAuthController@Event_postNewEvent');

/**
 * Delete event
 */
Route::post('/app/v1/event/delete', 'IntermediateAuthController@Event_postDeleteEvent');

/**
 * Update event
 */
Route::post('/app/v1/event/update', 'IntermediateAuthController@Event_postUpdateEvent');

/**
 * List and/or Search event
 */
Route::get('/app/v1/event/search', 'IntermediateAuthController@Event_getSearchEvent');

/**
 * List and/or Search event by retailer
 */
Route::get('/app/v1/event/by-retailer/search', 'IntermediateAuthController@Event_getSearchEventByRetailer');

/**
 * Upload event Image
 */
Route::post('/app/v1/event/upload/image', 'IntermediateAuthController@Upload_postUploadEventImage');

/**
 * Delete event Image
 */
Route::post('/app/v1/event/delete/image', 'IntermediateAuthController@Upload_postDeleteEventImage');