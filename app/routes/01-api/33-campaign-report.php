<?php
/**
 * Routes File for Campaign Report Related API
 */


/**
 * Campaign Report Listing
 */
Route::get('/api/v1/campaign-report-summary/list', function()
{
    return CampaignReportAPIController::create()->getCampaignReportSummary();
});

/**
 * Campaign Report Detail Listing
 */
Route::get('/api/v1/campaign-report-detail/list', function()
{
    return CampaignReportAPIController::create()->getCampaignReportDetail();
});

/**
 * Get Tenant Campaign Summary
 */
Route::get('/api/v1/tenant-campaign-summary/list', function()
{
    return CampaignReportAPIController::create()->getTenantCampaignSummary();
});

/**
 * Get Tenant Campaign Detail
 */
Route::get('/api/v1/tenant-campaign-detail/list', function()
{
    return CampaignReportAPIController::create()->getTenantCampaignDetail();
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
