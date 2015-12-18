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
Route::get('/app/v1/inbox/unread-count', 'IntermediateAuthController@Inbox_getPollAlert');

/**
 * Change the flag of alert to read
 */
Route::post('/app/v1/alert/read', 'IntermediateAuthController@Inbox_postReadAlert');

/**
 * Poll new alert
 */
Route::get('/app/v1/inbox/list', 'IntermediateAuthController@Inbox_getSearchInbox');

/**
 * Change the status of alert to deleted
 */
Route::post('/app/v1/alert/delete', 'IntermediateAuthController@Inbox_postDeleteAlert');

/**
 * Get the server time
 */
Route::get('/app/v1/server-time', [
    'as'    => 'server-time',
    'uses'  => 'DummyAPIController@getServerTime'
]);


/**
 * Get the session info
 */
Route::get('/app/v1/session-info', [
    'as'    => 'session-info',
    'uses'  => 'IntermediateLoginController@getSessionLoginInfo'
]);