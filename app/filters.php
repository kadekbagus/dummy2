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
use Net\Security\RequestAccess;
use Orbit\Helper\Net\FBBotChecker;

App::after(function($request, $response)
{
    // for handling back button after logout in Mobile-CI
    $response->headers->set('Cache-Control','nocache, no-store, max-age=0, must-revalidate');
    $response->headers->set('Pragma','no-cache');
    $response->headers->set('Expires','Fri, 01 Jan 1990 00:00:00 GMT');
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
| Facebook crawler goes here
|--------------------------------------------------------------------------
*/
Route::filter('fb-bot', function() {
    $FBChecker = new FBBotChecker();
    if (! $FBChecker->isFBCrawler()) {
        switch (Route::currentRouteName()) {
            case 'share-tenant':
                $redirect_to = URL::route('ci-tenant-detail', array('id' => Input::get('id')));
                break;
            case 'share-promotion':
                $redirect_to = URL::route('ci-promotion-detail', array('id' => Input::get('id')));
                break;
            case 'share-news':
                $redirect_to = URL::route('ci-news-detail', array('id' => Input::get('id')));
                break;
            case 'share-coupon':
                $redirect_to = URL::route('ci-coupon-detail', array('id' => Input::get('id')));
                break;
            case 'share-lucky-draw':
                $redirect_to = URL::route('ci-luckydraw-detail', array('id' => Input::get('id')));
                break;
            case 'share-home':
                $redirect_to = URL::route('ci-customer-home', array('id' => Input::get('id')));
                break;
            default:
                $redirect_to = NULL;
                break;
        }
        
        $param = is_null($redirect_to) ? NULL : ['socmed_redirect_to' => $redirect_to];

        // return Redirect::route('mobile-ci.signin', $param);
        return Redirect::to($redirect_to);
    }
});

Route::filter('turn-off-query-string-session', function()
{
    Config::set('orbit.session.availability.query_string', false);
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

    // checking ip address of the client
    $ip_address = Request::getClientIp();
    if (RequestAccess::create()->checkIpAddress($ip_address) === false) {
        // throw new Exception (sprintf('You have no access'));
        $retailer = Mall::with('parent', 'mediaIcon')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first();
        View::share('this_mall', $retailer);
        return View::make('mobile-ci.connect');
    }

    $browserLang = substr(Request::server('HTTP_ACCEPT_LANGUAGE'), 0, 2);
    $retailer = Mall::with('parent', 'mediaIcon')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first();

    View::share('this_mall', $retailer);

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
        $default_lang = 'en';

        $lg = Mall::with('parent')->where('merchant_id', Config::get('orbit.shop.id'))->excludeDeleted()->first()->mobile_default_language;
        $lang_str = Language::where('name', $lg)->first()->name;
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
                                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                                    ->whereHas('language', function($has) {
                                    $has->where('merchant_languages.status', 'active');
                                })->get();

                if (! empty($startButtonTranslation)) {
                    if (isset($startButtonTranslation[0]) && property_exists($startButtonTranslation[0], 'setting_value')) {
                        Config::set('shop.start_button_label', $startButtonTranslation[0]->setting_value);
                    }
                }
            }
        }
    }

    if(empty(Config::get('shop.start_button_label'))) {
        Config::set('shop.start_button_label', '');
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

Route::filter('check-routes-luckydraw-alternative', function()
{
    $retailer_id = App::make('orbitSetting')->getSetting('current_retailer');
    $retailer = Mall::with('parent')->where('merchant_id', $retailer_id)->first();

    foreach ($retailer->settings as $setting) {
        if ($setting->setting_name == 'enable_lucky_draw') {
            if ($setting->setting_value != 'true') {
                return Redirect::route('ci-customer-home');
            }
        }
    }
});
