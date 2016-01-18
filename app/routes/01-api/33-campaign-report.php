<?php
/**
 * Routes File for Campaign Report Related API
 */


/**
 * Campaign Report Listing
 */
Route::get('/api/v1/campaign-report/list', function()
{
    return CampaignReportAPIController::create()->getCampaignReport();
});

/**
 * Get Campaign Demographic
 */
Route::get('/api/v1/campaign-report/campaign-demographic', function()
{
    return CampaignReportAPIController::create()->getCampaignDemographic();
});

Route::get('api/v1/campaign-report/spending', function() {
   return CampaignReportAPIController::create()->getSpending(); 
});

/**
 * Get Campaign Overview
 */
Route::get('/api/v1/campaign-report/campaign-overview', function()
{
    return CampaignReportAPIController::create()->getCampaignOverview();
});
