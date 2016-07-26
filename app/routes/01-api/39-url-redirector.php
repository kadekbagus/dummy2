<?php

/**
 * Route to get redirect url
 */
Route::get(
    '/{prefix}/v1/pub/url-redirector', ['as' => 'pub-url-redirector', function()
    {
        return Orbit\Controller\API\v1\Pub\UrlRedirectorAPIController::create()->getRedirectUrl();
    }]
)->where('prefix', '(api|app)');

