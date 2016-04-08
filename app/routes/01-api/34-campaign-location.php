<?php

/**
 * List and/or Search Campaign Locations
 */
Route::get('/api/v1/campaign-location/{search}', function()
{
    return CampaignLocationAPIController::create()->getCampaignLocations();
})->where('search', '(list|search)');