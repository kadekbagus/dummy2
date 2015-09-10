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
    //
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
    $retailer = Retailer::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first();

    if(! empty($browserLang) AND in_array($browserLang, Config::get('orbit.languages', ['en']))) {
        // Set Browser Lang
        App::setLocale($browserLang);
    } else {
        // Set Merchant Setting Lang
        $merchantLang = $retailer->parent->mobile_default_language;
        if(! empty($merchantLang)) {
            App::setLocale($merchantLang);
        } else {
            // Fallback to 'en'
            App::setLocale('en');
        }
    }

    // set start_button_label config
    foreach ($retailer->settings as $setting) {
        if ($setting->setting_name == 'start_button_label') {
            if (! empty($setting->setting_value)) {
                Config::set('shop.start_button_label', $setting->setting_value);
            }
        }
    }

    if(empty(Config::get('shop.start_button_label'))) {
        Config::set('shop.start_button_label', Lang::get('mobileci.signin.start_button_mall'));
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
