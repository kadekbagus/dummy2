<?php

/**
 * Media uploads
 */
Route::post('/api/v1/media/upload', function()
{
    return MediaAPIController::create()->upload();
});

/**
 * Media delete
 */
Route::post('/api/v1/media/delete', function()
{
    return MediaAPIController::create()->delete();
});