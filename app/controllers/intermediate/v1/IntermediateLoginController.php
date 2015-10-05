<?php
/**
 * Intermediate Controller for handling user login
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ResponseProvider;
use MobileCI\MobileCIAPIController;
use Net\Security\Firewall;
use Orbit\Helper\Security\Encrypter;
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
        'query_string'  => 'orbit_session',
        'header'        => 'X-Orbit-Session',
        'cookie'        => 'orbit_sessionx'
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
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::postLoginAdmin
     * @return Response
     */
    public function postLoginAdmin()
    {
        $response = LoginAPIController::create('raw')->postLoginAdmin();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name
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
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::postLoginMall
     * @return Response
     */
    public function postLoginMall()
    {
        $response = LoginAPIController::create('raw')->postLoginMall();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name
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
     * @author Rio Astamal <me@rioastamal.net>
     * @param @see LoginAPIController::postLoginMall
     * @return Response
     */
    public function postLoginMallCustomerService()
    {
        $response = LoginAPIController::create('raw')->postLoginMallCustomerService();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name
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
     * @param @see LoginAPIController::postLoginCustomer
     * @return Response
     */
    public function postLoginCustomer()
    {
        $response = LoginAPIController::create('raw')->postLoginCustomer();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('user_password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name
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

            /**
             * The orbit mall does not have other application which reside at the same domain.
             * So we can safely use standard session name 'orbit_sessionx' for cookie.
             */
            $this->session->getSessionConfig()->setConfig('session_origin.header.name', $this->mobileCISessionName['header']);
            $this->session->getSessionConfig()->setConfig('session_origin.query_string.name', $this->mobileCISessionName['query_string']);
            $this->session->getSessionConfig()->setConfig('session_origin.cookie.name', $this->mobileCISessionName['cookie']);
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            // For login page
            $expireTime = time() + 3600 * 24 * 365 * 5;
            setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', NULL, FALSE, FALSE);
            setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', NULL, FALSE, FALSE);

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign In')
                     ->responseOK();

            static::proceedPayload($activity, $user->registration_activity_id);
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

        if (isset($_GET['not_me'])) {
            setcookie('orbit_email', 'foo', time() - 3600, '/');
            setcookie('orbit_firstname', 'foo', time() - 3600, '/');
        }

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

    public static function proceedPayload($activity, $registration_activity_id = null)
    {
        // The sign-in view put the payload from query string to post body on AJAX call
        if (! isset($_POST['payload'])) {
            return;
        }

        $payload = $_POST['payload'];
        Log::info('[PAYLOAD] Payload found -- ' . serialize($payload));

        // Decrypt the payload
        $key = md5('--orbit-mall--');
        $payload = (new Encrypter($key))->decrypt($payload);
        Log::info('[PAYLOAD] Payload decrypted -- ' . serialize($payload));

        // The data is in url encoded
        parse_str($payload, $data);

        Log::info('[PAYLOAD] Payload extracted -- ' . serialize($data));

        // email, fname, lname, gender, mac, ip, login_from
        $email = isset($data['email']) ? $data['email'] : '';
        $fname = isset($data['fname']) ? $data['fname'] : '';
        $lname = isset($data['lname']) ? $data['lname'] : '';
        $gender = isset($data['gender']) ? $data['gender'] : '';
        $mac = isset($data['mac']) ? $data['mac'] : '';
        $ip = isset($data['ip']) ? $data['ip'] : '';
        $from = isset($data['login_from']) ? $data['login_from'] : '';
        $captive = isset($data['is_captive']) ? $data['is_captive'] : '';
        $recognized = isset($data['recognized']) ? $data['recognized'] : '';

        if (! $email) {
            Log::error('Email from payload is not valid or empty.');

            return;
        }

        // Try to get the email to update user data
        $customer = User::consumers()->excludeDeleted()->where('user_email', $email)->first();
        if (is_object($customer)) {
            // Update first name if necessary
            if (empty($customer->user_firstname)) {
                $customer->user_firstname = $fname;
            }

            // Update last name if necessary
            if (empty($customer->user_lastname)) {
                $customer->user_lastname = $lname;
            }

            // Update gender if necessary
            $gender = strtolower($gender);
            $male = ['male', 'm', 'men', 'man'];
            if (in_array($gender, $male)) {
                $customer->userdetail->gender = 'm';
            }

            $female = ['female', 'f', 'women', 'woman'];
            if (in_array($gender, $female)) {
                $customer->userdetail->gender = 'f';
            }

            if ($from === 'facebook' && $customer->status === 'pending') {
                // Only set if the previous status is pending
                $customer->status = 'active';   // make it active
            }

            $customer->save();
            $customer->userdetail->save();

            Log::info('[PAYLOAD] Consumer data saved -- ' . serialize($customer));
        }

        // Try to update the mac address table
        if (! empty($mac)) {
            $macModel = MacAddress::excludeDeleted()
                                  ->where('mac_address', $mac)
                                  ->where('user_email', $email)
                                  ->orderBy('created_at', 'desc')
                                  ->first();

            if (! is_object($macModel)) {
                $macModel = new MacAddress();
                $macModel->mac_address = $mac;
                $macModel->user_email = $email;
                $macModel->status = 'active';
            }

            $macModel->ip_address = $ip;
            $macModel->save();

            Log::info('[PAYLOAD] Mac saved -- ' . serialize($macModel));
        }

        // Try to update the activity
        if ($captive === 'yes') {
            switch ($from) {
                case 'facebook':
                    $activityNameLong = 'Sign In via Facebook (Captive)';
                    break;

                case 'form':
                    $activityNameLong = 'Sign In via Email (Captive)';
                    break;

                default:
                    $activityNameLong = 'Sign In';
            }

            switch ($recognized) {
                case 'auto_mac':
                    $activityNameLong = 'Sign In via Automatic MAC Recognition (Captive)';
                    break;

                case 'auto_email':
                    $activityNameLong = 'Sign In via Automatic Email Recognition (Captive)';
                    break;

                default:
                    // do nothing
            }

            $activity->setActivityNameLong($activityNameLong);
        }

        // this is passed up from LoginAPIController::postRegisterUserInShop, to MobileCIAPIController::postLoginInShop
        // to here, so if this login automatically registered the user, we can update this based on where
        // the registration is coming from.
        if (isset($registration_activity_id)) {
            $registration_activity = Activity::where('activity_id', '=', $registration_activity_id)
                ->where('activity_name', '=', 'registration_ok')
                ->where('user_id', '=', $activity->user_id)
                ->first();
            if (isset($registration_activity)) {
                if (isset($from)) {
                    if ($from === 'facebook') {
                        $registration_activity->activity_name_long = 'Facebook Sign Up';
                        $registration_activity->save();
                    } else if ($from === 'form') {
                        $registration_activity->activity_name_long = 'Email Sign Up';
                        $registration_activity->save();
                    }
                }
            }
        }
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
            $bg = NULL;

            try {
                $retailer_id = Config::get('orbit.shop.id');
                $retailer = Retailer::with('settings', 'parent')->where('merchant_id', $retailer_id)->first();

                try {
                    $bg = Setting::getFromList($retailer->settings, 'background_image');
                } catch (Exception $e) {
                }
            } catch (Exception $e) {
                $retailer = new stdClass();
                // Fake some properties
                $retailer->parent = new stdClass();
                $retailer->parent->logo = '';
                $retailer->parent->biglogo = '';
            }

            $display_name = Input::get('fname', '');

            if (empty($display_name)) {
                $display_name = Input::get('email', '');
            }

            // Display a view which showing that page is loading
            return View::make('mobile-ci/captive-loading', ['retailer' => $retailer, 'bg' => $bg, 'display_name' => $display_name]);
        }

        if (isset($_GET['createsession'])) {
            $cookieName = $this->mobileCISessionName['cookie'];
            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

            $sessionId = $_GET['createsession'];

            // Send cookie so our app have an idea about the session
            setcookie($cookieName, $sessionId, time() + $expireTime, '/', NULL, FALSE, FALSE);

            // Used for internal session object since sending cookie above
            // only affects on next request
            $_COOKIE[$cookieName] = $sessionId;

            $this->session->setSessionId($sessionId);
            $oldData = $this->session->getSession();

            $sessData = clone $oldData;
            $sessData->userAgent = $_SERVER['HTTP_USER_AGENT'];
            $this->session->rawUpdate($sessData);
            $newData = $this->session->getSession();

            $sessionName = $this->mobileCISessionName['query_string'];

            $userId = $this->session->read('user_id');
            $user = User::excludeDeleted()->find($userId);

            $key = md5('--orbit-mall--');
            $query = [
                'time' => time(),
                'email' => $user->email,
                'fname' => $user->user_firstname
            ];

            $payload = (new Encrypter($key))->encrypt(http_build_query($query));
            $redirectTo = sprintf('/customer?%s=%s&payload_login=%s', $sessionName, $sessionId, $payload);

            setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', NULL, FALSE, FALSE);
            setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', NULL, FALSE, FALSE);

            return Redirect::to($redirectTo);
        }

        // Catch all
        $response = new ResponseProvider();
        return $this->render($response);
    }
}
