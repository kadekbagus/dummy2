<?php
/**
 * Routes file for Intermediate PromotionalEvent API
 */

/**
 * Create new promotional-event
 */
Route::post('/app/v1/promotional-event/new', 'IntermediateAuthController@PromotionalEvent_postNewPromotionalEvent');

/**
 * Delete promotional-event
 */
Route::post('/app/v1/promotional-event/delete', 'IntermediateAuthController@PromotionalEvent_postDeletePromotionalEvent');

/**
 * Update promotional-event
 */
Route::post('/app/v1/promotional-event/update', 'IntermediateAuthController@PromotionalEvent_postUpdatePromotionalEvent');

/**
 * List and/or Search promotional-event
 */
Route::get('/app/v1/promotional-event/search', 'IntermediateAuthController@PromotionalEvent_getSearchPromotionalEvent');

/**
 * Detail promotional-event
 */
Route::get('/app/v1/promotional-event/detail', 'IntermediateAuthController@PromotionalEvent_getDetailPromotionalEvent');

/**
 * List and/or Search promotional-event by retailer
 */
Route::get('/app/v1/promotional-event/by-retailer/search', 'IntermediateAuthController@PromotionalEvent_getSearchPromotionalEventByRetailer');

/**
 * Upload promotional-event Image
 */
Route::post('/app/v1/promotional-event/upload/image', 'IntermediateAuthController@Upload_postUploadPromotionalEventImage');

/**
 * Delete promotional-event Image
 */
Route::post('/app/v1/promotional-event/delete/image', 'IntermediateAuthController@Upload_postDeletePromotionalEventImage');