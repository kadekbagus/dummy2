<?php
/**
 * Routes file for login related API.
 * This URL which should get called by the Frontend.
 */

/**
 * Login and logout user
 */
Route::post('/app/v1/login', 'IntermediateLoginController@Dummy_unsupported');
Route::post('/app/v1/logout', 'IntermediateLoginController@Dummy_unsupported');
Route::get('/app/v1/logout', 'IntermediateLoginController@Dummy_unsupported');
Route::get('/app/v1/session', 'IntermediateLoginController@getSession');

Route::group(['before' => 'orbit-settings'], function() {
    Route::post('/app/v1/login/admin', 'IntermediateLoginController@postLoginAdmin');
    Route::post('/app/v1/logout/admin', 'IntermediateLoginController@getLogout');

    Route::post('/app/v1/login/mall', 'IntermediateLoginController@postLoginMall');
    Route::post('/app/v1/logout/mall', 'IntermediateLoginController@getLogout');

    Route::post('/app/v1/login/mallcs', 'IntermediateLoginController@postLoginMallCustomerService');
    Route::post('/app/v1/logout/mallcs', 'IntermediateLoginController@getLogout');

    Route::post('/app/v1/login/customer', 'IntermediateLoginController@postLoginCustomer');
    Route::post('/app/v1/logout/customer', 'IntermediateLoginController@getLogout');
});

/**
 * Customer registration
 */
Route::post('/app/v1/user/register/mobile', 'IntermediateLoginController@Login_postRegisterUserInShop');

/**
 * Check the token
 */
Route::post('/app/v1/user/token/check', 'IntermediateLoginController@postRegisterTokenCheck');

/**
 * Update Service Agreement
 */
Route::post('/app/v1/service-agreement/update', 'IntermediateLoginController@Login_postUpdateServiceAgreement');

/**
 * Alias for check token
 */
Route::post('/app/v1/user/activate', 'IntermediateLoginController@postRegisterTokenCheck');

/**
 * Token List
 */
Route::get('/app/v1/token/list', 'IntermediateLoginController@getTokenList');
