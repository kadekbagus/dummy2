<?php

Route::post('/app/v1/pub/cart/add', [
    'as' => 'add-item-to-cart',
    'uses' => 'IntermediatePubAuthController@Cart\AddItemToCart_handle',
]);

Route::post('/app/v1/pub/cart/update', [
    'as' => 'update-cart-item',
    'uses' => 'IntermediatePubAuthController@Cart\UpdateCartItem_handle',
]);

Route::post('/app/v1/pub/cart/remove', [
    'as' => 'remove-cart-item',
    'uses' => 'IntermediatePubAuthController@Cart\RemoveCartItem_handle',
]);

Route::get('/app/v1/pub/cart/items', [
    'as' => 'cart-items',
    'uses' => 'IntermediatePubAuthController@Cart\CartItemList_handle',
]);

Route::post('/app/v1/pub/order/new', [
    'as' => 'new-product-order',
    'uses' => 'IntermediatePubAuthController@Order\NewOrder_handle',
]);
