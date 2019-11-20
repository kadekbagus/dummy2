<?php

/**
 * RGP related APIs
 */
Route::get('/app/v1/rgp/all-transaction-per-day', function()
{
    return Orbit\Controller\API\v1\rgp\PulsaReportAPIController::create()->getAllTransactionsPerDay();
});

