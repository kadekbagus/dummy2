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
    protected $customHeaders = array();

    public function __construct() {
        $this->prepareSession();
    }
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
    	$this->prepareSession();
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
        if (in_array(\Route::currentRouteName(), Config::get('orbit.blocked_routes', []))) {
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

       	if (in_array($url, Config::get('orbit.blocked_routes', []))) {
       		$userRole = strtolower($this->session->read('role'));

	        if ($this->session->read('logged_in') !== true || $userRole !== 'consumer') {
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
            $guest_email = $this->session->read('email');
            if (empty($guest_email)) {
            	DB::beginTransaction();
                $user = (new User)->generateGuestUser();
                DB::commit();
            } else {
                $guest = User::excludeDeleted()->where('user_email', $guest_email)->first();
                $user = $guest;
            }

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name,
                'fullname'  => $user->getFullName(),
            );
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            return $user;

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
