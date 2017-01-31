<?php
/**
 * List and/or search social media link
 */

Route::get('/api/v1/pub/social-media-link/list', function()
{
    return Orbit\Controller\API\v1\Pub\SocialMediaLinkAPIController::create()->getSocialMediaLink();
});