<?php

// Route for Digital Product listing
// Route::get('/api/v1/digital-product/{search}', function()
// {
//     return Orbit\Controller\API\v1\Product\DigitalProduct\DigitalProductListAPIController::create()->getList();
// })->where('search', '(list|search)');

Route::get('/app/v1/digital-product/{search}', ['as' => 'digital-product-list', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductList_getList'])->where('search', '(list|search)');

// route for Digital Product new/create
// Route::post('/api/v1/digital-product/new', function()
// {
//     return Orbit\Controller\API\v1\Product\DigitalProduct\DigitalProductNewAPIController::create()->postNewGame();
// });
// Route::post('/app/v1/digital-product/new', ['as' => 'digital-product-new', 'uses' => 'IntermediateProductAuthController@Game\GameNew_postNewGame']);

// route for Digital Product detail
// Route::get('/api/v1/digital-product/detail', function()
// {
//     return Orbit\Controller\API\v1\Product\DigitalProduct\DigitalProductDetailAPIController::create()->getDetailGame();
// });
Route::get('/app/v1/digital-product/detail', ['as' => 'digital-product-detail', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductDetail_getDetailGame']);

// route for Digital Product update
// Route::post('/api/v1/digital-product/update', function()
// {
//     return Orbit\Controller\API\v1\Product\DigitalProduct\DigitalProductUpdateAPIController::create()->postUpdateGame();
// });
// Route::post('/app/v1/digital-product/update', ['as' => 'digital-product-update', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductUpdate_postUpdateGame']);
