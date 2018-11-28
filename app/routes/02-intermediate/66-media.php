<?php

Route::post('/app/v1/media/upload', ['as' => 'api-media-upload', 'uses' => 'IntermediateAuthController@Media_upload']);
