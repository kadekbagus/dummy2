<?php
/**
 * Routes file for PromotionalEvent related API
 */

/**
 * Create new promotional-event
 */
Route::post('/api/v1/promotional-event/new', function()
{
    return PromotionalEventAPIController::create()->postNewPromotionalEvent();
});

/**
 * Delete promotional-event
 */
Route::post('/api/v1/promotional-event/delete', function()
{
    return PromotionalEventAPIController::create()->postDeletePromotionalEvent();
});

/**
 * Update promotional-event
 */
Route::post('/api/v1/promotional-event/update', function()
{
    return PromotionalEventAPIController::create()->postUpdatePromotionalEvent();
});

/**
 * List/Search promotional-event
 */
Route::get('/api/v1/promotional-event/search', function()
{
    return PromotionalEventAPIController::create()->getSearchPromotionalEvent();
});

/**
 * detail promotional-event
 */
Route::get('/api/v1/promotional-event/detail', function()
{
    return PromotionalEventAPIController::create()->getDetailPromotionalEvent();
});

/**
 * List/Search promotional-event by retailer
 */
Route::get('/api/v1/promotional-event/by-retailer/search', function()
{
    return PromotionalEventAPIController::create()->getSearchPromotionalEventByRetailer();
});

/**
 * Upload promotional-event image
 */
Route::post('/api/v1/promotional-event/upload/image', function()
{
    return UploadAPIController::create()->postUploadPromotionalEventImage();
});

/**
 * Delete promotional-event image
 */
Route::post('/api/v1/promotional-event/delete/image', function()
{
    return UploadAPIController::create()->postDeletePromotionalEventImage();
});

/**
 * List of promotional-event on all malls
 */
Route::get('/api/v1/pub/promotional-event-list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventListAPIController::create()->getSearchPromotionalEvent();
});

Route::get('/app/v1/pub/promotional-event-list', ['as' => 'pub-promotional-event-list', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventList_getSearchPromotionalEvent']);

/**
 * List mall of promotional-event
 */
Route::get('/api/v1/pub/mall-promotional-event-list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventMallAPIController::create()->getMallPerPromotionalEvent();
});

Route::get('/app/v1/pub/mall-promotional-event-list', ['as' => 'pub-mall-promotional-event-list', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventMall_getMallPerPromotionalEvent']);


/**
 * Get mall promotional-event detail on landing page
 */
Route::get('/api/v1/pub/mall-promotional-event/detail', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventDetailAPIController::create()->getPromotionalEventItem();
});

Route::get('/app/v1/pub/mall-promotional-event/detail', ['as' => 'pub-mall-promotional-event-detail', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventDetail_getPromotionalEventItem']);

/**
 * List location of a news
 */
Route::get('/api/v1/pub/mall-promotional-event-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventLocationAPIController::create()->getPromotionalEventLocations();
});

Route::get('/app/v1/pub/mall-promotional-event-location/list', ['as' => 'pub-mall-promotional-event-location-list', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventLocation_getPromotionalEventLocations']);

/**
 * List store location in promotional-event detil
 */
Route::get('/api/v1/pub/store-promotional-event/list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventStoreAPIController::create()->getPromotionalEventStore();
});

Route::get('/app/v1/pub/store-promotional-event/list', ['as' => 'pub-store-promotional-event-list', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventStore_getPromotionalEventStore']);


/**
 * List city for promotional-event
 */
Route::get('/api/v1/pub/promotional-event-city/list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventCityAPIController::create()->getPromotionalEventCity();
});

Route::get('/app/v1/pub/promotional-event-city/list', ['as' => 'pub-promotional-event-city', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventCity_getPromotionalEventCity']);

/**
 * Also like List of promotional-event
 */
Route::get('/api/v1/pub/promotional-event/suggestion/list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventAlsoLikeListAPIController::create()->getSearchPromotionalEvent();
});

Route::get('/app/v1/pub/promotional-event/suggestion/list', ['as' => 'pub-promotional-event-suggestion-list', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventAlsoLikeList_getSearchPromotionalEvent']);

/**
 * Get promotional event detail
 */
Route::get('/api/v1/pub/promotional-event/detail', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventDetailAPIController::create()->getPromotionalEventItem();
});

Route::get('/app/v1/pub/promotional-event/detail', ['as' => 'pub-promotional-event', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventDetail_getPromotionalEventItem']);

/**
 * Get signin / signup background for promotional event
 */
Route::get('/api/v1/pub/promotional-event/background', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventBackgroundAPIController::create()->getPromotionalEventBackground();
});

Route::get('/app/v1/pub/promotional-event/background', ['as' => 'pub-promotional-event-background', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventBackground_getPromotionalEventBackground']);

/**
 * Post user get issued code of promotional event
 */
Route::post('/api/v1/pub/promotional-event/issued', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionalEvent\PromotionalEventIssuedAPIController::create()->postIssuedPromotionalEvent();
});

Route::post('/app/v1/pub/promotional-event/issued', ['as' => 'pub-promotional-event-issued', 'uses' => 'IntermediatePubAuthController@PromotionalEvent\PromotionalEventIssued_postIssuedPromotionalEvent']);

