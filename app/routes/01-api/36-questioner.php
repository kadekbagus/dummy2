<?php

/**
 * Post user answer
 */
Route::get('/api/v1/question', function()
{
    return QuestionerAPIController::create()->getQuestion();
});

/**
 * Post user answer
 */
Route::post('/api/v1/user-answer', function()
{
    return QuestionerAPIController::create()->postUserAnswer();
});