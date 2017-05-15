<?php
/**
 * Routes file for Seo Text related API
 */

/**
 * Create new seo text
 */
Route::post('/app/v1/seo-text/new', 'IntermediateAuthController@SeoText_postNewSeoText');