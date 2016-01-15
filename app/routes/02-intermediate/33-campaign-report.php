<?php
/**
 * Routes file for Intermediate Campaign Report API
 */


/**
 * Campaign Report Listing
 */
Route::get('/app/v1/campaign-report/list', 'IntermediateAuthController@CampaignReport_getCampaignReport');
