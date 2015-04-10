<?php
/**
 * Routes file for Event related API
 */

/**
 * Create new event
 */
Route::post('/api/v1/event/new', function()
{
    return EventAPIController::create()->postNewEvent();
});

/**
 * Delete event
 */
Route::post('/api/v1/event/delete', function()
{
    return EventAPIController::create()->postDeleteEvent();
});

/**
 * Update event
 */
Route::post('/api/v1/event/update', function()
{
    return EventAPIController::create()->postUpdateEvent();
});

/**
 * List/Search event
 */
Route::get('/api/v1/event/search', function()
{
    return EventAPIController::create()->getSearchEvent();
});

/**
 * List/Search event by retailer
 */
Route::get('/api/v1/event/by-retailer/search', function()
{
    return EventAPIController::create()->getSearchEventByRetailer();
});

/**
 * Upload event image
 */
Route::post('/api/v1/event/upload/image', function()
{
    return UploadAPIController::create()->postUploadEventImage();
});

/**
 * Delete event image
 */
Route::post('/api/v1/event/delete/image', function()
{
    return UploadAPIController::create()->postDeleteEventImage();
});