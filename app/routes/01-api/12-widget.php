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

/**
 * Delete Widget Image
 */
Route::post('/api/v1/widget/deleteimage', function()
{
    return WidgetAPIController::create()->postDeleteWidgetImage();
});

/**
 * List Widget Templates
 */
Route::get('/api/v1/widget-template/list', function()
{
    return WidgetTemplateAPIController::create()->getSearchWidgetTemplate();
});

/**
 * List Setting Widget Templates
 */
Route::get('/api/v1/setting-widget-template/list', function()
{
    return WidgetTemplateAPIController::create()->getSearchSettingWidgetTemplate();
});
