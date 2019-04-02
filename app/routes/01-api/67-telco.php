<?php

/**
 * Telco create
 */
Route::post('/api/v1/telco/new', function()
{
    return TelcoOperatorAPIController::create()->postNewTelcoOperator();
});

/**
 * Telco update
 */
Route::post('/api/v1/telco/update', function()
{
    return TelcoOperatorAPIController::create()->postUpdateTelcoOperator();
});

/**
 * Telco list
 */
Route::get('/api/v1/telco/list', function()
{
    return TelcoOperatorAPIController::create()->getSearchTelcoOperator();
});

/**
 * Telco Detail
 */
Route::get('/api/v1/telco/detail', function()
{
    return TelcoOperatorAPIController::create()->getDetailTelcoOperator();
});