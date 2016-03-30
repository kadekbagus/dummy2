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

class UrlChecker
{
	const APPLICATION_ID = 1;
	protected $session = null;
    protected $retailer = null;

	/**
     * Prepare session.
     *
     * @return void
     */
    protected function prepareSession()
    {
        if (! is_object($this->session)) {
            // This user assumed are Consumer, which has been checked at login process
            $config = new SessionConfig(Config::get('orbit.session'));
            $config->setConfig('application_id', static::APPLICATION_ID);

            try {
                $this->session = new Session($config);
                $this->session->start();
            } catch (Exception $e) {
                
            }
        }
    }


	/**
     * Check user if logged in or not
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
    	$this->prepareSession();
        $userId = $this->session->read('user_id');
        if ($this->session->read('logged_in') !== true || ! $userId) {
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
        if (in_array(\Route::currentRouteName(), Config::get('orbit.blocked_routes'))) {
            $user = $this->getLoggedInUser();
            if (! $user) {
                throw new Exception('Session error: user not found.');
            }
        } else {
            $user = $this->getLoggedInUser();
            if (! is_object($user)) {
                $user = $this->generateGuestUser();
            }
        }

        return $user;
    }

	/**
     * Check if the route is blocked by the config or not
     * @param string route name
     * @return string full url or #
     */
    public function blockedRoute($url, $param = [])
    {
    	$this->prepareSession();

       	if (in_array($url, Config::get('orbit.blocked_routes'))) {
       		$userId = $this->session->read('user_id');
	        if ($this->session->read('logged_in') !== true || ! $userId) {
	            return '#';
	        }
       	}

        return URL::route($url, $param);
    }

    /**
     * Generate the guest user
     * If there are already guest_email on the cookie use that email instead
     *
     */
    public function generateGuestUser()
    {
        try{
            DB::beginTransaction();
            $guest_email = '';

            //check for existing guest email on cookie
            if (array_key_exists('orbit_guest_email', $_COOKIE)) {
                $cookie_email = $_COOKIE['orbit_guest_email'];
                if (empty($cookie_email)) {
                    $user = (new User)->generateGuestUser();
                    // dd($user);
                } else {
                    $guest_email = $cookie_email;
                    $guest = User::excludeDeleted()->where('user_email', $guest_email)->first();
                    if (empty ($guest)) { // directly inserted wrong email on cookie clear it
                        unset($_COOKIE['orbit_guest_email']);
                        setcookie('orbit_guest_email', null, -1, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                        return \Redirect::to(Request::url());
                    }

                    $user = $guest;
                }
            } else {
                $user = (new User)->generateGuestUser();
                // dd($user);
            }

            DB::commit();

            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
            setcookie('orbit_guest_email', $user->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

            return $user->load('userDetail');

        } catch (Exception $e) {
            DB::rollback();
            print_r([$e->getMessage(), $e->getLine()]);
        }
    }

    /**
     * Get current logged in user used in view related page.
     *
     * @return User $user
     */
    protected function getLoggedInUser()
    {
        $this->prepareSession();

        $userId = $this->session->read('user_id');

        if ($this->session->read('logged_in') !== true || ! $userId) {
            // throw new Exception('Invalid session data.');
        }

        // @todo: Why we query membership also? do we need it on every page?
        $user = User::with(['userDetail'])
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (! $user) {
            // throw new Exception('Session error: user not found.');
            $user = NULL;
        } else {
            $_user = clone($user);
            if (count($_user->membershipNumbers)) {
               $user->membership_number = $_user->membershipNumbers[0]->membership_number;
            }
        }

        return $user;
    }
}
