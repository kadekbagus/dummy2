<?php
/**
 * Routes file Customer Service lucky draw related activity.
 */

Route::group(['before' => 'orbit-settings'], function()
{
    /**
     * Issue lucky draw to customer
     */
    Route::post('/api/v1/cs/coupon/issue', function()
    {
        return LuckyDrawCSAPIController::create()->postIssueCouponNumber();
    });

    /**
     * Intermediate.
     */
    Route::post('/app/v1/cs/coupon/issue', 'IntermediateAuthController@LuckyDrawCS_postIssueCouponNumber');
});
