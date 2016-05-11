<?php

/**
 * List and/or Search Campaign Locations
 */
Route::get('/app/v1/campaign-location/{search}', 'IntermediateAuthController@CampaignLocation_getCampaignLocations')
     ->where('search', '(list|search)');