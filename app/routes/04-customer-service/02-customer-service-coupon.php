<?php
/**
 * Routes file Customer Service lucky draw related activity.
 */

/**
 * Issue lucky draw to customer
 */
Route::post('/api/v1/cs/coupon/issue', function()
{
    return LuckyDrawCSAPIController::create()->postIssueCouponManual();
});

/**
 * Intermediate.
 */
Route::post('/app/v1/cs/coupon/issue', 'IntermediateAuthController@LuckyDrawCS_postIssueCouponManual');
