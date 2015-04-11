<?php
/**
 * Routes file for Widget related API
 */

/**
 * Create New Widget
 */
Route::post('/api/v1/widget/new', function()
{
    return WidgetAPIController::create()->postNewWidget();
});

/**
 * Update Widget
 */
Route::post('/api/v1/widget/update', function()
{
    return WidgetAPIController::create()->postUpdateWidget();
});

/**
 * Delete Widget
 */
Route::post('/api/v1/widget/delete', function()
{
    return WidgetAPIController::create()->postDeleteWidget();
});

/**
 * List Widgets
 */
Route::get('/api/v1/widget/list', function()
{
    return WidgetAPIController::create()->getSearchWidget();
});
