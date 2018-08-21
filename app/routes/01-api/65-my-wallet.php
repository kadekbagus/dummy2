<?php

/**
 * Get my wallet item count on landing page
 */
Route::get('/api/v1/pub/my-wallet/count', function()
{
    return Orbit\Controller\API\v1\Pub\MyWallet\MyWalletItemCountAPIController::create()->getItemCount();
});

Route::get('/app/v1/pub/my-wallet/count', ['as' => 'pub-my-wallet-count', 'uses' => 'IntermediatePubAuthController@MyWallet\MyWalletItemCount_getItemCount']);
