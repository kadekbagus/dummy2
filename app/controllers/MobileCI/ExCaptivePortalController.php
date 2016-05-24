<?php namespace MobileCI;
use URL;
use Config;
use View;
use Redirect;
use Orbit\Helper\Net\UrlChecker;
use Widget;
use Log;
use Lang;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class ExCaptivePortalController extends BaseCIController
{
    public function getExCaptiveLanding()
    {
        $mac = isset($_GET['client_mac']) ? $_GET['client_mac'] : '';
        $ip = isset($_GET['client_ip']) ? $_GET['client_ip'] : '';

        $params = [
            'mac_address' => $mac,
            'ip' => $ip,
            'from_captive' => 'yes'
        ];
        $url = URL::route('ci-customer-home', $params);

        $logMessage = sprintf('-- CAPTIVE REDIRECT URL -> %s', $url);
        Log::info($logMessage);

        $this->setCookieForCaptive();

        return Redirect::to($url);
    }

    public function getCaptiveRequestInternet()
    {
        $user = null;
        $media = null;
        $user_full_name = null;

        try {
            $this->pageTitle = Lang::get('mobileci.captive.request_internet.title');

            $params = [
                'continue_url' => URL::route('captive-internet-granted')
            ];
            $grantUrl = Config::get('orbit.captive.base_grant_url');
            $grantedUrl = URL::route('captive-internet-granted');
            $pingUrl = Config::get('orbit.captive.ping_url', 'http://tools.pingdom.com/fpt/favicon.ico');
            $timeout = Config::get('orbit.captive.delay_button', 2);

            $data = [
                    'base_grant_url' => $grantUrl,
                    'granted_url' => $grantedUrl,
                    'ping_url' => $pingUrl,
                    'timeout' => $timeout,
                    'params' => $params
            ];
            $data = $data + $this->fillCommonViewsData();

            return View::make('mobile-ci.captive-request-internet', $data);
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public function getCaptiveInternetGranted()
    {
        $user = null;
        $media = null;
        $user_full_name = null;
        $cookieName = Config::get('orbit.session.session_origin.cookie.name', 'orbit_sessionx');
        $sessionQueryName = Config::get('orbit.session.session_origin.query_string.name', 'orbit_session');

        try {
            $this->pageTitle = Lang::get('mobileci.captive.granted.title');

            $sessionId = isset($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : NULL;
            Log::info(sprintf('-- CAPTIVE PORTAL -> INTERNET GRANTED SID: %s', $sessionId));

            $params = [
                'from_captive' => 'yes'
            ];

            if (! is_null($sessionId)) {
                $params = $params + [$sessionQueryName => $sessionId];
            }

            $continueUrl = Config::get('orbit.captive.continue_url',
                URL::route('ci-customer-home'));

            $data = [
                    'continue_url' => $continueUrl,
                    'params' => $params
            ];
            $data = $data + $this->fillCommonViewsData();

            return View::make('mobile-ci.captive-internet-granted', $data);
        } catch (Exception $e) {
            return $this->redirectIfNotLoggedIn($e);
        }
    }

    public static function generateDummyWidget($merchant, UrlChecker $urblock)
    {
        $widget = new Widget();
        $widget->widget_id = 'XXXX';
        $widget->widget_type = 'captive';
        $widget->widget_object_id = '0';
        $widget->widget_slogan = 'Get Free Internet Acccess';
        $widget->widget_order = '6';
        $widget->merchant_id = $merchant->merchant_id;
        $widget->animation = 'none';
        $widget->status = 'active';
        $widget->created_by = '0';
        $widget->modified_by = '0';

        // Additional property set on the CI
        $widget->item_count = 0;
        $widget->new_item_count = 0;
        $widget->display_title = Lang::get('mobileci.captive.widget_slogan');
        $widget->display_sub_title = '';
        $widget->url = $urblock->blockedRoute('captive-request-internet');
        $widget->redirect_url = URL::route('captive-request-internet');
        $widget->image = asset('mobile-ci/images/balloon-internet.jpg');

        return $widget;
    }

    public static function isFromCaptive()
    {
        // is there any query string called 'from_captive' and
        // the value is 'yes'
        $fromQueryString = OrbitInput::get('from_captive', 'no') === 'yes';

        // is there any cookie called 'from_captive' and
        // the value is 'yes'
        $fromCookie = isset($_COOKIE['from_captive']) ? $_COOKIE['from_captive'] === 'yes' : FALSE;

        return $fromQueryString || $fromCookie;
    }

    public static function setCookieForCaptive()
    {
        $domain = Config::get('orbit.session.session_origin.cookie.domain', NULL);
        $path = Config::get('orbit.session.session_origin.cookie.path', '/');
        $expire = time() + 7200;

        setcookie('from_captive', 'yes', $expire, $path, $domain, FALSE);
    }

    public static function isSessionQueryExists()
    {
        // is there any query string called 'from_captive' and
        // the value is 'yes'
        $sessionQueryName = Config::get('orbit.session.session_origin.query_string.name', 'orbit_session');

        return (! empty(OrbitInput::get($sessionQueryName, FALSE)));
    }

    public static function forceOverrideCookie($sessionId)
    {
        $cookieName = Config::get('orbit.session.session_origin.cookie.name', 'orbit_sessionx');
        $expire = Config::get('orbit.session.session_origin.cookie.expire', 7200);
        $path = Config::get('orbit.session.session_origin.cookie.path', '/');
        $domain = Config::get('orbit.session.session_origin.cookie.domain', NULL);
        $expire = time() + $expire;

        Log::info(sprintf('-- CAPTIVE PORTAL -> Force SID to %s', $sessionId));

        // Force the cookie global variable so it affected on current request
        $_COOKIE[$cookieName] = $sessionId;

        setcookie($cookieName, $sessionId, $expire, $path, $domain, FALSE);
    }
}