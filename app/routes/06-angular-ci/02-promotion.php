<?php
Route::get('/api/v1/cust/promotions', function()
{
    return Orbit\Controller\API\v1\Customer\PromotionCIAPIController::create()->getPromotionList();
});

Route::get('/api/v1/cust/promotions/detail', function()
{
    return Orbit\Controller\API\v1\Customer\PromotionCIAPIController::create()->getPromotionDetail();
});

Route::get('/app/v1/cust/promotions', ['as' => 'customer-api-promotion-list', 'uses' => 'IntermediateCIAuthController@PromotionCI_getPromotionList']);

Route::get('/app/v1/cust/promotions/detail', ['as' => 'customer-api-promotion-detail', 'uses' => 'IntermediateCIAuthController@PromotionCI_getPromotionDetail']);
