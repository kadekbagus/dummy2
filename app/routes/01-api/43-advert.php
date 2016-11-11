<?php
/**
 * Routes file for adverts
 */

/**
 * Get pub footer advert
 */
// Route::get('/app/v1/', function()
// {
//     return Orbit\Controller\API\v1\Pub\Advert\AdvertFooterAPIController::create()->getFooterAdvert();
// });

Route::get('/{app}/v1/pub/advert/footer/{search}', [
    'as' => 'campaign-slider',
    'uses' => 'IntermediatePubAuthController@Advert\AdvertFooter_getFooterAdvert'
])->where(['app' => '(api|app)', 'search' => '(search|list)']);