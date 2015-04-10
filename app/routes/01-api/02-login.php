<?php
/**
 * Routes file for login related API
 */

/**
 * Login user
 */
Route::post('/api/v1/login', function()
{
    return LoginAPIController::create()->postLogin();
});

Route::post('/api/v1/logout', function()
{
    return LoginAPIController::create()->postLogout();
});

/**
 * Customer registration
 */
Route::post('/api/v1/user/register/mobile', function()
{
    return LoginAPIController::create()->postRegisterUserInShop();
});

/**
 * URL to check the token
 */
Route::get('/api/v1/user/token/check', function()
{
    return LoginAPIController::create()->getRegisterTokenCheck();
});

/**
 * Token List
 */
Route::get('/api/v1/token/list', function()
{
    return TokenAPIController::create()->getSearchToken();
});
