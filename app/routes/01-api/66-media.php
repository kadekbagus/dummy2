<?php

/**
 * Media uploads
 */
Route::post('/api/v1/media/upload', function()
{
    return MediaAPIController::create()->upload();
});