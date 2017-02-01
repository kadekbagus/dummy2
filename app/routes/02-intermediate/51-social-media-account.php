<?php
/**
 * List and/or search social media account
 */

Route::get('/app/v1/pub/social-media-account/list', ['as' => 'pub-social-media-account', 'uses' => 'IntermediatePubAuthController@SocialMediaAccount_getSocialMediaAccount']);
