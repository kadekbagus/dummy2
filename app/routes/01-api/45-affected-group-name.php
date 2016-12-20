<?php
/**
 * Routes file for Affected Group Name related API
 */

/**
 * Get search affected group name
 */
Route::get('/api/v1/affected-group-name/list', function()
{
    return AffectedGroupNameAPIController::create()->getSearchAffectedGroupName();
});
