<?php

/**
 * Game list api
 */
Route::get('/app/v1/pub/game/list', ['as' => 'game-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\GameList_getList']);

/**
 * Game list api
 */
Route::get('/app/v1/pub/game/detail', ['as' => 'game-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\GameDetail_getDetail']);

/**
 * Digital product detail api.
 */
Route::get('/app/v1/pub/digital-product/detail', ['as' => 'pub-digital-product-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\DigitalProductDetail_getDetail']);
