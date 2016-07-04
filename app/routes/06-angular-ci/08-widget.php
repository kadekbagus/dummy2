<?php

Route::get('/api/v1/cust/widgets', function()
{
    return Orbit\Controller\API\v1\Customer\WidgetCIAPIController::create()->getWidgetList();
});

Route::get('/app/v1/cust/widgets', ['as' => 'customer-api-widget-list', 'uses' => 'IntermediateCIAuthController@WidgetCI_getWidgetList']);


Route::post('/api/v1/cust/widgetclick', function()
{
    return Orbit\Controller\API\v1\Customer\WidgetCIAPIController::create()->postClickWidgetActivity();
});

Route::post('/app/v1/cust/widgetclick', ['as' => 'customer-api-widget-click', 'uses' => 'IntermediateCIAuthController@WidgetCI_postClickWidgetActivity']);
