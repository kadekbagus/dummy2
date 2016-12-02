<?php
/**
 * Routes file for Partner related API
 */

/**
 * Create new partner
 */
Route::post('/api/v1/partner/new', function()
{
    return PartnerAPIController::create()->postNewPartner();
});