<?php

/**
 * List and/or Search age ranges
 */
Route::get('/api/v1/age-range/{search}', function()
{
    return AgeRangeAPIController::create()->getSearchAgeRanges();
})->where('search', '(list|search)');