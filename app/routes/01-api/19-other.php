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
		// /**
		//  * Poll new alert
		//  */
		// Route::get('/api/v1/inbox/unread-count', function()
		// {
		//     return InboxAPIController::create()->getPollAlert();
		// });

		// /**
		//  * Search inbox
		//  */
		// Route::get('/api/v1/inbox/list', function()
		// {
		//     return InboxAPIController::create()->getSearchInbox();
		// });
	}
);

// /**
//  * Read / flag the alert as read
//  */
// Route::post('/api/v1/inbox/read', function()
// {
//     return InboxAPIController::create()->postReadAlert();
// });

// /**
//  * Delete the alert
//  */
// Route::post('/api/v1/inbox/delete', function()
// {
//     return InboxAPIController::create()->postDeleteAlert();
// });


/**
 * Get server time
 */
Route::get('/api/v1/server-time', function()
{
    return DummyAPIController::create()->getServerTime();
});

/**
 * Get sitemap
 */
Route::get('/api/v1/pub/sitemap', function()
{
    return Orbit\Controller\API\v1\Pub\SitemapReaderAPIController::create()->getSitemap();
});

/**
 * Get sitemap
 */
Route::get('/api/v1/pub/sitemap-article', function()
{
    return Orbit\Controller\API\v1\Pub\SitemapReaderAPIController::create()->getSitemapArticle();
});

/**
 * Get sitemap
 */
Route::get('/api/v1/store-list-adactive', function()
{
    return Orbit\Controller\API\v1\Pub\Store\StoreListAdactiveAPIController::create()->getStoreListAdactive();
});
