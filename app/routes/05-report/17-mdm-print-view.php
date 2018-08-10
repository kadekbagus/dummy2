<?php
/**
 * Report Coupon By Name
 */
Route::get('/printer/mdm-store/list', 'Report\MdmStorePrinterController@getPrintMdmStoreReport');
Route::get('/printer/mdm-merchant-store-location/list', 'Report\MdmMerchantStoreLocationPrinterController@getPrintMdmMerchantStoreLocationReport');