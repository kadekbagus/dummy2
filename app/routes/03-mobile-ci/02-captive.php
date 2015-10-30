<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Routes related with Captive Portal
|
*/
Route::get('/captive-session', ['before' => 'orbit-settings', 'as' => 'captive-portal', function()
{
    return IntermediateLoginController::create()->getCaptive();
}]);
