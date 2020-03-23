<?php
/**
 * Routes file for Intermediate News API
 */

/**
 * Create new news
 */
Route::post('/app/v1/news/new', 'IntermediateAuthController@News_postNewNews');

/**
 * Delete news
 */
Route::post('/app/v1/news/delete', 'IntermediateAuthController@News_postDeleteNews');

/**
 * Update news
 */
Route::post('/app/v1/news/update', 'IntermediateAuthController@News_postUpdateNews');

/**
 * List and/or Search news
 */
Route::get('/app/v1/news/{search}', 'IntermediateAuthController@News_getSearchNews')
     ->where('search', '(list|search)');

/**
 * Detail news
 */
Route::get('/app/v1/news/detail', 'IntermediateAuthController@News_getDetailNews');

/**
 * Upload news image
 */
Route::post('/app/v1/news-image/upload', 'IntermediateAuthController@Upload_postUploadNewsImage');

/**
 * Delete news image
 */
Route::post('/app/v1/news-image/delete', 'IntermediateAuthController@Upload_postDeleteNewsImage');

/**
 * List and/or Search promotion by retailer
 */
Route::get('/app/v1/newspromotion/by-retailer/search', 'IntermediateAuthController@News_getSearchNewsPromotionByRetailer');