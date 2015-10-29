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
    $retailer = Mall::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first();

    // Timezone should be set
    $timezone = $retailer->timezone;
    if (empty($timezone)) {
        throw new Exception (sprintf('You have to setup timezone for %s.', $retailer->name));
    }

    // Priority : 1. Cookie 2. Browser 3. Mall_setting 4. Fallback to 'en'

    // 1. Cek Cookie orbit_preferred_language
    if (array_key_exists('orbit_preferred_language', $_COOKIE)) {
        App::setLocale($_COOKIE['orbit_preferred_language']);
    } else {
        // 2. Cek browser language
        if (! empty($browserLang) AND in_array($browserLang, Config::get('orbit.languages', ['en']))) {
            App::setLocale($browserLang);
        } else {
            // 3. Cek mall / merchant setting
            $merchantLang = Mall::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first()->mobile_default_language;
            if (! empty($merchantLang)) {
                App::setLocale($merchantLang);
            } else {
                // 4. Fallback to 'en'
                App::setLocale('en');
            }
        }
    }

    $getLocaleLang = App::getLocale();

    // get language label for default mall lang
    App::singleton('default_lang', function() {
        $default_lang = 'English';

        $lg = Mall::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first()->mobile_default_language;
        $lang_str = Language::where('name', $lg)->first()->name_long;
        if(! empty($lang_str)) {
            $default_lang = $lang_str;
        }
        return $default_lang;
    });

    View::share('default_lang', app('default_lang'));

    // Set start button translation
    // Get language_id from locale
    $languageIdMall = \Language::where('name', '=', $getLocaleLang)->first();

    // Get merchant language
    $alternateLanguage = \MerchantLanguage::excludeDeleted()
        ->where('merchant_id', '=', $retailer->merchant_id)
        ->where('language_id', '=', $languageIdMall->language_id)
        ->first();

    if ($alternateLanguage !== NULL) {
        foreach ($retailer->settings as $value) {
            if ($value->setting_name == 'start_button_label') {
                // Get start button translation
                $startButtonTranslation = $value->hasMany('SettingTranslation', 'setting_id', 'setting_id')
                                    ->where('merchant_language_id', '=', $alternateLanguage->merchant_language_id)
                                    ->whereHas('language', function($has) {
                                    $has->where('merchant_languages.status', 'active');
                                })->get();

                if (! empty($startButtonTranslation)) {
                    if(! empty($startButtonTranslation[0]->setting_value)) {
                        Config::set('shop.start_button_label', $startButtonTranslation[0]->setting_value);
                    }
                }
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
    $retailer = Mall::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first();

    foreach ($retailer->settings as $setting) {
        if ($setting->setting_name == 'enable_lucky_draw') {
            if ($setting->setting_value != 'true') {
                return Redirect::route('ci-customer-home');
            }
        }
    }
});
