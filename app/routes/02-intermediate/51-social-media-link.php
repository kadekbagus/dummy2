<?php
/**
 * List and/or search social media link
 */

Route::get('/app/v1/pub/social-media-link/list', ['as' => 'pub-social-media-link', 'uses' => 'IntermediatePubAuthController@SocialMediaLink_getSocialMediaLink']);
