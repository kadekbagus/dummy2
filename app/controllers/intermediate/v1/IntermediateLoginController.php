<?php
/**
 * Intermediate Controller for handling user login
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ResponseProvider;
use MobileCI\MobileCIAPIController;
use Net\Security\Firewall;
use \Cookie;

class IntermediateLoginController extends IntermediateBaseController
{
    /**
     * Mobile CI cookie, temporary fix.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @todo put it on config
     * @var string
     */
    protected $mobileCISessionName = [
        'query_string'  => 'orbit_mobile_session',
        'header'        => 'X-Orbit-Mobile-Session',
        'cookie'        => 'orbit_mobile_session'
    ];

    /**
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::postLogin
     * @return Response
     */
    public function postLogin()
    {
        $response = LoginAPIController::create('raw')->postLogin();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
        }

        return $this->render($response);
    }

    /**
     * Clear the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getLogout()
    {
        $from = isset($_GET['_orbit_logout_from']) === FALSE ? 'portal' : $_GET['_orbit_logout_from'];
        $validFrom = ['portal', 'mobile-ci', 'pos'];

        switch ($from) {
            case 'mobile-ci':
                $activity = Activity::mobileci()
                                    ->setActivityType('logout');
                break;

            case 'pos':
                $activity = Activity::pos()
                                    ->setActivityType('logout');
                break;

            case 'portal':
            default:
                $activity = Activity::portal()
                                    ->setActivityType('logout');
        }

        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');

            $userId = $this->session->read('user_id');

            if ($this->session->read('logged_in') !== TRUE || ! $userId) {
                throw new Exception ('Invalid session data.');
            }

            $user = User::excludeDeleted()->find($userId);

            if (! $user) {
                throw new Exception ('Session error: user not found.');
            }

            $this->session->destroy();
            $response->data = NULL;

            // Successfull logout
            $activity->setUser($user)
                     ->setActivityName('logout_ok')
                     ->setActivityNameLong('Sign out')
                     ->setModuleName('Application')
                     ->responseOK();
        } catch (Exception $e) {
            try {
                $this->session->destroy();
            } catch (Exception $e) {
            }

            $response->code = $e->getCode();
            $response->status = 'error';
            $response->message = $e->getMessage();

            $activity->setUser('guest')
                     ->setActivityName('logout_failed')
                     ->setActivityNameLong('Sign out Failed')
                     ->setNotes($e->getMessage())
                     ->setModuleName('Application')
                     ->responseFailed();
        }

        $activity->save();

        return $this->render($response);
    }

    /**
     * Check the session value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getSession()
    {
        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');

            if (Config::get('app.debug')) {
                $response->data = $this->session->getSession();
            } else {
                $response->data = 'Not in debug mode.';
            }
        } catch (Exception $e) {
            $response->code = $e->getCode();
            $response->status = 'error';
            $response->message = $e->getMessage();
        }

        return $this->render($response);
    }

    /**
     * Check and activate token.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::getRegisterTokenCheck
     * @return response
     */
    public function postRegisterTokenCheck()
    {
        $response = LoginAPIController::create('raw')->postRegisterTokenCheck();
        if ($response->code === 0)
        {
            $user = $response->data;

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
        }

        return $this->render($response);
    }

    /**
     * Get token list
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getTokenList()
    {
        return $this->render(TokenAPIController::create('raw')->getSearchToken());
    }

    /**
     * Mobile-CI Intermediate call by registering client mac address when login
     * succeed.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see MobileCIAPIController::postLoginInShop()
     * @return Response
     */
    public function postLoginMobileCI()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('login');

        $response = MobileCIAPIController::create('raw')->postLoginInShop();
        if ($response->code === 0)
        {
            // Register User Mac Address to the Router
            $registerMac = Firewall::create()->grantMacByIP($_SERVER['REMOTE_ADDR']);
            if (! $registerMac['status']) {
                $exitCode = 1;
                if (isset($registerMac['object'])) {
                    $exitCode = $registerMac['object']->getExitCode();
                }
                $response->message = $registerMac['message'];

                // Login Failed
                $activity->setUser('guest')
                         ->setActivityName('login_failed')
                         ->setActivityNameLong('Login failed - Fails to register mac address')
                         ->setNotes($response->message)
                         ->setModuleName('Application')
                         ->responseFailed();

                // Call logout to clear session
                MobileCIAPIController::create('raw')->getLogoutInShop();

                return $this->render($response);
            }

            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
            );
            $this->session->getSessionConfig()->setConfig('session_origin.header.name', $this->mobileCISessionName['header']);
            $this->session->getSessionConfig()->setConfig('session_origin.query_string.name', $this->mobileCISessionName['query_string']);
            $this->session->getSessionConfig()->setConfig('session_origin.cookie.name', $this->mobileCISessionName['cookie']);
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign In')
                     ->responseOK();
        } else {
            // Login Failed
            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Sign In Failed')
                     ->setModuleName('Application')
                     ->setNotes($response->message)
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render($response);
    }

    /**
     * Mobile-CI Intermediate call by revoking client mac address when logout
     * succeed.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see MobileCIAPIController::postLoginInShop()
     * @return Response
     */
    public function getLogoutMobileCI()
    {
        // This Query String trigger how activity would be logged
        $_GET['_orbit_logout_from'] = 'mobile-ci';

        $this->session->getSessionConfig()->setConfig('session_origin.header.name', $this->mobileCISessionName['header']);
        $this->session->getSessionConfig()->setConfig('session_origin.query_string.name', $this->mobileCISessionName['query_string']);
        $this->session->getSessionConfig()->setConfig('session_origin.cookie.name', $this->mobileCISessionName['cookie']);

        $response = json_decode($this->getLogout()->getContent());
        $cookie = Cookie::make('event', '', 365*5);
        try {
            if ($response->code !== 0) {
                throw new Exception ($response->message, $response->code);
            }

            // De-register User Mac Address to the Router
            $deRegisterMac = Firewall::create()->revokeMacByIP($_SERVER['REMOTE_ADDR']);
            if (! $deRegisterMac['status']) {
                $exitCode = 1;
                if (isset($deRegisterMac['object'])) {
                    $exitCode = $deRegisterMac['object']->getExitCode();
                }
                throw new Exception ($deRegisterMac['message'], $exitCode);
            }

            // Delete event popup cookie
            $cookie = Cookie::forget('event');

        } catch (Exception $e) {
        }

        // Redirect back to /customer
        return Redirect::to('/customer')->withCookie($cookie);
    }

    /**
     * Captive Portal related tricks.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function getCaptive()
    {
        /**
         * Handle the ?loadsession=SESSION_ID action. Basically it just showing
         * a loading page. The actual session creation on '?createsession'
         */
        if (isset($_GET['loadsession'])) {
            // Needed to show some information on the view
            try {
                $retailer_id = Config::get('orbit.shop.id');
                $retailer = Retailer::with('parent')->where('merchant_id', $retailer_id)->first();
            } catch (Exception $e) {
                $retailer = new stdClass();
                // Fake some properties
                $retailer->parent = new stdClass();
                $retailer->parent->logo = '';
            }

            // Display a view which showing that page is loading
            return View::make('mobile-ci/captive-loading', ['retailer' => $retailer]);
        }

        if (isset($_GET['createsession'])) {
            $cookieName = $this->mobileCISessionName['cookie'];
            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

            $sessionId = $_GET['createsession'];

            // Send cookie so our app have an idea about the session
            setcookie($cookieName, $sessionId, time() + $expireTime, '/', NULL, FALSE, TRUE);

            // Used for internal session object since sending cookie above
            // only affects on next request
            $_COOKIE[$cookieName] = $sessionId;

            $this->session->setSessionId($sessionId);
            $oldData = $this->session->getSession();

            $sessData = clone $oldData;
            $sessData->userAgent = $_SERVER['HTTP_USER_AGENT'];
            $this->session->rawUpdate($sessData);
            $newData = $this->session->getSession();

            return Redirect::to('/customer');
        }

        // Catch all
        $response = new ResponseProvider();
        return $this->render($response);
    }
}
