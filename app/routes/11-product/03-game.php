<?php
// route for game new/create
Route::post('/api/v1/game/new', function()
{
    return Orbit\Controller\API\v1\Product\Game\GameNewAPIController::create()->postNewGame();
});
Route::post('/app/v1/game/new', ['as' => 'game-api-new', 'uses' => 'IntermediateProductAuthController@Game\GameNew_postNewGame']);


// route for game listing
Route::get('/api/v1/game/{search}', function()
{
    return Orbit\Controller\API\v1\Product\Game\GameListAPIController::create()->getSearchGame();
})->where('search', '(list|search)');

Route::get('/app/v1/game/{search}', ['as' => 'game-api-new', 'uses' => 'IntermediateProductAuthController@Game\GameList_getSearchGame'])->where('search', '(list|search)');


// route for game detail
Route::get('/api/v1/game/detail', function()
{
    return Orbit\Controller\API\v1\Product\Game\GameDetailAPIController::create()->getDetailGame();
});

Route::get('/app/v1/game/detail', ['as' => 'game-api-detail', 'uses' => 'IntermediateProductAuthController@Game\GameDetail_getDetailGame']);


// route for game update
Route::post('/api/v1/game/update', function()
{
    return Orbit\Controller\API\v1\Product\Game\GameUpdateAPIController::create()->postUpdateGame();
});
Route::post('/app/v1/game/update', ['as' => 'game-api-update', 'uses' => 'IntermediateProductAuthController@Game\GameUpdate_postUpdateGame']);
