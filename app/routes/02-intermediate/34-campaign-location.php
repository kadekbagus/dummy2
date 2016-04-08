<?php

/**
 * List and/or Search age ranges
 */
Route::get('/app/v1/campaign-location/{search}', 'IntermediateAuthController@CampaignLocation_getCampaignLocations')
     ->where('search', '(list|search)');