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
 * Post update rating reply
 */
Route::post('/api/v1/pub/rating-reply/update', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\RatingReplyUpdateAPIController::create()->postUpdateRatingReply();
});

Route::post('/app/v1/pub/rating-reply/update', ['as' => 'rating-reply-update', 'uses' => 'IntermediatePubAuthController@Rating\RatingReplyUpdate_postUpdateRatingReply']);

/**
 * List rating and review
 */
Route::get('/api/v1/pub/rating/list', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\RatingListAPIController::create()->getRatingList();
});

Route::get('/app/v1/pub/rating/list', ['as' => 'rating-list', 'uses' => 'IntermediatePubAuthController@Rating\RatingList_getRatingList']);

/**
 * Get rating and review detail
 */
Route::post('/api/v1/pub/rating/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\RatingDetailAPIController::create()->getDetail();
});

Route::post('/app/v1/pub/rating/detail', ['as' => 'pub-review-detail', 'uses' => 'IntermediatePubAuthController@Rating\ReviewDetail_getDetail']);

/**
 * List reply of rating and review
 */
Route::get('/api/v1/pub/reply-rating-review/list', function()
{
    return Orbit\Controller\API\v1\Pub\Rating\ReplyRatingReviewListAPIController::create()->getReplyRatingReviewList();
});

Route::get('/app/v1/pub/reply-rating-review/list', ['as' => 'reply-rating-review-list', 'uses' => 'IntermediatePubAuthController@Rating\ReplyRatingReviewList_getReplyRatingReviewList']);

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
    return RatingReviewAPIController::create()->getReviewList();
});

/**
 * Get search review for rating and review portal
 */
Route::get('/app/v1/review/list', 'IntermediateAuthController@RatingReview_getReviewList');

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

/**
 * Reply to a Review
 */
Route::post('/api/v1/review/reply', function()
{
    return ReviewRatingReplyAPIController::create()->postReplyReviewRating();
});

Route::post('/app/v1/review/reply', 'IntermediateAuthController@ReviewRatingReply_postReplyReviewRating');


/**
 * Update Reply
 */
Route::post('/api/v1/review/reply/update', function()
{
    return RatingReviewAPIController::create()->postUpdateReply();
});

Route::post('/app/v1/review/reply/update', 'IntermediateAuthController@RatingReview_postUpdateReply');

/**
 * Delete Reply
 */
Route::post('/api/v1/review/reply/delete', function()
{
    return RatingReviewAPIController::create()->postDeleteReply();
});

Route::post('/app/v1/review/reply/delete', 'IntermediateAuthController@RatingReview_postDeleteReply');


/**
 * Delete Review
 */
Route::post('/api/v1/review/delete', function()
{
    return RatingReviewAPIController::create()->postDeleteReview();
});

Route::post('/app/v1/review/delete', 'IntermediateAuthController@RatingReview_postDeleteReview');




/**
 * Reply to a Review
 */
Route::post('/api/v1/review/imageapproval', function()
{
    return ReviewRatingImageApprovalAPIController::create()->postReplyReviewRating();
});

Route::post('/app/v1/review/imageapproval', 'IntermediateAuthController@ReviewRatingImageApproval_postImageApproval');
