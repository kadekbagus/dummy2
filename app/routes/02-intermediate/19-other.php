<?php
/**
 * Get country list
 */
Route::get('/app/v1/country/list', 'IntermediateLoginController@Country_getSearchCountry');

/**
 * Shutdown box
 */
Route::post('/app/v1/box-control/shutdown', 'IntermediateAuthController@Shutdown_postShutdownBox');

/**
 * Reboot box
 */
Route::post('/app/v1/box-control/reboot', 'IntermediateAuthController@Shutdown_postRebootBox');
