<?php
/**
 * Routes file for User Follow related API
 */

/**
 * Get search user follow mall or store
 */
Route::get('/api/v1/pub/user-follow-list', function()
{
    return Orbit\Controller\API\v1\Pub\UserFollow\UserFollowListAPIController::create()->getUserFollowList();
});

/**
 * Post follow mall or store
 */
Route::get('/api/v1/pub/follow', function()
{
    return Orbit\Controller\API\v1\Pub\UserFollow\FollowAPIController::create()->postFollow();
});


/**
 * Get store list user follow, call with ajax
 */
Route::get('/api/v1/pub/user-follow-store-list', function()
{
    return Orbit\Controller\API\v1\Pub\UserFollow\UserFollowStoreListAPIController::create()->getUserFollowStoreList();
});