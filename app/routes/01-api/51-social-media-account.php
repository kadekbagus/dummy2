<?php
/**
 * List and/or search social media account
 */

Route::get('/api/v1/pub/social-media-account/list', function()
{
    return Orbit\Controller\API\v1\Pub\SocialMediaAccountAPIController::create()->getSocialMediaAccount();
});