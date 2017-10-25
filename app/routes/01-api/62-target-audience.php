<?php
/**
 * Routes file for Target Audience related API
 */

/**
 * Create target audience
 */
Route::post('/api/v1/target-audience/new', function()
{
    return TargetAudienceAPIController::create()->postNewTargetAudience();
});

/**
 * Update target audience
 */
Route::post('/api/v1/target-audience/update', function()
{
    return TargetAudienceAPIController::create()->postUpdateTargetAudience();
});

/**
 * Get search target audience
 */
Route::get('/api/v1/target-audience/list', function()
{
    return TargetAudienceAPIController::create()->getSearchTargetAudience();
});
