<?php
/**
 * Routes file for Intermediate Partner API
 */

/**
 * Create new partner
 */
Route::post('/app/v1/partner/new', 'IntermediateAuthController@Partner_postNewPartner');