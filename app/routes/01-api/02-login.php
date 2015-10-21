<?php
/**
 * Routes file for login related API
 */

/**
 * Login user
 */
Route::post('/api/v1/login', function()
{
    return DummyAPIController::create()->unsupported();
});

Route::post('/api/v1/logout', function()
{
    return DummyAPIController::create()->unsupported();
});

Route::group(['before' => 'orbit-settings'], function() {
    Route::post('/api/v1/login/admin', function()
    {
        return LoginAPIController::create()->postLoginAdmin();
    });

    Route::post('/api/v1/logout/admin', function()
    {
        return LoginAPIController::create()->postLogout();
    });

    Route::post('/api/v1/login/mall', function()
    {
        return LoginAPIController::create()->postLoginMall();
    });

    Route::post('/api/v1/logout/mall', function()
    {
        return LoginAPIController::create()->postLogout();
    });

    Route::post('/api/v1/login/mallcs', function()
    {
        return LoginAPIController::create()->postLoginMallCustomerService();
    });

    Route::post('/api/v1/logout/mallcs', function()
    {
        return LoginAPIController::create()->postLogout();
    });

    Route::post('/api/v1/login/customer', function()
    {
        return LoginAPIController::create()->postLoginCustomer();
    });

    Route::post('/api/v1/logout/customer', function()
    {
        return LoginAPIController::create()->postLogout();
    });
});

/**
 * Customer registration
 */
Route::post('/api/v1/user/register/mobile', function()
{
    return LoginAPIController::create()->postRegisterUserInShop();
});

/**
 * URL to setup new password
 */
Route::post('/api/v1/user/setup-new-password', function()
{
    return LoginAPIController::create()->postSetupPasswordByToken();
});

/**
 * URL to update service agreement
 */
Route::get('/api/v1/service-agreement/update', function()
{
    return LoginAPIController::create()->postUpdateServiceAgreement();
});

/**
 * Token List
 */
Route::get('/api/v1/token/list', function()
{
    return TokenAPIController::create()->getSearchToken();
});

/**
 * Token List
 */
Route::post('/api/v1/activate-account', function()
{
    return LoginAPIController::create()->postActivateAccount();
});
