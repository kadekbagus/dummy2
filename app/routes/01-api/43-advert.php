<?php
/**
 * Routes file for adverts
 */

/**
 * Get pub footer advert
 */
Route::get('/{app}/v1/pub/advert/{search}', [
    'as' => 'pub-advert-list',
    'uses' => 'IntermediatePubAuthController@Advert\AdvertList_getAdvertList'
])->where(['app' => '(api|app)', 'search' => '(search|list)']);