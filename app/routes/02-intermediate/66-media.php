<?php

Route::post('/app/v1/media/upload', ['as' => 'api-media-upload', 'uses' => 'IntermediateAuthController@Media_upload']);

Route::post('/app/v1/media/delete', ['as' => 'api-media-delete', 'uses' => 'IntermediateAuthController@Media_delete']);
