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

Route::post('/app/v1/login/admin', 'IntermediateLoginController@postLoginAdmin');
Route::post('/app/v1/logout/admin', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/mall', 'IntermediateLoginController@postLoginMall');
Route::post('/app/v1/logout/mall', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/mallcs', 'IntermediateLoginController@postLoginMallCustomerService');
Route::post('/app/v1/logout/mallcs', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/customer', 'IntermediateLoginController@postLoginCustomer');
Route::post('/app/v1/logout/customer', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/pmp', 'IntermediateLoginController@postLoginPMP');
Route::post('/app/v1/logout/pmp', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/mdm', 'IntermediateLoginController@postLoginMDM');
Route::post('/app/v1/logout/mdm', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/mtp', 'IntermediateLoginController@postLoginMTP');
Route::post('/app/v1/logout/mtp', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/rrp', 'IntermediateLoginController@postLoginRRP');
Route::post('/app/v1/logout/rrp', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/amp', 'IntermediateLoginController@postLoginAMP');
Route::post('/app/v1/logout/amp', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/pp', 'IntermediateLoginController@postLoginPP');
Route::post('/app/v1/logout/pp', 'IntermediateLoginController@getLogout');

Route::post('/app/v1/login/rgp', 'IntermediateLoginController@postLoginRGP');
Route::post('/app/v1/logout/rgp', 'IntermediateLoginController@getLogoutRGP');

Route::group(['before' => 'orbit-settings'], function() {
    // Route::post('/app/v1/customer/login', 'IntermediateLoginController@postLoginMobileCI');
});

Route::get('/app/v1/customer/cloud-login', 'IntermediateLoginController@getCloudLogin');

Route::post('/app/v1/customer/accept-cloud-data', 'IntermediateLoginController@postAcceptCloudLoginFullData');

/**
 * Customer registration
 */
Route::post('/app/v1/user/register/mobile', 'IntermediateLoginController@Login_postRegisterUserInShop');

/**
 * Setup new password by token
 */
Route::post('/app/v1/user/setup-new-password', 'IntermediateLoginController@Login_postSetupPasswordByToken');

/**
 * Token List
 */
Route::get('/app/v1/token/list', 'IntermediateLoginController@getTokenList');

/**
 * Active Account
 */
Route::post('/app/v1/activate-account', 'IntermediateLoginController@Login_postActivateAccount');

/**
 * Update Service Agreement
 */
Route::post('/app/v1/service-agreement/update', 'IntermediateLoginController@Login_postUpdateServiceAgreement');

/**
 * Update Service Agreement PMP Account
 */
Route::post('/app/v1/service-agreement-pmp/update', 'IntermediateLoginController@Login_postUpdateServiceAgreementPMP');

/**
 * Check Email Sign Up
 */
Route::group(['before' => 'orbit-settings'], function() {
    Route::post('/app/v1/customer/basic-data', 'IntermediateLoginController@checkEmailSignUp');
});