<?php
/**
 * Routes file for Pokestop related API
 */

/**
 * Get mall list based which have pokestop
 */
Route::get(
    '/{search}/v1/pub/mall-pokestop-list', ['as' => 'mall-pokestop-list', function()
    {
        return Orbit\Controller\API\v1\Pub\PokestopAPIController::create()->getMallPokestopList();
    }]
)->where('search', '(api|app)');