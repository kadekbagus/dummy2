<?php
/**
 * Routes file for Intermediate Campaign Report API
 */


/**
 * Campaign Report Summary Listing
 */
Route::get('/app/v1/campaign-report-summary/list', 'IntermediateAuthController@CampaignReport_getCampaignReportSummary');

/**
 * Campaign Report Detail Listing
 */
Route::get('/app/v1/campaign-report-detail/list', 'IntermediateAuthController@CampaignReport_getCampaignReportDetail');

/**
 * Get Tenant Campaign Summary
 */
Route::get('/app/v1/tenant-campaign-summary/list', 'IntermediateAuthController@CampaignReport_getTenantCampaignSummary');

/**
 * Get Tenant Campaign Summary
 */
Route::get('/app/v1/tenant-campaign-detail/list', 'IntermediateAuthController@CampaignReport_getTenantCampaignDetail');


/**
 * Campaign Report Dashboard Demographic
 */
Route::get('/app/v1/campaign-report/campaign-demographic', 'IntermediateAuthController@CampaignReport_getCampaignDemographic');

Route::get('/app/v1/campaign-report/spending', 'IntermediateAuthController@CampaignReport_getSpending');

/**
 * Campaign Overview Dashboard
 */
Route::get('/app/v1/campaign-report/campaign-overview', 'IntermediateAuthController@CampaignReport_getCampaignOverview');
