<?php
/**
 * List/Search mall country
 */
Route::get('/api/v1/mall-country/{search}', function()
{
    return MallLocationAPIController::create()->getSearchMallCountry();
})->where('search', '(list|search)');