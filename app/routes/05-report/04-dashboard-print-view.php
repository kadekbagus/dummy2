<?php
Route::get('/printer/dashboard/top-widget-click', [
    'as'      => 'printer-dashboard-detail-top-widget-click',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getTopWidgetClickPrintView'
]);

Route::get('/printer/dashboard/user-connect-time', [
    'as'      => 'printer-dashboard-detail-user-connect-time',
    'before'  => 'orbit-settings',
    'uses'    => 'Report\DashboardPrinterController@getUserConnectTimePrintView'
]);


