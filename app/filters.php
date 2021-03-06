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
use Orbit\Helper\Security\MallAccess;
use Net\Util\MobileDetect;
use Orbit\Helper\Net\SessionPreparer;

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

Route::filter('orbit-csrf', function()
{
    $session = SessionPreparer::prepareSession();

    if ($session->read('orbit_csrf_token') !== Input::get('_token'))
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
                $redirect_to = URL::route('ci-tenant-detail', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
                break;
            case 'share-promotion':
                $redirect_to = URL::route('ci-promotion-detail', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
                break;
            case 'share-news':
                $redirect_to = URL::route('ci-news-detail', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
                break;
            case 'share-coupon':
                $redirect_to = URL::route('ci-coupon-detail', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
                break;
            case 'share-lucky-draw':
                $redirect_to = URL::route('ci-luckydraw-detail', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
                break;
            case 'share-home':
                $redirect_to = URL::route('ci-customer-home', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
                break;
            case 'share-promotional-event':
                $redirect_to = URL::route('ci-promotional-event-detail', array('id' => Input::get('id'), 'name' => Str::slug(Input::get('name', ''))));
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

/*
|--------------------------------------------------------------------------
| Facebook crawler goes here
| (Facebook bot filter for pub routes)
|--------------------------------------------------------------------------
*/
Route::filter('pub-fb-bot', function() {
    Config::set('orbit.session.availability.query_string', false);
    $gtmUrl = Config::get('orbit.shop.gtm_url');
    $FBChecker = new FBBotChecker();
    $item = NULL;
    $country = Input::get('country', null);
    $cities = Input::get('cities', null);

    if (! $FBChecker->isFBCrawler()) {
        switch (Route::currentRouteName()) {
            case 'pub-share-promotion':
                $type = 'promotions';

                $item = News::excludeDeleted()
                    ->where('object_type', 'promotion')
                    ->where('news_id', Input::get('id', NULL))
                    ->first();

                break;
            case 'pub-share-news':
                $type = 'news';

                $item = News::excludeDeleted()
                    ->where('object_type', 'news')
                    ->where('news_id', Input::get('id', NULL))
                    ->where('is_having_reward', 'N')
                    ->first();

                break;
            case 'pub-share-coupon':
                $type = 'coupons';

                $item = Coupon::excludeDeleted()
                    ->where('promotion_id', Input::get('id', NULL))
                    ->first();

                break;
            case 'pub-share-store':
                $type = 'stores';

                $item = Tenant::excludeDeleted()
                    ->where('merchant_id', Input::get('id', NULL))
                    ->first();

                break;
            case 'pub-share-promotional-event':
                $type = 'promotional-event';

                $item = News::excludeDeleted()
                    ->where('object_type', 'news')
                    ->where('news_id', Input::get('id', NULL))
                    ->where('is_having_reward', 'Y')
                    ->first();

                break;
            default:
                $type = '';

                break;
        }

        if (is_object($item)) {
            $utmParamConfig = Config::get('orbit.campaign_share_email.utm_params');
            $config = [
                'stores'     => Config::get('orbit.campaign_share_email.store_detail_base_url'),
                'promotions' => Config::get('orbit.campaign_share_email.promotion_detail_base_url'),
                'coupons'    => Config::get('orbit.campaign_share_email.coupon_detail_base_url'),
                'news'       => Config::get('orbit.campaign_share_email.news_detail_base_url'),
                'promotional-event' => Config::get('orbit.campaign_share_email.promotional_event_detail_base_url'),
            ];

            $countryCityParams = '';
            $countryString = '';

            if ($country != null) {
                $countryString .= '&country=' . $country;
            }

            $citiesString = '';

            if ($cities == null) {
                $citiesString .= '&cities=0';
            } else {
                foreach ((array) $cities as $city) {
                    $citiesString .= '&cities=' . $city;
                }
            }

            if (! empty($countryString)) {
                $countryCityParams = $countryString . $citiesString;
            }

            $utmParam = http_build_query($utmParamConfig['facebook']);

            $param = $utmParam . $countryCityParams;

            $redirect_to = sprintf($config[$type], Input::get('id'), Str::slug(Input::get('name', '', $separator = '-')), $param);
        } else {
            $redirect_to = URL::to($gtmUrl);
        }

        $_POST['plain'] = true;

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

    if (! MallAccess::create()->isAccessible($retailer)) {
        App::abort(403, sprintf('%s is inaccessible at the moment.', htmlentities($retailer->name)));
    }

    // Redirect mall to the new URL
    $mallLandingPageUrl = rtrim(Config::get('app.url'), '/') . '/#!/mall/' . $retailer->merchant_id;
    Return Redirect::to($mallLandingPageUrl);

    if (! (new MobileDetect)->isMobile()) {
        Config::set('orbit.error_message.e500', 'Desktop version is coming soon, please use mobile device to access this site at the moment.');

        return Redirect::to(Config::get('app.url'));
    }

    View::share('this_mall', $retailer);

    // Timezone should be set
    $timezone = $retailer->timezone;
    if (empty($timezone)) {
        throw new Exception (sprintf('You have to setup timezone for %s.', $retailer->name));
    }

    // Priority : 1. Cookie 2. Browser 3. Mall_setting 4. Fallback to 'en'
    if (! isset($_COOKIE['orbit_preferred_language'])) {
        $_COOKIE['orbit_preferred_language'] = 'id';
    }

    // 1. Cek Cookie orbit_preferred_language
    if (array_key_exists('orbit_preferred_language', $_COOKIE)) {
        App::setLocale($_COOKIE['orbit_preferred_language']);
    } else {
        // 2. Cek browser language
        if (! empty($browserLang) AND in_array($browserLang, Config::get('orbit.languages', ['en']))) {
            App::setLocale($browserLang);
        } else {
            // 3. Cek mall / merchant setting
            $merchantLang = $retailer->mobile_default_language;
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
    App::singleton('default_lang', function() use ($retailer) {
        $default_lang = 'en';

        if (empty($retailer->mobile_default_language)) {
            throw new Exception (sprintf('Mobile default language is not set on the table for mall %s.', $retailer->name));
        }

        $lg = $retailer->mobile_default_language;
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
