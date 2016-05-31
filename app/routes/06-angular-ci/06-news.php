<?php
Route::get('/api/v1/cust/news', function()
{
    return Orbit\Controller\API\v1\Customer\PromotionCIAPIController::create()->getPromotionList();
});

Route::get('/api/v1/cust/news/detail', function()
{
    return Orbit\Controller\API\v1\Customer\PromotionCIAPIController::create()->getPromotionDetail();
});

Route::get('/app/v1/cust/news', ['as' => 'customer-api-news-list', 'uses' => 'IntermediateCIAuthController@PromotionCI_getPromotionList']);

Route::get('/app/v1/cust/news/detail', ['as' => 'customer-api-news-detail', 'uses' => 'IntermediateCIAuthController@PromotionCI_getPromotionDetail']);