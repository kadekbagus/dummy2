<?php

/**
 * share landing page via email
 */
Route::post('/api/v1/pub/advertise-with-us', function()
{
    return Orbit\Controller\API\v1\Pub\AdvertiseWithUsEmailAPIController::create()->postAdvertiseWithUsEmail();
});