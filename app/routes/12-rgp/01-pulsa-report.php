<?php

/**
 * RGP related APIs
 */
Route::get('/app/v1/rgp/all-transactions-per-day', function()
{
    return Orbit\Controller\API\v1\RGP\PulsaReportTotalDailyAPIController::create()->get();
});

Route::get('/app/v1/rgp/all-transactions-per-month', function()
{
    return Orbit\Controller\API\v1\RGP\PulsaReportTotalMonthlyAPIController::create()->get();
});

Route::get('/app/v1/rgp/user-transactions-per-day', function()
{
    return Orbit\Controller\API\v1\RGP\PulsaReportUserDailyAPIController::create()->get();
});

Route::get('/app/v1/rgp/user-transactions-per-month', function()
{
    return Orbit\Controller\API\v1\RGP\PulsaReportUserMonthlyAPIController::create()->get();
});

Route::get('/app/v1/rgp/user-transactions', function()
{
    return Orbit\Controller\API\v1\RGP\PulsaReportAllUserAPIController::create()->get();
});

