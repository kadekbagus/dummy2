<?php
/**
 * Routes file for User Follow related API
 */

/**
 * Get search user follow mall or store
 */
Route::get('/app/v1/pub/user-follow-list', ['as' => 'pub-user-follow-list', 'uses' => 'IntermediatePubAuthController@UserFollow\UserFollowList_getUserFollowList']);

/**
 * Post follow and unfollow mall or store
 */
Route::post('/app/v1/pub/follow', ['as' => 'pub-follow', 'uses' => 'IntermediatePubAuthController@UserFollow\Follow_postFollow']);


/**
 * Get store list user follow, call with ajax
 */
Route::get('/app/v1/pub/user-follow-store-list', ['as' => 'pub-user-follow-list', 'uses' => 'IntermediatePubAuthController@UserFollow\UserFollowStoreList_getUserFollowStoreList']);