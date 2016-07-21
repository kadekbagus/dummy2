<?php

/**
 * Post user answer
 */
Route::get('/api/v1/pub/question', function()
{
    return QuestionerAPIController::create()->getQuestion();
});

/**
 * Post user answer
 */
Route::post('/api/v1/pub/user-answer', function()
{
    return QuestionerAPIController::create()->postUserAnswer();
});