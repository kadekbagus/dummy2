<?php

/**
 * Start coupon transfer
 */
Route::post('/app/v1/pub/coupon-transfer', ['as' => 'coupon-transfer', 'uses' => 'IntermediatePubAuthController@Coupon\Transfer\Transfer_postTransfer']);

/**
 * Accept a coupon transfer.
 */
Route::post('/app/v1/pub/coupon-transfer/accept', ['as' => 'accept-coupon-transfer', 'uses' => 'IntermediatePubAuthController@Coupon\Transfer\AcceptTransfer_postAcceptTransfer']);

/**
 * Cancel a coupon transfer
 */
Route::post('/app/v1/pub/coupon-transfer/cancel', ['as' => 'cancel-coupon-transfer', 'uses' => 'IntermediatePubAuthController@Coupon\Transfer\CancelTransfer_postCancelTransfer']);

/**
 * Decline a coupon transfer.
 */
Route::post('/app/v1/pub/coupon-transfer/decline', ['as' => 'decline-coupon-transfer', 'uses' => 'IntermediatePubAuthController@Coupon\Transfer\DeclineTransfer_postDeclineTransfer']);

/**
 * Get coupon transfer status.
 */
Route::get('/app/v1/pub/coupon-transfer/status', ['as' => 'coupon-transfer-status', 'uses' => 'IntermediatePubAuthController@Coupon\Transfer\DeclineTransfer_postDeclineTransfer']);
