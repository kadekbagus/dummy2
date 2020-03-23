<?php
/**
 * Routes file for login related API
 */

/**
 * Login user
 */
Route::post('/api/v1/login', function()
{
    return DummyAPIController::create()->unsupported();
});

Route::post('/api/v1/logout', function()
{
    return DummyAPIController::create()->unsupported();
});


Route::post('/api/v1/login/admin', function()
{
    return LoginAPIController::create()->postLoginAdmin();
});

Route::post('/api/v1/logout/admin', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/login/mall', function()
{
    return LoginAPIController::create()->postLoginMall();
});

Route::post('/api/v1/logout/mall', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/login/pmp', function()
{
    return LoginAPIController::create()->postLoginPMP();
});

Route::post('/api/v1/logout/pmp', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/login/mallcs', function()
{
    return LoginAPIController::create()->postLoginMallCustomerService();
});

Route::post('/api/v1/logout/mallcs', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/login/customer', function()
{
    return LoginAPIController::create()->postLoginCustomer();
});

Route::post('/api/v1/logout/customer', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/logout/mtp', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/logout/rrp', function()
{
    return LoginAPIController::create()->postLogout();
});

Route::post('/api/v1/login/amp', function()
{
    return LoginAPIController::create()->postLoginAMP();
});

Route::post('/api/v1/logout/amp', function()
{
    return LoginAPIController::create()->postLogout();
});

/**
 * Customer registration
 */
Route::post('/api/v1/user/register/mobile', function()
{
    return LoginAPIController::create()->postRegisterUserInShop();
});

/**
 * URL to setup new password
 */
Route::post('/api/v1/user/setup-new-password', function()
{
    return LoginAPIController::create()->postSetupPasswordByToken();
});

/**
 * URL to update service agreement
 */
Route::post('/api/v1/service-agreement/update', function()
{
    return LoginAPIController::create()->postUpdateServiceAgreement();
});

/**
 * URL to update service agreement pmp account
 */
Route::post('/api/v1/service-agreement-pmp/update', function()
{
    return LoginAPIController::create()->postUpdateServiceAgreementPMP();
});

/**
 * Token List
 */
Route::get('/api/v1/token/list', function()
{
    return TokenAPIController::create()->getSearchToken();
});

/**
 * Token List
 */
Route::post('/api/v1/activate-account', function()
{
    return LoginAPIController::create()->postActivateAccount();
});

/**
 * Public customer login
 */
Route::post(
    '/{app}/v1/pub/login/customer', ['as' => 'pub-customer-login', function()
    {
        return Orbit\Controller\API\v1\Pub\LoginAPIController::create()->postLoginCustomer();
    }]
)->where('app', '(api|app)');

/**
 * Public customer login desktop CI (Angular CI)
 */
Route::post(
    '/{app}/v1/pub/login/customer/desktop', ['as' => 'pub-customer-login-desktop', function()
    {
        return Orbit\Controller\API\v1\Pub\LoginAPIController::create()->postDesktopCILogin();
    }]
)->where('app', '(api|app)');

/**
 * Public customer logout desktop CI (Angular CI)
 */
Route::post(
    '/{app}/v1/pub/logout/customer/desktop', [
        'as' => 'pub-customer-logout-desktop',
        'uses' => 'IntermediateLoginController@getLogout'
    ]
)->where('app', '(api|app)');

/**
 * Public customer signup
 */
Route::post(
    '/{app}/v1/pub/signup/customer', ['as' => 'pub-customer-signup', function()
    {
        return Orbit\Controller\API\v1\Pub\RegistrationAPIController::create()->postRegisterCustomer();
    }]
)->where('app', '(api|app)');

Route::post(
    '/{app}/v1/pub/customer/basic-data', ['as' => 'pub-customer-check-email', function()
    {
        return Orbit\Controller\API\v1\Pub\LoginAPIController::create()->checkEmailSignUp();
    }]
)->where('app', '(api|app)');

Route::get(
    '/pub/social-google-callback', ['as' => 'pub.social_google_callback',
        function () {
            return Orbit\Controller\API\v1\Pub\LoginAPIController::create()->getGoogleCallbackView();
        },
    ]
);

Route::get(
    '/pub/social-login-callback', ['as' => 'pub.social_login_callback',
        function () {
            return Orbit\Controller\API\v1\Pub\LoginAPIController::create()->getSocialLoginCallbackView();
        },
    ]
);

Route::post(
    '/pub/social-login', ['as' => 'pub.social_login',
        function () {
            return Orbit\Controller\API\v1\Pub\LoginAPIController::create()->postSocialLoginView();
        },
    ]
);


Route::post(
    '/{app}/v1/pub/login/social-media', ['as' => 'pub.social_media_login',
        function () {
            return Orbit\Controller\API\v1\Pub\LoginSocialMediaAPIController::create()->postLoginSocialMedia();
        },
    ]
)->where('app', '(api|app)');