<?php
/**
 * Routes file for Intermediate Membership Number API
 */

/**
* List and/or Search Membership Number
*/
Route::get('/app/v1/membership-number/{search}', 'IntermediateAuthController@MembershipNumber_getSearchMembershipNumber')
     ->where('search', '(list|search)');
