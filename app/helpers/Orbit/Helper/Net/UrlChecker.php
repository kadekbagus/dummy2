<?php namespace Orbit\Helper\Net;
/**
 * Helper for checking url if it is blocked by the config or not
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \DB;
use \Config;
use \URL;
use \Route;
use \User;
use \UserDetail;
use \Exception;
use \Request;
use \App;
use OrbitShop\API\v1\OrbitShopAPI;
use Orbit\Helper\Session\AppOriginProcessor;
use Orbit\Helper\Exception\UrlException;

class UrlChecker
{
    protected $session = null;
    protected $retailer = null;
    protected $user = null;
    protected $customHeaders = array();
    protected $noPrepareSession = FALSE;

    public function __construct($session = NULL, $user = NULL) {
        $this->session = $session;
        $this->user = $user;
    }

    public function getUserSession()
    {
        return $this->session;
    }

    public function setUserSession($session) {
        $this->session = $session;
    }

    /**
     * Check user if logged in or not
     *
     * @return boolean
     */
    public static function isLoggedIn($session = NULL)
    {
        if (empty($session)) {
            OrbitShopAPI::throwInvalidArgument('Session error: user not found.');
        }

        $sessionId = $session->getSessionId();

        if (! empty($sessionId)) {
            $userRole = strtolower($session->read('role'));

            if ($session->read('logged_in') !== true || $userRole !== 'consumer') {
                return FALSE;
            } else {
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Check user if guest or not
     *
     * @param $user User
     * @return boolean
     */
    public static function isGuest($user = NULL)
    {
        if (is_null($user) || strtolower($user->role()->first()->role_name) !== 'guest') {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Check route, is it blocked by the config or not
     * If blocked return session error if the user not signed in
     * If not blocked return the $user object (signed in user / guest user)
     *
     * @return boolean
     */
    public static function checkBlockedUrl($user = null)
    {
        if (in_array(\Route::currentRouteName(), Config::get('orbit.blocked_routes', []))) {
            if (! is_object($user) || strtolower($user->role()->first()->role_name) !== 'consumer') {
                // check if the request is coming from mobile-ci or desktop-ci
                $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                    ->getAppName();

                $id = OrbitInput::get('id', '');
                $currentParams = \Input::all();
                $params = array();
                $currentRoute = \Route::currentRouteName();
                if ($appOrigin === 'mobile_ci') {
                    switch ($currentRoute) {
                        case 'ci-tenant-detail':
                            $redirectTo = 'ci-tenant-list';
                            $params = ['id' => $id];
                            break;
                        case 'ci-service-detail':
                            $redirectTo = 'ci-service-list';
                            $params = ['id' => $id];
                            break;
                        case 'ci-coupon-detail':
                            $redirectTo = 'ci-coupon-list';
                            $params = ['id' => $id];
                            break;
                        case 'ci-coupon-detail-wallet':
                            $redirectTo = 'ci-coupon-list';
                            $params = ['id' => $id];
                            break;
                        case 'ci-news-detail':
                            $redirectTo = 'ci-news-list';
                            $params = ['id' => $id];
                            break;
                        case 'ci-promotion-detail':
                            $redirectTo = 'ci-promotion-list';
                            $params = ['id' => $id];
                            break;
                        case 'ci-luckydraw-detail':
                            $redirectTo = 'ci-luckydraw-list';
                            $params = ['id' => $id];
                            break;

                        default:
                            $redirectTo = 'ci-home';
                            break;
                    }

                    $params = array_merge($params, $currentParams);
                    // throw exception custom UrlException
                    $redirectTo = URL::route($redirectTo, [
                        'do_sign_in' => 'true',
                        'redirect_url' => URL::route($currentRoute, $params)
                    ]);

                    throw new UrlException($redirectTo, 'You need to log in to view this page.', Session::ERR_SESS_NOT_FOUND);

                } elseif ($appOrigin === 'desktop_ci') {
                    OrbitShopAPI::throwInvalidArgument('You need to log in to view this page.');
                }
            }
        }

        return TRUE;
    }

    /**
     * Check if the route is blocked by the config or not
     * @param string route name
     * @param array query string parameter
     * @return string full url or #
     */
    public static function blockedRoute($url, $param = [], $session)
    {
        if (empty($session)) {
            OrbitShopAPI::throwInvalidArgument('Session error: user not found.');
        }

        if (in_array($url, Config::get('orbit.blocked_routes', []))) {
            $userRole = strtolower($session->read('role'));

            if ($session->read('logged_in') !== true || $userRole !== 'consumer') {
                return '#';
            }
        }

        return URL::route($url, $param);
    }
}
