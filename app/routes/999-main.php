<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/
Route::get('/', ['as' => 'ci-home', function()
{
    return 'Gotomalls API!';
}]);

/*
|--------------------------------------------------------------------------
| Orbit Application Version
|--------------------------------------------------------------------------
|
*/
Route::get('/{api}/orbit-version', ['as' => 'orbit-app-version', function() {
    return OrbitVersionAPIController::create()->getVersion();
}])->where('api', '(api|app)');

/*
|--------------------------------------------------------------------------
| CORS REQUEST
|--------------------------------------------------------------------------
|
| Return all headers which needed to make CORS request successful.
|
*/
Route::options('{all}', function()
{
    return DummyAPIController::create()->IamOK();
})->where('all', '.*');

/*
|--------------------------------------------------------------------------
| Catch All Routes for API
|--------------------------------------------------------------------------
|
| Catch all routes for API both GET and POST /app/v1/* or /api/v1/*
|
*/
Route::get('/{api}/v1/{all}', function()
{
    return DummyAPIController::create()->xxx();
})->where('all', '.*')->where('api', '(api|app)');

Route::post('/{api}/v1/{all}', function()
{
    return DummyAPIController::create()->xxx();
})->where('all', '.*')->where('api', '(api|app)');

/*
|--------------------------------------------------------------------------
| Catch All Routes
|--------------------------------------------------------------------------
|
| All unrouted request will go here.
|
*/
Route::get('{all}', function()
{
    return View::make('errors/404');
})->where('all', '.*');
