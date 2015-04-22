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

/**
 * Poll new alert
 */
Route::get('/app/v1/alert/poll', 'IntermediateAuthController@Inbox_getPollAlert');

/**
 * Change the flag of alert to read
 */
Route::post('/app/v1/alert/read', 'IntermediateAuthController@Inbox_postReadAlert');

/**
 * Get the server time
 */
Route::get('/app/v1/server-time', [
    'as'    => 'server-time',
    'uses'  => 'DummyAPIController@getServerTime'
]);

