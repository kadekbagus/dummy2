<?php
/**
 * Routes file for Blogs
 */

/**
 * Get list of Wordpress post list
 */
Route::get('/api/v1/pub/blog/posts/list', function()
{
    return Orbit\Controller\API\v1\Pub\Wordpress\WordpressPostListAPIController::create()->getPostList();
});

Route::get('/app/v1/pub/blog/posts/list', ['as' => 'blog-post-list', 'uses' => 'IntermediatePubAuthController@Wordpress\WordpressPostList_getPostList']);

/**
 * Proceed Web Hooks calls from Wordpress
 */
Route::post('/api/v1/pub/blog/web-hooks/post', function()
{
    return Orbit\Controller\API\v1\Pub\Wordpress\WordpressWebHooksPostAPIController::create()->postWebHooks();
});

Route::post('/app/v1/pub/blog/web-hooks/post', ['as' => 'blog-web-hooks-post', 'uses' => 'IntermediatePubAuthController@Wordpress\WordpressWebHooksPost_postWebHooks']);
