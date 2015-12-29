<?php

// Get upload max file size
Route::get('/api/v1/upload/max-file-size', "UploadAPIController@getMaximumFileSize");

