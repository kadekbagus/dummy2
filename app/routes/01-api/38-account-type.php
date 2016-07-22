<?php

// Get supported account type
Route::get('/api/v1/account-type/list', function()
{
    return AccountTypeAPIController::create()->getSearchAccountType();
});