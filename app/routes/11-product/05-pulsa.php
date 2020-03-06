<?php



// Route for pulsa listing
Route::get('/app/v1/pulsa/{search}', ['as' => 'pulsa-list', 'uses' => 'IntermediateProductAuthController@Pulsa\PulsaList_getList'])->where('search', '(list|search)');

// Route for pulsa detail
Route::get('/app/v1/pulsa/detail', ['as' => 'pulsa-detail', 'uses' => 'IntermediateProductAuthController@Pulsa\PulsaDetail_getDetail']);

// Route for pulsa new
Route::post('/app/v1/pulsa/new', ['as' => 'pulsa-new', 'uses' => 'IntermediateProductAuthController@Pulsa\PulsaNew_postNew']);

// Route for pulsa update
Route::post('/app/v1/pulsa/update', ['as' => 'pulsa-update', 'uses' => 'IntermediateProductAuthController@Pulsa\PulsaUpdate_postUpdate']);

// Route for pulsa update status
// Route::post('/app/v1/pulsa/update-status', ['as' => 'pulsa-update-status', 'uses' => 'IntermediateProductAuthController@Pulsa\PulsaNew_postUpdateStatus']);
