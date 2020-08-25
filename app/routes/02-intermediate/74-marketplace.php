<?php

Route::post('/app/v1/pub/active-marketplace/list', [
    'as' => 'pub-active-marketplace-list',
    'uses' => 'IntermediatePubAuthController@Product\ActiveMarketplaceList_getActiveMarketplaces'
]);
