<?php
/**
 * Routes file for Intermediate Product API
 */

/**
 * Create new product
 */
Route::post('/app/v1/product/new', 'IntermediateAuthController@Product_postNewProduct');

/**
 * Delete product
 */
Route::post('/app/v1/product/delete', 'IntermediateAuthController@Product_postDeleteProduct');

/**
 * Update product
 */
Route::post('/app/v1/product/update', 'IntermediateAuthController@Product_postUpdateProduct');

/**
 * List and/or Search product
 */
Route::get('/app/v1/product/search', 'IntermediateAuthController@Product_getSearchProduct');

/**
 * Upload Product Image
 */
Route::post('/app/v1/product/upload/image', 'IntermediateAuthController@Upload_postUploadProductImage');

/**
 * Delete Product Image
 */
Route::post('/app/v1/product/delete/image', 'IntermediateAuthController@Upload_postDeleteProductImage');

/**
 * Create new product attribute
 */
Route::post('/app/v1/product-attribute/new', 'IntermediateAuthController@ProductAttribute_postNewAttribute');

/**
 * List product attribute
 */
Route::get('/app/v1/product-attribute/list', 'IntermediateAuthController@ProductAttribute_getSearchAttribute');

/**
 * Update product attribute
 */
Route::post('/app/v1/product-attribute/update', 'IntermediateAuthController@ProductAttribute_postUpdateAttribute');

/**
 * Delete product attribute
 */
Route::post('/app/v1/product-attribute/delete', 'IntermediateAuthController@ProductAttribute_postDeleteAttribute');
