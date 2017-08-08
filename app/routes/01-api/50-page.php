<?php
/**
 * List and/or Search pages
 */

Route::get('/api/v1/pub/page/list', function()
{
    return Orbit\Controller\API\v1\Pub\PageAPIController::create()->getPage();
});

/**
 * Get menu counter
 */
Route::get('/api/v1/pub/menu/counter', function()
{
    return Orbit\Controller\API\v1\Pub\MenuCounterAPIController::create()->getMenuCounter();
});

Route::get('/app/v1/pub/menu/counter', ['as' => 'menu-counter', 'uses' => 'IntermediatePubAuthController@MenuCounter_getMenuCounter']);