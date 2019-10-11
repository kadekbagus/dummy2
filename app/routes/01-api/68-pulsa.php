<?php

/**
 * Pulsa create
 */
Route::post('/api/v1/pulsa/new', function()
{
    return PulsaAPIController::create()->postNewPulsa();
});

/**
 * Pulsa update
 */
Route::post('/api/v1/pulsa/update', function()
{
    return PulsaAPIController::create()->postUpdatePulsa();
});

/**
 * Pulsa status update
 */
Route::post('/api/v1/pulsa/update-status', function()
{
    return PulsaAPIController::create()->postUpdatePulsaStatus();
});

/**
 * Pulsa list
 */
Route::get('/api/v1/pulsa/list', function()
{
    return PulsaAPIController::create()->getSearchPulsa();
});

/**
 * Pulsa Detail
 */
Route::get('/api/v1/pulsa/detail', function()
{
    return PulsaAPIController::create()->getDetailPulsa();
});
