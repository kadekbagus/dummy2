<?php



// Route for game voucher promotion listing
Route::get('/app/v1/game-voucher-promotion/{search}', ['as' => 'game-voucher-promotion-list', 'uses' => 'IntermediateProductAuthController@GameVoucherPromotion\PromotionList_getList'])->where('search', '(list|search)');

// Route for game voucher promotion detail
Route::get('/app/v1/game-voucher-promotion/detail', ['as' => 'game-voucher-promotion-detail', 'uses' => 'IntermediateProductAuthController@GameVoucherPromotion\PromotionDetail_getDetail']);

// Route for game-voucher-promotion new
Route::post('/app/v1/game-voucher-promotion/new', ['as' => 'game-voucher-promotion-new', 'uses' => 'IntermediateProductAuthController@GameVoucherPromotion\PromotionNew_postNew']);

// Route for game-voucher-promotion update
Route::post('/app/v1/game-voucher-promotion/update', ['as' => 'game-voucher-promotion-update', 'uses' => 'IntermediateProductAuthController@GameVoucherPromotion\PromotionUpdate_postUpdate']);

// Route for game-voucher-promotion update status
Route::post('/app/v1/game-voucher-promotion/update-status', ['as' => 'game-voucher-promotion-update-status', 'uses' => 'IntermediateProductAuthController@GameVoucherPromotion\PromotionUpdateStatus_postUpdateStatus']);
