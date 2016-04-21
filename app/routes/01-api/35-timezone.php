<?php

/**
 * List and/or Search Timezone
 */
Route::get('/api/v1/timezone/{search}', function()
{
    return TimezoneAPIController::create()->getTimezone();
})->where('search', '(list|search)');