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
 * Get search detail sponsor bank, e-wallet, credit card
 */
Route::get('/api/v1/sponsor-provider/detail', function()
{
    return SponsorProviderAPIController::create()->getSearchSponsorProviderDetail();
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
    return Orbit\Controller\API\v1\Pub\Sponsor\AvailableSponsorListAPIController::create()->getAvailableSponsorList();
});

Route::get('/app/v1/pub/available-sponsor/list', ['as' => 'pub-user-sponsor-list', 'uses' => 'IntermediatePubAuthController@Sponsor\AvailableSponsorList_getAvailableSponsorList']);


/**
 * Sponsor provider
 */
Route::get('/api/v1/pub/sponsor-provider/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\SponsorProviderListAPIController::create()->getSponsorProviderList();
});

Route::get('/app/v1/pub/sponsor-provider/list', ['as' => 'pub-sponsor-provider-list', 'uses' => 'IntermediatePubAuthController@Sponsor\SponsorProviderList_getSponsorProviderList']);


/**
 * Sponsor provider credit card
 */
Route::get('/api/v1/pub/sponsor-provider-cc/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\SponsorProviderCreditCardListAPIController::create()->getSponsorProviderCreditcardList();
});

Route::get('/app/v1/pub/sponsor-provider-cc/list', ['as' => 'pub-sponsor-provider-cc-list', 'uses' => 'IntermediatePubAuthController@Sponsor\SponsorProviderCreditCardList_getSponsorProviderCreditcardList']);

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

/**
 * credit card list
 */
Route::get('/api/v1/pub/available-sponsor/credit-card', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\AvailableSponsorListAPIController::create()->getAvailableSponsorCreditCard();
});

Route::get('/app/v1/pub/available-sponsor/credit-card', ['as' => 'pub-user-sponsor-credit-card', 'uses' => 'IntermediatePubAuthController@Sponsor\AvailableSponsorList_getAvailableSponsorCreditCard']);

/**
 * update user sponsor
 */
Route::post('/api/v1/pub/user-sponsor/update', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\UserSponsorUpdateAPIController::create()->postUserSponsor();
});

Route::post('/app/v1/pub/user-sponsor/update', ['as' => 'pub-user-sponsor-update', 'uses' => 'IntermediatePubAuthController@Sponsor\UserSponsorUpdate_postUserSponsor']);

/**
 * get total campaign that link to user sponsor
 */
Route::get('/api/v1/pub/user-sponsor/campaign', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\UserSponsorCampaignAPIController::create()->getUserSponsorCampaign();
});

Route::get('/app/v1/pub/user-sponsor/campaign', ['as' => 'pub-user-sponsor-campaign', 'uses' => 'IntermediatePubAuthController@Sponsor\UserSponsorCampaign_getUserSponsorCampaign']);

/**
 * Country of sponsor provider
 */
Route::get('/api/v1/pub/country-sponsor/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\CountrySponsorListAPIController::create()->getCountrySponsorList();
});

Route::get('/app/v1/pub/country-sponsor/list', ['as' => 'pub-country-sponsor-list', 'uses' => 'IntermediatePubAuthController@Sponsor\CountrySponsorList_getCountrySponsorList']);


/**
 * Save allowed user notification by cities of cc/wallet user choosen
 */
Route::post('/api/v1/pub/user-sponsor-allowed-notification-cities', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\UserSponsorAllowedNotificationCitiesNewAPIController::create()->postNewUserSponsorAllowedNotificationCities();
});

Route::post('/app/v1/pub/user-sponsor-allowed-notification-cities', ['as' => 'pub-user-sponsor-allowed-notification-cities', 'uses' => 'IntermediatePubAuthController@Sponsor\UserSponsorAllowedNotificationCitiesNew_postNewUserSponsorAllowedNotificationCities']);


/**
 * List of cc/wallet cities by user choosen
 */
Route::get('/api/v1/pub/user-sponsor-allowed-notification-cities/list', function()
{
    return Orbit\Controller\API\v1\Pub\Sponsor\UserSponsorAllowedNotificationCitiesListAPIController::create()->getUserSponsorAllowedNotificationCities();
});

Route::get('/app/v1/pub/user-sponsor-allowed-notification-cities/list', ['as' => 'pub-user-sponsor-allowed-notification-cities', 'uses' => 'IntermediatePubAuthController@Sponsor\UserSponsorAllowedNotificationCitiesList_getUserSponsorAllowedNotificationCities']);
