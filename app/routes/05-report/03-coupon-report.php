<?php
/**
 * Report Coupon By Name
 */
Route::group(['before' => 'orbit-settings'], function()
{
    Route::get('/printer/coupon-report/list', 'Report\CouponReportPrinterController@getPrintCouponReport');
});