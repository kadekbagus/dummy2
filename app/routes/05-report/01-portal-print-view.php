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

    Route::get('/printer/lucky-draw-number/{search}', [
        'as'    => 'printer-lucky-draw-number-list',
        'uses'  => 'Report\DataPrinterController@getLuckyDrawNumberPrintView'
    ])->where('search', '(search|list)');
});
