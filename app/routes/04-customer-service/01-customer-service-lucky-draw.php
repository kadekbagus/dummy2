<?php
/**
 * Routes file Customer Service lucky draw related activity.
 */

Route::group(['before' => 'orbit-settings'], function()
{
    /**
     * Issue lucky draw to customer
     */
    Route::post('/api/v1/cs/lucky-draw-number/issue', function()
    {
        return TenantAPIController::create()->postNewTenant();
    });

    /**
     * Intermediate.
     */
    Route::post('/app/v1/cs/lucky-draw-number/issue', 'IntermediateAuthController@LuckyDrawCS_postIssueLuckyDrawNumber');
});
