<?php
/**
 * Routes file for Object related API
 */

/**
 * Create new object
 */
Route::post('/api/v1/object/new', function()
{
    return ObjectAPIController::create()->postNewObject();
});

/**
 * Delete object
 */
Route::post('/api/v1/object/delete', function()
{
    return ObjectAPIController::create()->postDeleteObject();
});

/**
 * Update object
 */
Route::post('/api/v1/object/update', function()
{
    return ObjectAPIController::create()->postUpdateObject();
});

/**
 * List/Search object
 */
Route::get('/api/v1/object/{search}', function()
{
    return ObjectAPIController::create()->getSearchObject();
})->where('search', '(list|search)');
