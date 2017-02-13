<?php
/**
 * List and/or Search pages
 */

Route::get('/api/v1/pub/page/list', function()
{
    return Orbit\Controller\API\v1\Pub\PageAPIController::create()->getPage();
});