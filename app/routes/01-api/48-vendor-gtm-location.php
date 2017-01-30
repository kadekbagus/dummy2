<?php
/**
 * Routes file for managing Vendor Gtm City and Country.
 */

/**
 * Get search county based on Vendor Gtm Country
 */
Route::get('/api/v1/dbip-country/list', function()
{
    return VendorGtmLocationAPIController::create()->getSearchVendorGtmCountry();
});

/**
 * Get search city base on Vendor Gtm City
 */
Route::get('/api/v1/dbip-city/list', function()
{
    return VendorGtmLocationAPIController::create()->getSearchVendorGtmCity();
});