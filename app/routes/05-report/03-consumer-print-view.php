<?php

//    Route::get('/printer/consumer/list', 'Report\ConsumerPrinterController@getConsumerPrintView');

Route::get('/printer/consumer/list', [
    'as'        => 'printer-consumer-list',
    'uses'      => 'Report\ConsumerPrinterController@getConsumerPrintView'
]);