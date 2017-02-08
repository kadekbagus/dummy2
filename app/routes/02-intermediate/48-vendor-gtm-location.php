<?php
/**
 * Routes file for managing Vendor Gtm City and Country.
 */

/**
 * Get search county based on Vendor Gtm Country
 */
Route::get('/app/v1/vendor-gtm-country/list', 'IntermediateAuthController@VendorGtmLocation_getSearchVendorGtmCountry');

/**
 * Get search city base on Vendor Gtm City
 */
Route::get('/app/v1/vendor-gtm-city/list', 'IntermediateAuthController@VendorGtmLocation_getSearchVendorGtmCity');