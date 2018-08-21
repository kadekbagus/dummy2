<?php
/**
 * Report Coupon By Name
 */
Route::get('/printer/mdm-store/list', 'Report\MDMStorePrinterController@getPrintMDMStoreReport');
Route::get('/printer/mdm-merchant-location/list', 'Report\MDMMerchantLocationPrinterController@getPrintMDMMerchantLocationReport');