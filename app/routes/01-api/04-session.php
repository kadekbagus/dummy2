<?php
/**
 * Routes file for session related API
 */

Route::get('/api/v1/session/check', function()
{
    return SessionAPIController::create()->getCheck();
});