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

/**
 * List rating and review
 */
Route::get('/api/v1/pub/rating/list', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\RatingListAPIController::create()->getRatingList();
});

Route::get('/app/v1/pub/rating/list', ['as' => 'rating-list', 'uses' => 'IntermediatePubAuthController@Rating\RatingList_getRatingList']);

/**
 * List rating and review
 */
Route::get('/api/v1/pub/rating/user/list', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\UserRatingListAPIController::create()->getUserRatingList();
});

Route::get('/app/v1/pub/rating/user/list', ['as' => 'user-rating-list', 'uses' => 'IntermediatePubAuthController@Rating\UserRatingList_getUserRatingList']);

/**
 * Get search review for rating and review portal
 */
Route::get('/api/v1/review/list', function()
{
    return ReviewRatingAPIController::create()->getReviewList();
});

/**
 * Get search review for rating and review portal
 */
Route::get('/app/v1/review/list', 'IntermediateAuthController@ReviewRating_getReviewList');

/**
 * Rating detail and replies
 */
Route::get('/api/v1/review/detail', function()
{
    return RatingDetailAPIController::create()->getRatingDetail();
});

Route::get('/app/v1/review/detail', 'IntermediateAuthController@RatingDetail_getRatingDetail');

Route::get('/api/v1/review/detail/replies', function()
{
    return RatingDetailAPIController::create()->getRatingReplies();
});

Route::get('/app/v1/review/detail/replies', 'IntermediateAuthController@RatingDetail_getRatingReplies');
