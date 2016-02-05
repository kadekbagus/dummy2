<?php

// Get upload max file size
Route::get('/app/v1/upload/max-file-size', 'UploadAPIController@getMaximumFileSize');
