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