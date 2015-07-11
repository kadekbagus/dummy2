<?php

//    Route::get('/printer/consumer/list', 'Report\ConsumerPrinterController@getConsumerPrintView');

Route::get('/printer/consumer/list', [
    'as'        => 'printer-consumer-list',
    'before'    => 'orbit-settings',
    'uses'      => 'Report\ConsumerPrinterController@getConsumerPrintView'
]);