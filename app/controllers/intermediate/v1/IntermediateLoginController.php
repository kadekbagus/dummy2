<?php
/**
 * Intermediate Controller for handling user login
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Orbit\CloudMAC;
use OrbitShop\API\v1\ResponseProvider;
use MobileCI\MobileCIAPIController;
use Net\Security\Firewall;
use Orbit\Helper\Security\Encrypter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

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
     *
     * Calls: MobileCIAPIController getCloudLogin
     * Passed: GET[email, retailer_id, callback_url]
     *
     * Returns: redirect to callback with
     *   GET[status=, message=(if error), user_id=(if success), user_email=(if success)]
     *
     * @return RedirectResponse
     */
    public function getCloudLogin()
    {
        $callback_url = OrbitInput::get('callback_url', '');
        $email = OrbitInput::get('email', '');
        $retailer_id = OrbitInput::get('retailer_id', '');
        $payload = OrbitInput::get('payload', '');

        $mac = OrbitInput::get('mac', '');
        $timestamp = (int)OrbitInput::get('timestamp', 0);

        if (!CloudMAC::validateDataFromBox($mac, $timestamp, [
            'email' => $email,
            'retailer_id' => $retailer_id,
            'callback_url' => $callback_url,
            'payload' => $payload,
        ])) {
            return $this->displayValidationError();
        }

        $response = MobileCIAPIController::create('raw')->getCloudLogin();

        $params = ['status' => $response->status];
        if ($response->status === 'success') {
            $params['user_id'] = $response->data->user_id;
            $params['user_detail_id'] = $response->data->user_detail_id;
            $params['apikey_id'] = $response->data->apikey_id;
            $params['user_email'] = $response->data->user_email;
            $params['payload'] = $payload;
        } else {
            $params['message'] = $response->message;
        }
        $params = CloudMAC::wrapDataFromCloud($params);

        // we use this to assemble a normalized URL.
        $req = \Symfony\Component\HttpFoundation\Request::create($callback_url, 'GET', $params);
        return Redirect::away($req->getUri(), 302, $this->getCORSHeaders());
    }

    /**
     * Cloud login callback function.
     *
     * User is redirected to here by cloud after cloud determines user id for given email.
     *
     * Gets: GET[user_email, user_id, user_detail_id, apikey_id]
     *
     * This should insert the user and associated objects using the given email and ids.
     *
     * Returns [true, user_id, user_email] or [false, string_error]
     */
    private function internalCloudLoginCallback()
    {
        $email = OrbitInput::get('user_email', '');
        $user_id = OrbitInput::get('user_id', '');
        $user_detail_id = OrbitInput::get('user_detail_id', '');
        $apikey_id = OrbitInput::get('apikey_id', '');
        $payload = OrbitInput::get('payload', '');

        $mac = OrbitInput::get('mac', '');
        $timestamp = (int)OrbitInput::get('timestamp', 0);

        $status = OrbitInput::get('status', 'failed');
        if ($status !== 'success') {
            $message = OrbitInput::get('message');
            if (!CloudMAC::validateDataFromCloud($mac, $timestamp, [
                'status' => $status,
                'message' => $message,
            ])) {
                return [false, $this->displayValidationError()];
            }
            return [false, $this->displayError($message)];
        }

        // else success

        if (!CloudMAC::validateDataFromCloud($mac, $timestamp, [
            'status' => $status,
            'user_email' => $email,
            'user_id' => $user_id,
            'user_detail_id' => $user_detail_id,
            'apikey_id' => $apikey_id,
            'payload' => $payload,
        ])) {
            return [false, $this->displayValidationError()];
        }

        $user = NULL;
        DB::connection()->beginTransaction();
        /** @var LoginAPIController $login */
        $login = LoginAPIController::create('raw');
        $login->setUseTransaction(false);
        try {
            // try getting user again, if found do not insert, just use that.
            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where('role_name', 'Consumer');
                    }
                )->sharedLock()
                ->first();

            if (!isset($user)) {
                list($user, $userdetail, $apikey) = $login->createCustomerUser($email, $user_id, $user_detail_id, $apikey_id);
            }

            DB::connection()->commit();
            return [true, $user->user_id, $email];
        } catch (Exception $e) {
            DB::connection()->rollBack();
            throw $e; // TODO display error?
        }
    }

    /**
     * Cloud login callback function.
     *
     * User is redirected to here by cloud after cloud determines user id for given email.
     *
     * Common logic (validate parameters, insert if not found) in internalCloudLoginCallback
     *
     * After the user is inserted this returns the user ID as JSON.
     *
     */
    public function getCloudLoginCallbackShowId()
    {
        $callback_result = $this->internalCloudLoginCallback();
        if ($callback_result[0] === false) {
            // error, returns [false, string_error]
            // we return response
            $response = new ResponseProvider();
            $response->code = Status::UNKNOWN_ERROR;
            $response->status = 'error';
            $response->message = $callback_result[1];
            return $this->render($response);
        }
        // else ok return [true, user_id, user_email]
        $response = new ResponseProvider();
        $response->code = 0;
        $response->status = 'ok';
        $response->data = ['user_id' => $callback_result[1]];
        return $this->render($response);
    }

    /**
     * Cloud login callback function.
     *
     * User is redirected to here by cloud after cloud determines user id for given email.
     *
     * Common logic (validate parameters, insert if not found) in internalCloudLoginCallback
     *
     * After the user is inserted this performs the same logic as the regular mobile CI POST login, then directly
     * redirects to the landing url.
     *
     */
    public function getCloudLoginCallback()
    {
        $callback_result = $this->internalCloudLoginCallback();
        if ($callback_result[0] === false) {
            // error, returns [false, string_error]
            // we return the error as is
            return $callback_result[1];
        }
        // else ok return [true, user_id, user_email]
        $email = $callback_result[2];

        // do the usual login stuff
        $_POST['email'] = $email;
        $this->postLoginMobileCI(); // sets cookies & inserts activity - we ignore the JSON result

        /** @var \MobileCI\MobileCIAPIController $mobile_ci */
        $mobile_ci = MobileCIAPIController::create('raw');

        // hack: we get the landing URL from the sign in view's data so we don't duplicate logic.
        $view = $mobile_ci->getSignInView();
        $view_data = $view->getData();

        return Redirect::away($view_data['landing_url']);
    }

    private function displayValidationError()
    {
        return "Validation error occurred"; // TODO
    }

    private function displayError($message)
    {
        return $message; // TODO
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

            if ($user->role->role_name === 'Consumer') {
                // For login page
                $expireTime = time() + 3600 * 24 * 365 * 5;

                setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', NULL, FALSE, FALSE);
                setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', NULL, FALSE, FALSE);
            }

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

    /**
    * Process payload in $_POST[payload] or $_GET[payload].
    *
    * @param $activity Activity|null log in activity so we can change the activity name
    * @param $registration_activity_id string|null ID of registration activity if present so we can change the activity name
    */
    public static function proceedPayload($activity, $registration_activity_id = null)
    {
        // The sign-in view put the payload from query string to post body on AJAX call
        if (isset($_POST['payload'])) {
            $payload = $_POST['payload'];
        } elseif (isset($_GET['payload'])) {
            // possibly from cloud login callback
            $payload = $_GET['payload'];
        } else {
            return;
        }

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
                    $activityNameLong = 'Sign In via Facebook';
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
            if (isset($activity)) {
                $activity->setActivityNameLong($activityNameLong);
            }
        }

        // this is passed up from LoginAPIController::postRegisterUserInShop, to MobileCIAPIController::postLoginInShop
        // to here, so if this login automatically registered the user, we can update this based on where
        // the registration is coming from.
        if (isset($registration_activity_id) && isset($customer)) {
            $registration_activity = Activity::where('activity_id', '=', $registration_activity_id)
                ->where('activity_name', '=', 'registration_ok')
                ->where('user_id', '=', $customer->user_id)
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
                $retailer = Mall::with('settings', 'parent')->where('merchant_id', $retailer_id)->first();

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
