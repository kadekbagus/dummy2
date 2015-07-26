<?php
/**
 * Mimic the external system output for POST /orbit-notify/v1/check-member
 */
Route::post('/orbit-notify/v1/check-member', function()
{
    return InternalIntegrationAPIController::create()->NotifyCheckMemberHandler();
});

Route::post('/orbit-notify/v1/update-member', function()
{
    return InternalIntegrationAPIController::create()->NotifyUpdateMemberHandler();
});

Route::post('/orbit-notify/v1/lucky-draw-number', function()
{
    return InternalIntegrationAPIController::create()->NotifyLuckyDrawNumberHandler();
});