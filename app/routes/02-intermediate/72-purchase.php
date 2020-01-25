<?php

Route::post('/app/v1/pub/purchase/new', [
    'as' => 'pub-digital-product-purchase-new',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseNew_postNewPurchase'
]);

Route::post('/app/v1/pub/purchase/update', [
    'as' => 'pub-digital-product-purchase-update',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseUpdate_postUpdatePurchase'
]);
