<?php
/**
 * Routes file Customer Service lucky draw related activity.
 */

/**
 * Issue lucky draw to customer
 */
Route::post('/api/v1/cs/lucky-draw-number/issue', function()
{
    return LuckyDrawCSAPIController::create()->postIssueLuckyDrawNumberExternal();
});

/**
 * Intermediate.
 */
Route::post('/app/v1/cs/lucky-draw-number/issue', 'IntermediateAuthController@LuckyDrawCS_postIssueLuckyDrawNumberExternal');