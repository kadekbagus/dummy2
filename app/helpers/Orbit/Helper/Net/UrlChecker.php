<?php namespace Orbit\Helper\Net;
/**
 * Helper for checking url if it is blocked by the config or not
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \DB;
use \Config;
use \URL;
use \Route;
use \User;
use \UserDetail;
use \Exception;
use \Request;
use \App;
use Orbit\Helper\Net\GenerateGuestUser;

class UrlChecker
{
	const APPLICATION_ID = 1;
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
    public function isLoggedIn()
    {
        $sessionId = $this->session->getSessionId();

        if (! empty($sessionId)) {
            $userRole = strtolower($this->session->read('role'));

    	    if ($this->session->read('logged_in') !== true || $userRole !== 'consumer') {
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
    public function isGuest($user = NULL)
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
    public static function checkBlockedUrl($user)
    {
        if (in_array(\Route::currentRouteName(), Config::get('orbit.blocked_routes', []))) {
            if (! is_object($user)) {
                return FALSE;
            }
        } else {
            if (! is_object($user)) {
                return FALSE;
            } else {
                return TRUE;
            }
        }
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
            throw new Exception('Session error: user not found.');
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
