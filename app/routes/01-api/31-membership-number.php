<?php
/**
 * Routes file for Membership Number related API
 */


/**
 * List/Search Membership Number
 */
Route::get('/api/v1/membership-number/{search}', function()
{
    return MembershipNumberAPIController::create()->getSearchMembershipNumber();
})->where('search', '(list|search)');