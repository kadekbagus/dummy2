<?php
/**
 * Intermediate routes for user personal interest API
 */

/**
 * List Widgets
 */
Route::get('/app/v1/personal-interest/list', 'IntermediateAuthController@PersonalInterest_getSearchPersonalInterest');
