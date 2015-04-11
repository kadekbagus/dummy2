<?php
/**
 * Routes file for Intermediate Intermediate Pos Quick Product
 */

/**
 * Create New POS Quick Product
 */
Route::post('/app/v1/pos-quick-product/new', 'IntermediateAuthController@PosQuickProduct_postNewPosQuickProduct');

/**
 * Update POS Quick Product
 */
Route::post('/app/v1/pos-quick-product/update', 'IntermediateAuthController@PosQuickProduct_postUpdatePosQuickProduct');

/**
 * Delete POS Quick Product
 */
Route::post('/app/v1/pos-quick-product/delete', 'IntermediateAuthController@PosQuickProduct_postDeletePosQuickProduct');

/**
 * Get list POS Quick Product
 */
Route::get('/app/v1/pos-quick-product/list', 'IntermediateAuthController@PosQuickProduct_getSearchPosQuickProduct');
