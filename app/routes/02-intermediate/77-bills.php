<?php

Route::get('app/v1/pub/bill/list', [
    'as' => 'pub-bill-list',
    'uses' => 'IntermediatePubAuthController@Bill\BillList',
]);

Route::get('app/v1/pub/bill-purchased/detail', [
    'as' => 'pub-bill-purchased-detail',
    'uses' => 'IntermediatePubAuthController@Bill\BillPurchasedDetail',
]);
