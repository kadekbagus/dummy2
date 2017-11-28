<?php
/**
 * Routes file for Sponsor Bank, E-wallet, Credit card related API
 */

/**
 * Create sponsor bank, e-wallet, credit card
 */
Route::post('/api/v1/sponsor-provider/new', function()
{
    return SponsorProviderAPIController::create()->postNewSponsorProvider();
});

/**
 * Update sponsor bank, e-wallet, credit card
 */
Route::post('/api/v1/sponsor-provider/update', function()
{
    return SponsorProviderAPIController::create()->postUpdateSponsorProvider();
});

/**
 * Get search sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/sponsor-provider/list', function()
{
    return SponsorProviderAPIController::create()->getSearchSponsorProvider();
});

/**
 * Get link to sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/link-to-sponsor/list', function()
{
    return LinkToSponsorAPIController::create()->getLinkToSponsor();
});

/**
 * sponsor list
 */
Route::get('/api/v1/pub/available-sponsor/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\AvailableSponsorListAPIController::create()->getSponsorList();
});

Route::get('/app/v1/pub/available-sponsor/list', ['as' => 'pub-user-sponsor-list', 'uses' => 'IntermediatePubAuthController@Sponsor\AvailableSponsorList_getAvailableSponsorList']);


/**
 * Sponsor provider
 */
Route::get('/api/v1/pub/sponsor-provider/list', function()
{
    return Orbit\Controller\API\v1\Pub\SponsorProviderListAPIController::create()->getSponsorProviderList();
});

Route::get('/app/v1/pub/sponsor-provider/list', ['as' => 'pub-sponsor-provider-list', 'uses' => 'IntermediatePubAuthController@SponsorProviderList_getSponsorProviderList']);


/**
 * Sponsor provider credit card
 */
Route::get('/api/v1/pub/sponsor-provider-cc/list', function()
{
    return Orbit\Controller\API\v1\Pub\SponsorProviderCreditCardListAPIController::create()->getSponsorProviderCreditcardList();
});

Route::get('/app/v1/pub/sponsor-provider-cc/list', ['as' => 'pub-sponsor-provider-cc-list', 'uses' => 'IntermediatePubAuthController@SponsorProviderCreditCardList_getSponsorProviderCreditcardList']);

/**
 * user credit card list
 */
Route::get('/api/v1/pub/user-credit-card/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\UserCreditCardListAPIController::create()->getUserCreditCard();
});

Route::get('/app/v1/pub/user-credit-card/list', ['as' => 'pub-user-credit-card-list', 'uses' => 'IntermediatePubAuthController@Sponsor\UserCreditCardList_getUserCreditCard']);

/**
 * user e-wallet list
 */
Route::get('/api/v1/pub/user-ewallet/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\UserEWalletListAPIController::create()->getUserEWallet();
});

Route::get('/app/v1/pub/user-ewallet/list', ['as' => 'pub-user-ewallet-list', 'uses' => 'IntermediatePubAuthController@Sponsor\UserEWalletList_getUserEWallet']);