<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Routes related with Captive Portal
|
*/
Route::group( ['before' => ['orbit-settings']], function ()
{
    Route::get('/captive-session', ['as' => 'captive-portal', function()
    {
        return IntermediateLoginController::create()->getCaptive();
    }]);

    Route::get('/customer/captive/request-internet', ['as' => 'captive-request-internet', function()
    {
        return MobileCI\ExCaptivePortalController::create()->getCaptiveRequestInternet();
    }]);

    Route::get('/customer/captive/internet-granted', ['as' => 'captive-internet-granted', function()
    {
        return MobileCI\ExCaptivePortalController::create()->getCaptiveInternetGranted();
    }]);

    Route::get('/customer/captive/ex-captive-landing', ['as' => 'captive-ex-landing', function()
    {
        return MobileCI\ExCaptivePortalController::create()->getExCaptiveLanding();
    }]);
});