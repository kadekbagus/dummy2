<?php

/**
 * Get Quentions
 */
Route::get(
    '/{search}/v1/pub/question', ['as' => 'question', function()
    {
        return Orbit\Controller\API\v1\Pub\QuestionerAPIController::create()->getQuestion();
    }]
)->where('search', '(api|app)');


/**
 * Post user answer
 */
Route::post('/api/v1/user-answer', function()
{
    return QuestionerAPIController::create()->postUserAnswer();
});