<?php
/**
 * Get country list
 */
Route::get('/api/v1/country/list', function()
{
    return CountryAPIController::create()->getSearchCountry();
});

/**
 * Shutdown box
 */
Route::post('/api/v1/box-control/shutdown', ['as' => 'api-box-control-shutdown', function()
{
    return ShutdownAPIController::create()->postShutdownBox();
}]);

/**
 * Reboot box
 */
Route::post('/api/v1/box-control/reboot', ['as' => 'api-box-control-shutdown', function()
{
    return ShutdownAPIController::create()->postRebootBox();
}]);

Route::group(
    array('before' => 'orbit-settings'),
    function () {
		/**
		 * Poll new alert
		 */
		Route::post('/api/v1/alert/poll', function()
		{
		    return InboxAPIController::create()->getPollAlert();
		});

		/**
		 * Search inbox
		 */
		Route::get('/api/v1/alert/search', function()
		{
		    return InboxAPIController::create()->getSearchInbox();
		});
	}
);

/**
 * Read / flag the alert as read
 */
Route::post('/api/v1/alert/read', function()
{
    return InboxAPIController::create()->postReadAlert();
});

/**
 * Delete the alert
 */
Route::post('/api/v1/alert/delete', function()
{
    return InboxAPIController::create()->postDeleteAlert();
});


/**
 * Get server time
 */
Route::get('/api/v1/server-time', function()
{
    return DummyAPIController::create()->getServerTime();
});
