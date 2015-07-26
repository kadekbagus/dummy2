<?php
/**
 * Mimic the external system output for POST /orbit-notify/v1/check-member
 */
Route::post('/orbit-notify/v1/check-member', function()
{
    return InternalIntegrationAPIController::create()->NotifyCheckMemberHandler();
});