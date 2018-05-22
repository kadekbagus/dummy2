<?php

Route::post('/app/v1/sepulsa/redeem-callback', ['uses' => 'Orbit\Controller\API\v1\Pub\SepulsaRedeemCallbackController@validate']);


