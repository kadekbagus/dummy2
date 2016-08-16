<?php
/**
 * Routes file for landing page
 */

/**
 * Get Icon list
 */
Route::get(
    '/{search}/v1/pub/icon-list', ['as' => 'icon-list', function()
    {
        return Orbit\Controller\API\v1\Pub\LandingPageAPIController::create()->getIconList();
    }]
)->where('search', '(api|app)');

/**
 * Get Slideshow
 */
Route::get(
    '/{search}/v1/pub/slideshow', ['as' => 'slideshow', function()
    {
        return Orbit\Controller\API\v1\Pub\LandingPageAPIController::create()->getSlideShow();
    }]
)->where('search', '(api|app)');
