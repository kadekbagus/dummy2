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

    public function __construct($session = NULL, $user = NULL, $caller = null) {
        $this->session = $session;
        $this->user = $user;
		if ($caller === 'no-prepare-session') {
            $this->noPrepareSession = TRUE;
        } else {
			if (empty($caller)) {
				$this->prepareSession();
			} else {
				$this->prepareSession('IntermediateCIAuthController');
			}
		}
    }

    /**
     * Prepare session.
     *
     * @return void
     */
    protected function prepareSession($caller = null)
    {
        if ($this->noPrepareSession) {
            return;
        }

        if (! is_object($this->session)) {
            // set the session strict to FALSE
            Config::set('orbit.session.strict', FALSE);
            if (empty($caller)) {
                // set the query session string to FALSE, so the CI will depend on session cookie
                Config::set('orbit.session.availability.query_string', FALSE);
            } else {
                Config::set('orbit.session.availability.query_string', TRUE);
            }

            // This user assumed are Consumer, which has been checked at login process
            $config = new SessionConfig(Config::get('orbit.session'));
            $config->setConfig('application_id', static::APPLICATION_ID);

            try {
                $this->session = new Session($config);
                $this->session->start(array(), 'no-session-creation');
            } catch (Exception $e) {
                $this->session->start();
            }
        }
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
     * Get current logged in user used in view related page.
     *
     * @return User $user
     */
    protected function getLoggedInUser()
    {
        $this->prepareSession();
        $mall_id = App::make('orbitSetting')->getSetting('current_retailer');
        $userId = $this->session->read('user_id');

        if ($this->session->read('logged_in') !== true || ! $userId) {
            // throw new Exception('Invalid session data.');
        }

        $user = User::with(['userDetail', 'membershipNumbers' => function($q) use ($mall_id) {
                $q->select('membership_numbers.*')
                    ->with('membership.media')
                    ->join('memberships', 'memberships.membership_id', '=', 'membership_numbers.membership_id')
                    ->excludeDeleted('membership_numbers')
                    ->excludeDeleted('memberships')
                    ->where('memberships.merchant_id', $mall_id);
            }])
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
