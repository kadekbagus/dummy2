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

    public function __construct($session = NULL, $user = NULL) {
        $this->session = $session;
        $this->user = $user;
    }

    public function getUserSession()
    {
        return $this->session;
    }

	/**
     * Check user if logged in or not
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        $userRole = strtolower($this->session->read('role'));

	    if ($this->session->read('logged_in') !== true || $userRole !== 'consumer') {
            return FALSE;
        }

        return TRUE;
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
     * @return $user User (signed in user or guest user)
     */
    public function checkBlockedUrl()
    {
        $user = $this->user;
        if (in_array(\Route::currentRouteName(), Config::get('orbit.blocked_routes', []))) {
            if (! $user) {
                throw new Exception('Session error: user not found.');
            }
        } else {
            if (! is_object($user)) {
                $user = GenerateGuestUser::generateGuestUser($this->session);

                if (! $user) {
                    throw new Exception($user);
                }
            }
        }

        return $user;
    }

	/**
     * Check if the route is blocked by the config or not
     * @param string route name
     * @param array query string parameter
     * @return string full url or #
     */
    public function blockedRoute($url, $param = [])
    {
       	if (in_array($url, Config::get('orbit.blocked_routes', []))) {
       		$userRole = strtolower($this->session->read('role'));

	        if ($this->session->read('logged_in') !== true || $userRole !== 'consumer') {
	            return '#';
	        }
       	}

        return URL::route($url, $param);
    }
}
