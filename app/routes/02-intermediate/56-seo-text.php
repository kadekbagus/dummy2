<?php
/**
 * Routes file for Seo Text related API
 */

/**
 * Create new seo text
 */
Route::post('/app/v1/seo-text/new', 'IntermediateAuthController@SeoText_postNewSeoText');

/**
 * Get search seo text
 */
Route::get('/app/v1/seo-text/list', 'IntermediateAuthController@SeoText_getSearchSeoText');