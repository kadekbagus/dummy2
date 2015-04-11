<?php
/**
 * A dummy route file
 */
Route::get('/api/v1/dummy/hisname', function()
{
    return DummyAPIController::create()->hisname();
});

Route::get('/api/v1/dummy/hisname/auth', function()
{
    return DummyAPIController::create()->hisnameAuth();
});

Route::get('/api/v1/dummy/hisname/authz', function()
{
    return DummyAPIController::create()->hisNameAuthz();
});

Route::post('/api/v1/dummy/myname', function()
{
    return DummyAPIController::create()->myName();
});

Route::post('/api/v1/dummy/myname/auth', function()
{
    return DummyAPIController::create()->myNameAuth();
});

Route::post('/api/v1/dummy/myname/authz', function()
{
    return DummyAPIController::create()->myNameAuthz();
});

Route::post('/api/v1/dummy/user/new', function()
{
    return DummyAPIController::create()->postRegisterUserAuthz();
});

Route::get('/customer/account', function() {
  return View::make('mobile-ci.account', array('page_title' => 'Akun'));
});
