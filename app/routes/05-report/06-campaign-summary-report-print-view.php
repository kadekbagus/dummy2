<?php

Route::get('/printer/campaign-summary-report/list', 'Report\CampaignReportPrinterController@getPrintCampaignSummaryReport');
Route::get('/printer/campaign-detail-report/list', 'Report\CampaignReportPrinterController@getPrintCampaignDetailReport');