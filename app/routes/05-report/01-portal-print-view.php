<?php
/**
 * Routing file for viewing data in printer friendly format.
 */
Route::group(['before' => 'orbit-settings'], function()
{
    Route::get('/printer/tenant/{search}', [
        'as'    => 'printer-tenant-list',
        'uses'  => 'Report\DataPrinterController@getTenantListPrintView'
    ])->where('search', '(search|list)');
});
