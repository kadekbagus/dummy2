<?php

Route::get('app/v1/pub/bill/list', [
    'as' => 'pub-bill-list',
    'uses' => 'IntermediatePubAuthController@Bill\BillList',
]);
