<?php


Route::get('/printer/campaign-summary-report/list', [
    'as'        => 'printer-campaign-summary-report-list',
    'uses'      => 'Report\CampaignSummaryReportPrinterController@getCampaignSummaryReportPrintView'
]);