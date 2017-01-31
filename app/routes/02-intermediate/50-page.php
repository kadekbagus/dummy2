<?php
/**
 * List and/or Search pages
 */

Route::get('/app/v1/pub/page/list', ['as' => 'pub-page', 'uses' => 'IntermediatePubAuthController@Page_getPage']);
