<?php

Route::post('/app/v1/pub/advertise-with-us', ['as' => 'pub-advertise-with-us', 'uses' => 'IntermediatePubAuthController@AdvertiseWithUsEmail_postAdvertiseWithUsEmail']);
