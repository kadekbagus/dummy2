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
