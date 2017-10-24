<?php
/**
 * Routes file for Target Audience related API
 */

/**
 * Create new target audience
 */
Route::post('/api/v1/target-audience/new', function()
{
    return TargetAudienceAPIController::create()->postNewTargetAudience();
});

/**
 * Get search target audience
 */
Route::get('/api/v1/target-audience/list', function()
{
    return TargetAudienceAPIController::create()->getSearchTargetAudience();
});
