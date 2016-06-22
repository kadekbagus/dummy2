<?php

Route::get('/api/v1/cust/membership', function()
{
    return Orbit\Controller\API\v1\Customer\MembershipCIAPIController::create()->getMembershipCI();
});

Route::get('/app/v1/cust/membership', ['as' => 'customer-api-membership', 'uses' => 'IntermediateCIAuthController@MembershipCI_getMembershipCI']);