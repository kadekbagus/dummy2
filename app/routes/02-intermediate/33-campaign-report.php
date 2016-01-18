<?php
/**
 * Routes file for Intermediate Campaign Report API
 */


/**
 * Campaign Report Listing
 */
Route::get('/app/v1/campaign-report/list', 'IntermediateAuthController@CampaignReport_getCampaignReport');

/**
 * Campaign Report Dashboard Demographic
 */
Route::get('/app/v1/campaign-report/campaign-demographic', 'IntermediateAuthController@CampaignReport_getCampaignDemographic');

Route::get('/app/v1/campaign-report/spending', 'IntermediateAuthController@CampaignReport_getSpending');
