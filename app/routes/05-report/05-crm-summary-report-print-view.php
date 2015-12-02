<?php


Route::get('/printer/crm-summary-report/list', [
    'as'        => 'printer-crm-summary-report-list',
    'uses'      => 'Report\CRMSummaryReportPrinterController@getCRMSummaryReportPrintView'
]);