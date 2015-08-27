<?php

/*
|--------------------------------------------------------------------------
| Application & Route Filters
|--------------------------------------------------------------------------
|
| Below you will find the "before" and "after" events for the application
| which may be used to do any work before or after a request into your
| application. Here you may also register your custom route filters.
|
*/

App::before(function($request)
{
    $allowedRoutes = ['api/v1/agreement', 'app/v1/agreement'];

    // If: request route is agreement then allowed
    // else: check agreement setting
    if (! in_array($request->path(), $allowedRoutes)) {

        // set mall id
        $mallId = App::make('orbitSetting')->getSetting('current_retailer');

        // Builder object
        $settings = Setting::excludeDeleted()
                           ->where('object_type', 'merchant')
                           ->where('object_id', $mallId)
                           ->where('setting_name', 'agreement')
                           ->where('status', 'active')
                           ->first();

        if (empty($settings)) {
            $agreement = 'no';
        } else {
            $agreement = $settings->setting_value;
        }

        if ($agreement !== 'yes') {
            return DummyAPIController::create()->unsupported();
        }

    }
});


App::after(function($request, $response)
{
    //
});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function()
{
    if (Auth::guest())
    {
        if (Request::ajax())
        {
            return Response::make('Unauthorized', 401);
        }
        else
        {
            return Redirect::guest('login');
        }
    }
});

Route::filter('authCustomer', function()
{

});

Route::filter('auth.basic', function()
{
    return Auth::basic();
});

/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
|
| The "guest" filter is the counterpart of the authentication filters as
| it simply checks that the current user is not logged in. A redirect
| response will be issued if they are, which you may freely change.
|
*/

Route::filter('guest', function()
{
    if (Auth::check()) return Redirect::to('/');
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/

Route::filter('csrf', function()
{
    if (Session::token() !== Input::get('_token'))
    {
        throw new Illuminate\Session\TokenMismatchException;
    }
});

/*
|--------------------------------------------------------------------------
| Mobile-CI Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/
Route::filter('orbit-settings', function()
{
    if (! App::make('orbitSetting')->getSetting('current_retailer')) {
        throw new Exception ('You have to setup current retailer first on Admin Portal.');
    }

    $browserLang = substr(Request::server('HTTP_ACCEPT_LANGUAGE'), 0, 2);

    if (isset($_COOKIE['orbit_preferred_language'])){
        App::setLocale($_COOKIE['orbit_preferred_language']);
    }else{
        if(! empty($browserLang) AND in_array($browserLang, Config::get('orbit.languages', ['en']))) {
            // Set Browser Lang
            App::setLocale($browserLang);
        } else {
            // Set Merchant Setting Lang
            $merchantLang = Retailer::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first()->parent->mobile_default_language;
            if(! empty($merchantLang)) {
                App::setLocale($merchantLang);
            } else {
                // Fallback to 'en'
                App::setLocale('en');
            }
        }
    }

});

/*
|--------------------------------------------------------------------------
| Check luckydraw routes based on database setting
|--------------------------------------------------------------------------
*/
Route::filter('check-routes-luckydraw', function()
{
    $retailer = Retailer::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first();

    foreach ($retailer->settings as $setting) {
        if ($setting->setting_name == 'enable_lucky_draw') {
            if ($setting->setting_value != 'true') {
                return Redirect::route('ci-customer-home');
            }
        }
    }
});
