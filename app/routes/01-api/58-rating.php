<?php
/**
 * Routes file for Rating related API
 */

/**
 * Post add new rating
 */
Route::post('/api/v1/pub/rating/new', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\RatingNewAPIController::create()->postNewRating();
});

Route::post('/app/v1/pub/rating/new', ['as' => 'rating-new', 'uses' => 'IntermediatePubAuthController@Rating\RatingNew_postNewRating']);

/**
 * Post update rating
 */
Route::post('/api/v1/pub/rating/update', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\RatingUpdateAPIController::create()->postUpdateRating();
});

Route::post('/app/v1/pub/rating/update', ['as' => 'rating-update', 'uses' => 'IntermediatePubAuthController@Rating\RatingUpdate_postUpdateRating']);
