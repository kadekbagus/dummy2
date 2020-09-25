<?php
/**
 * Intermediate Controller for handling user login
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Orbit\CloudMAC;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ResponseProvider;
use MobileCI\MobileCIAPIController;
use Net\Security\Firewall;
use Orbit\Helper\Security\Encrypter;
use Orbit\Helper\Net\Domain;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Orbit\Helper\Net\GuestUserGenerator;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\AppOriginProcessor;
use Carbon\Carbon;
use Orbit\Helper\Util\UserAgent;

class IntermediateLoginController extends IntermediateBaseController
{
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
     * @author Irianto <irianto@dominopos.com>
     * @param @see LoginAPIController::postLoginPMP
     * @return Response
     */
    public function postLoginPMP()
    {
        $response = LoginAPIController::create('raw')->postLoginPMP();
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
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param @see LoginAPIController::postLoginMDM
     * @return Response
     */
    public function postLoginMDM()
    {
        $response = LoginAPIController::create('raw')->postLoginMDM();
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
     * Login for Merchant Transactions Portal (MTP)
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param @see LoginAPIController::postLoginMTP
     * @return Response
     */
    public function postLoginMTP()
    {
        $response = LoginAPIController::create('raw')->postLoginMTP();
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
     * Login for Rating and Review Portal (RRP)
     * @author kadek <kadek@dominopos.com>
     * @param @see LoginAPIController::postLoginRRP
     * @return Response
     */
    public function postLoginRRP()
    {
        $response = LoginAPIController::create('raw')->postLoginRRP();
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
     * Login for Admin Manager Poertal (AMP)
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param @see LoginAPIController::postLoginAMP
     * @return Response
     */
    public function postLoginAMP()
    {
        $response = LoginAPIController::create('raw')->postLoginAMP();
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
     * Login for Rating and Review Portal (RRP)
     * @author kadek <kadek@dominopos.com>
     * @param @see LoginAPIController::postLoginRRP
     * @return Response
     */
    public function postLoginPP()
    {
        $response = LoginAPIController::create('raw')->postLoginPP();
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
     * Login for Report Generator Portal (RGP)
     * @author kadek <kadek@dominopos.com>
     * @param @see LoginAPIController::postLoginRGP
     * @return Response
     */
    public function postLoginRGP()
    {
        $response = LoginAPIController::create('raw')->postLoginRGP();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('password', 'apikey'));
            // Auth::login($user);

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->rgp_user_id,
                'email'     => $user->email,
                'username'  => $user->username
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
     * Login for Brand Product Portal (BPP)
     * @author kadek <kadek@dominopos.com>
     * @param @see LoginAPIController::postLoginBPP
     * @return Response
     */
    public function postLoginBPP()
    {
        $response = LoginAPIController::create('raw')->postLoginBPP();
        if ($response->code === 0)
        {
            $user = $response->data;
            $user->setHidden(array('password', 'apikey'));

            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->bpp_user_id,
                'email'     => $user->email,
                'username'  => $user->name
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
        // this additional code is for checking url of cs portal to match the cs user on the correct mall
        // for bug fix OM-685 Takashimaya:Employee Setup(Role:CS) from another mall can login to My CS Portal

            $csUrl = trim(OrbitInput::post('url'));
            $email = trim(OrbitInput::post('email'));

            $searchUrl = array('http://cs.', 'https://cs.', 'http://cs-', 'https://cs-');
            $replaceUrl = array('dom:', 'dom:', 'dom:', 'dom:');
            $seetingUrl = str_replace($searchUrl, $replaceUrl, $csUrl);
            $seetingUrl = preg_replace('{/$}', '', $seetingUrl);

            $setting = Setting::where('setting_name', '=', $seetingUrl)->first();

            if (is_object($setting)) {
                $mallId = $setting->setting_value;
            } else {
                $mallId = 0;
            }

        if (trim($email) === '') {
            $response = new stdclass();
            $response->code = 14;
            $response->status = 'error';
            $response->message = Lang::get('validation.required', array('attribute' => 'email'));
            $response->data = null;
        } else {
            $user = User::excludeDeleted('users')
                      ->leftJoin('employees','employees.user_id', '=', 'users.user_id')
                      ->leftJoin('employee_retailer','employee_retailer.employee_id','=','employees.employee_id')
                      ->where('user_email', '=', $email)
                      ->where('employee_retailer.retailer_id', '=', $mallId)
                      ->first();

            if (is_object($user) || $user != null) {
                $response = LoginAPIController::create('raw')->postLoginMallCustomerService();
            } else {
                $response = new stdclass();
                $response->code = 13;
                $response->status = 'error';
                $response->message = Lang::get('validation.orbit.access.loginfailed');
                $response->data = null;
            }

        }

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
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function getCloudLogin()
    {
        $callback_url = OrbitInput::get('callback_url', '');
        $email = OrbitInput::get('email', '');
        $password = OrbitInput::get('password', '');
        $retailer_id = OrbitInput::get('retailer_id', '');
        $payload = OrbitInput::get('payload', '');
        $from = OrbitInput::get('from', '');
        $full_data = OrbitInput::get('full_data', '');
        $check_only = OrbitInput::get('check_only', '');
        $auto_login = OrbitInput::get('auto_login', 'no');
        $from_captive = OrbitInput::get('from_captive', 'no');
        $socmed_redirect_to = OrbitInput::get('socmed_redirect_to', '');

        $mac = OrbitInput::get('mac', '');
        $timestamp = (int)OrbitInput::get('timestamp', 0);

        if ($from !== 'cs' && !CloudMAC::validateDataFromBox($mac, $timestamp, [
            'email' => $email,
            'password' => $password,
            'retailer_id' => $retailer_id,
            'callback_url' => $callback_url,
            'payload' => $payload,
            'from' => $from,
            'full_data' => $full_data,
            'check_only' => $check_only,
            'auto_login' => $auto_login,
            'from_captive' => $from_captive,
            'socmed_redirect_to' => $socmed_redirect_to
        ])) {
            return $this->displayValidationError();
        }

        $full_data = ($full_data === 'yes');
        $check_only = ($check_only === 'yes');

        /** @var MobileCIAPIController $controllerAPI */
        $controllerAPI = MobileCIAPIController::create('raw');
        $response = $controllerAPI->getCloudLogin(!$full_data, !$check_only);

        $params = ['status' => $response->status];
        if ($response->status === 'success') {
            if (isset($response->data->user_id)) {
                $params['user_id'] = $response->data->user_id;
                $params['user_status'] = $response->data->user_status;
                $params['user_detail_id'] = $response->data->user_detail_id;
                $params['apikey_id'] = $response->data->apikey_id;
                $params['user_email'] = $response->data->user_email;
                $params['payload'] = $payload;
                $params['user_acquisition_id'] = $response->data->user_acquisition_id;
                $params['socmed_redirect_to'] = $socmed_redirect_to;
            } else {
                $params['user_id'] = '';
                $params['user_status'] = '';
                $params['user_detail_id'] = '';
                $params['apikey_id'] = '';
                $params['user_email'] = '';
                $params['payload'] = '';
                $params['user_acquisition_id'] = '';
                $params['socmed_redirect_to'] = '';
            }

            $params['auto_login'] = $auto_login;
            $params['from_captive'] = $from_captive;
        } else {
            $params['message'] = $response->message;
        }
        if ($full_data) {
            $response = new stdclass();
            $response->code = 0;
            $response->status = $params['status'];
            $response->message = '';
            if ($params['status'] === 'success') {
                $params['user'] = '';
                $params['user_detail'] = '';
                if ($params['user_id'] !== '') {
                    // technically this will also serialize any *loaded* relation, but we are
                    // loading the entity from the ID without loading any relations.
                    $u = \User::find($params['user_id']);
                    if (isset($u)) {
                        $params['user'] = $u->toJson();
                    }
                    $ud = \UserDetail::find($params['user_detail_id']);
                    if (isset($ud)) {
                        $params['user_detail'] = $ud->toJson();
                    }
                    // api key does not need syncing as it is one way only (cloud -> box) plus it contains
                    // secret data so...
                    // user personal interest is always reloaded as it should not conflict (???)
                }
            }
            $params = CloudMAC::wrapDataFromCloud($params);
            $response->data = $params;
            if ($check_only) {
                if ($params['user_id'] != '') {
                    // this is so that the frontend can display this (translated) error message
                    $response->message = Lang::get('validation.orbit.email.exists');
                }
            }
            return $this->render($response);
        } else {
            // we use this to assemble a normalized URL.
            $params = CloudMAC::wrapDataFromCloud($params);
            $req = \Symfony\Component\HttpFoundation\Request::create($callback_url, 'GET', $params);
            return Redirect::away($req->getUri(), 302, $this->getCORSHeaders());
        }
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
        $user_acquisition_id = OrbitInput::get('user_acquisition_id', '');
        $user_status = OrbitInput::get('user_status', '');
        $auto_login = OrbitInput::get('auto_login', 'no');
        $from_captive = OrbitInput::get('from_captive', 'no');
        $socmed_redirect_to = OrbitInput::get('socmed_redirect_to', '');

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
            'user_status' => $user_status,
            'user_id' => $user_id,
            'user_detail_id' => $user_detail_id,
            'apikey_id' => $apikey_id,
            'payload' => $payload,
            'user_acquisition_id' => $user_acquisition_id,
            'auto_login' => $auto_login,
            'from_captive' => $from_captive,
            'socmed_redirect_to' => $socmed_redirect_to
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
                        // guest not included here because guest logins should be seeded in initial sync
                        // and there should be no need to go to cloud for guest login
                    }
                )->sharedLock()
                ->first();

            if (!isset($user)) {
                list($user, $userdetail, $apikey) = $login->createCustomerUser($email, $user_id, $user_detail_id, $apikey_id, $user_status);
            }

            $acq = UserAcquisition::where('user_acquisition_id', $user_acquisition_id)
                ->lockForUpdate()
                ->first();
            if (!isset($acq)) {
                $acq = new \UserAcquisition();
                $acq->user_acquisition_id = $user_acquisition_id;
                $acq->user_id = $user->user_id;
                $acq->acquirer_id = Config::get('orbit.shop.id');
                $acq->save();
            }

            DB::connection()->commit();
            return [true, $user->user_id, $email, $socmed_redirect_to];
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
        $socmed_redirect_to = $callback_result[3];
        // do the usual login stuff
        $_POST['email'] = $email;
        $_POST['socmed_redirect_to'] = $socmed_redirect_to;

        $this->postLoginMobileCI(); // sets cookies & inserts activity - we ignore the JSON result
        $proceed = OrbitInput::get('from_captive', 'no') === 'yes' && OrbitInput::get('auto_login', 'yes');
        if ($proceed) {
            $sid = $this->session->getSessionId();
            $url = URL::Route("captive-portal") . sprintf('&loadsession=%s&fname=&email=%s', $sid, $email);

            return Redirect::to($url);
        }

        /** @var \MobileCI\MobileCIAPIController $mobile_ci */
        $mobile_ci = MobileCIAPIController::create('raw');

        // hack: we get the landing URL from the sign in view's data so we don't duplicate logic.
        $user = User::excludeDeleted()->where('user_email', $email)->first();
        $retailer_id = Config::get('orbit.shop.id');
        $retailer = Mall::with('settings', 'parent')->where('merchant_id', $retailer_id)->first();

        $doLogin = $mobile_ci->loginStage2($user, $retailer);
        $view = $mobile_ci->getSignInView();
        $view_data = $view->getData();

        return Redirect::away($view_data['landing_url']);
    }

    /**
     * This accepts the "full data" returned by IntermediateLoginController::getCloudLogin as a POST
     * and inserts the corresponding items.
     *
     */
    public function postAcceptCloudLoginFullData()
    {
        $email = OrbitInput::post('user_email', '');
        $user_id = OrbitInput::post('user_id', '');
        $user_detail_id = OrbitInput::post('user_detail_id', '');
        $apikey_id = OrbitInput::post('apikey_id', '');
        $payload = OrbitInput::post('payload', '');
        $user_acquisition_id = OrbitInput::post('user_acquisition_id', '');
        $user_status = OrbitInput::post('user_status', '');
        /** @var string $user */
        $user = OrbitInput::post('user');
        $user_detail = OrbitInput::post('user_detail');

        $mac = OrbitInput::post('mac', '');
        $timestamp = (int)OrbitInput::post('timestamp', 0);

        $auto_login = OrbitInput::get('auto_login', 'no');
        $from_captive = OrbitInput::get('from_captive', 'no');

        $socmed_redirect_to = OrbitInput::get('socmed_redirect_to', '');

        $status = OrbitInput::post('status', 'failed');
        if ($status !== 'success') {
            $message = OrbitInput::post('message');
            if (!CloudMAC::validateDataFromCloud($mac, $timestamp, [
                'status' => $status,
                'message' => $message,
            ])) {
                return $this->displayValidationError(true);
            }
            return $this->displayError($message, true);
        }


        // else success

        if (!CloudMAC::validateDataFromCloud($mac, $timestamp, [
            'status' => $status,
            'user_email' => $email,
            'user_status' => $user_status,
            'user_id' => $user_id,
            'user_detail_id' => $user_detail_id,
            'apikey_id' => $apikey_id,
            'payload' => $payload,
            'user_acquisition_id' => $user_acquisition_id,
            'user' => $user,
            'user_detail' => $user_detail,
            'auto_login' => $auto_login,
            'from_captive' => $from_captive,
            'socmed_redirect_to' => $socmed_redirect_to
        ])) {
            return $this->displayValidationError(true);
        }

        $user_entity = NULL;
        DB::connection()->beginTransaction();
        /** @var LoginAPIController $login */
        $login = LoginAPIController::create('raw');
        $login->setUseTransaction(false);
        try {
            // try getting user again, if found do not insert, just use that.
            $user_entity = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->where('role_name', 'Consumer');
                        // guest not included here because guest logins should be seeded in initial sync
                        // and there should be no need to go to cloud for guest login
                    }
                )->sharedLock()
                ->first();

            if (!isset($user_entity)) {
                $user_entity = new User();
                $user_fields = json_decode($user, true);
                foreach ($user_fields as $k => $v) {
                    $user_entity->$k = $v;
                }
                $user_entity->save();
                $user_detail_entity = new UserDetail();
                $user_detail_fields = json_decode($user_detail, true);
                foreach ($user_detail_fields as $k => $v) {
                    $user_detail_entity->$k = $v;
                }
                $user_detail_entity->save();
                $apikey = $user_entity->createApiKey($apikey_id);
            }

            $acq = UserAcquisition::where('user_acquisition_id', $user_acquisition_id)
                ->lockForUpdate()
                ->first();
            if (!isset($acq)) {
                $acq = new \UserAcquisition();
                $acq->user_acquisition_id = $user_acquisition_id;
                $acq->user_id = $user_entity->user_id;
                $acq->acquirer_id = Config::get('orbit.shop.id');
                $acq->save();
            }

            DB::connection()->commit();

            $response = new stdClass();
            $response->code = Status::OK;
            $response->status = 'error';
            $response->message = Status::OK_MSG;
            $response->data = ['user_id' => $user_entity->user_id];
            return $this->render($response);

        } catch (Exception $e) {
            DB::connection()->rollBack();
            throw $e; // TODO display error?
        }

    }

    private function displayValidationError($json = false)
    {
        if ($json) {
            $response = new stdClass();
            $response->code = Status::UNKNOWN_ERROR;
            $response->status = 'error';
            $response->message = 'Validation error occurred';
            $response->data = null;
            return $this->render($response);
        } else {
            return 'Validation error occurred'; // TODO
        }
    }

    private function displayError($message, $json = false)
    {
        if ($json) {
            $response = new stdClass();
            $response->code = Status::UNKNOWN_ERROR;
            $response->status = 'error';
            $response->message = $message;
            $response->data = null;
            return $this->render($response);
        } else {
            return $message; // TODO
        }
    }

    /**
     * Clear the session
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * @return Response
     */
    public function getLogout()
    {
        $from = isset($_GET['_orbit_logout_from']) === FALSE ? 'portal' : $_GET['_orbit_logout_from'];
        $location_id = isset($_GET['mall_id']) ? $_GET['mall_id'] : NULL;
        $validFrom = ['portal', 'mobile-ci', 'pos'];

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
        unset($_COOKIE['login_from']);
        unset($_COOKIE['orbit_email']);
        unset($_COOKIE['orbit_firstname']);
        setcookie('orbit_email', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

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
                OrbitShopAPI::throwInvalidArgument('Invalid session data.');
            }

            $user = User::excludeDeleted()->find($userId);

            if (! $user) {
                OrbitShopAPI::throwInvalidArgument('Session error: user not found.');
            }

            $response->data = NULL;

            // Store session id only from mobile-ci login & logout
            if($from == 'mobile-ci'){
                $activity->setSessionId($this->session->getSessionId());
                // just to make sure the guest user is exist in user table
                $guestUser = User::with('role')->where('user_id', $this->session->read('guest_user_id'))->first();
                if (is_object($guestUser)) {
                    $this->session->remove('user_id');
                    $this->session->remove('email');
                    $this->session->remove('facebooksdk.state');
                    $this->session->remove('visited_location');
                    $this->session->remove('coupon_location');
                    $this->session->remove('login_from');
                    $sessionData = $this->session->read(NULL);
                    $sessionData['fullname'] = '';
                    $sessionData['role'] = $guestUser->role->role_name;
                    $sessionData['status'] = $guestUser->status;

                    $this->session->update($sessionData);
                } else {
                    $this->session->destroy();
                }
            } else {
                $this->session->destroy();
            }

            // Successfull logout
            $activity->setUser($user)
                     ->setActivityName('logout_ok')
                     ->setActivityNameLong('Sign Out')
                     ->setModuleName('Application')
                     ->responseOK();
            if (! is_null($location_id)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', $location_id)->first();

                if (is_object($mall)) {
                    $activity->setLocation($mall);
                }
            }
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
                     ->setActivityNameLong('Sign Out Failed')
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
            $fallbackUARules = ['browser' => [], 'platform' => [], 'device_model' => [], 'bot_crawler' => []];
            $detectUA = new UserAgent();
            $detectUA->setRules(Config::get('orbit.user_agent_rules', $fallbackUARules));
            $detectUA->setUserAgent($this->getUserAgent());

            if (! $detectUA->isBotCrawler()) {
                $this->session->start(array(), 'no-session-creation');
                if (empty($this->session->read('guest_user_id'))) {
                    $guestConfig = [
                        'session' => $this->session
                    ];
                    $guest = GuestUserGenerator::create($guestConfig)->generate();

                    $sessionData = $this->session->read(NULL);
                    $sessionData['logged_in'] = TRUE;
                    $sessionData['guest_user_id'] = $guest->user_id;
                    $sessionData['guest_email'] = $guest->user_email;
                    $sessionData['role'] = strtolower($this->session->read('role')) === 'consumer' ? $this->session->read('role') : $guest->role->role_name;
                    $sessionData['fullname'] = ! empty($this->session->read('fullname')) ? $this->session->read('fullname') : '';
                    $sessionData['status'] = $guest->status;

                    $this->session->update($sessionData);
                }

                $response->data = $this->session->getSession();

                if (! isset($response->data->value['status']) || empty($response->data->value['status'])) {
                    $response->data->value['status'] = null;
                    if (strtolower($response->data->value['role']) === 'guest') {
                        $response->data->value['status'] = 'pending';
                    } elseif (strtolower($response->data->value['role']) === 'consumer') {
                        $user = User::excludeDeleted()
                            ->where('user_id', $response->data->value['user_id'])
                            ->first();

                        if (is_object($user)) {
                            $response->data->value['status'] = $user->status;
                        }
                    }

                    $sessionData = $this->session->read(NULL);
                    $sessionData['status'] = $response->data->value['status'];
                    $this->session->update($sessionData);
                }

                // request to update the status in session data
                OrbitInput::get('request_update', function($req) use($response) {
                    if ($req === 'yes') {
                        // this should be run if only the user is consumer and the status is pending
                        if (strtolower($response->data->value['role']) === 'consumer') {
                            if (isset($response->data->value['status']) && $response->data->value['status'] === 'pending') {
                                $user = User::excludeDeleted()
                                    ->where('user_id', $response->data->value['user_id'])
                                    ->first();

                                if (is_object($user)) {
                                    $response->data->value['status'] = $user->status;

                                    $sessionData = $this->session->read(NULL);
                                    $sessionData['status'] = $response->data->value['status'];
                                    $this->session->update($sessionData);
                                }
                            }
                        }
                    }
                });
            } else {
                $botSession = DB::table('sessions')
                    ->where('session_id', 'bot_session_id_haha')
                    ->first();

                if (! is_object($botSession)) {
                    throw new Exception('Bot User session is not available', 1);
                }

                $sessionData = unserialize($botSession->session_data);

                if (empty($sessionData->value)) {
                    throw new Exception('Bot User session data is empty', 1);
                }

                // set the session strict to FALSE
                Config::set('orbit.session.strict', FALSE);

                // Return mall_portal, cs_portal, pmp_portal etc
                $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                               ->getAppName();

                // Session Config
                $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
                $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

                // Instantiate the OrbitSession object
                $config = new SessionConfig(Config::get('orbit.session'));
                $config->setConfig('session_origin', $orbitSessionConfig);
                $config->setConfig('expire', $orbitSessionConfig['expire']);
                $config->setConfig('application_id', $applicationId);

                $this->session = new OrbitSession($config);
                $this->session->setSessionId($botSession->session_id);
                $this->session->disableForceNew();
                $this->session->start($sessionData->value, 'no-session-creation');

                $response->data = $this->session;
            }

            unset($response->data->userAgent);
            unset($response->data->ipAddress);
        } catch (Exception $e) {
            $request_for_guest = OrbitInput::get('desktop_ci', NULL);

            if (! empty($request_for_guest)) {
                if (empty($this->session->getSessionId())) {
                    // Start the orbit session
                    $this->session = SessionPreparer::prepareSession();
                }
                $guestConfig = [
                    'session' => $this->session
                ];
                $guest = GuestUserGenerator::create($guestConfig)->generate();

                $sessionData = $this->session->read(NULL);
                $sessionData['logged_in'] = TRUE;
                $sessionData['guest_user_id'] = $guest->user_id;
                $sessionData['guest_email'] = $guest->user_email;
                $sessionData['role'] = $guest->role->role_name;
                $sessionData['fullname'] = '';
                $sessionData['status'] = $guest->status;

                $this->session->update($sessionData);
                $response->data = $this->session->getSession();
                unset($response->data->userAgent);
                unset($response->data->ipAddress);
            } else {
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();
            }
        }

        return $this->render($response);
    }

    /**
     * Check the session login status.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getSessionLoginInfo()
    {
        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');

            $userId = $this->session->read('user_id');

            if ($this->session->read('logged_in') !== true || ! $userId) {
                throw new Exception('User not logged in', OrbitSession::ERR_UNKNOWN);
            }

            $response->data = sprintf('User id %s logged in', $userId);
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
     * Mobile-CI Intermediate check email exist in sign in mobile CI
     * succeed.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `email`             (optional)
     * @return Illuminate\Support\Facades\Response
     */
    public function checkEmailSignUp()
    {
        $email = OrbitInput::post('email');
        $validator = Validator::make(
            array(
                'email' => $email,
            ),
            array(
                'email' => 'required',
            )
        );

        $users = User::select('users.user_email', 'users.user_firstname', 'users.user_lastname', 'users.user_lastname', 'users.user_id', 'user_details.birthdate', 'user_details.gender', 'users.status')
                ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                ->where('users.user_email', $email)
                ->whereHas('role', function($q) {
                    $q->where('role_name', 'Consumer');
                })
                ->get();

        return $users;
    }

    /**
     * Mobile-CI Intermediate call by registering client mac address when login
     * succeed.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
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
                         ->responseFailed()
                         ->save();

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
                'location_id' => Config::get('orbit.shop.id'),
                'role'      => $user->role->role_name,
                'fullname'  => $user->getFullName(),
            );

            /**
             * The orbit mall does not have other application which reside at the same domain.
             * So we can safely use standard session name 'orbit_sessionx' for cookie.
             */

            // Return mall_portal, cs_portal, pmp_portal etc
            $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                           ->getAppName();

            // Session Config
            $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
            $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

            $this->session->getSessionConfig()->setConfig('session_origin.header.name', $orbitSessionConfig['header']);
            $this->session->getSessionConfig()->setConfig('session_origin.query_string.name', $orbitSessionConfig['query_string']);
            $this->session->getSessionConfig()->setConfig('session_origin.cookie.name', $orbitSessionConfig['cookie']);
            $this->session->getSessionConfig()->setConfig('expire', $orbitSessionConfig['expire']);
            $this->session->getSessionConfig()->setConfig('application_id', $applicationId);
            $this->session->enableForceNew()->start($data);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
            $login_from_cookie = isset($_COOKIE['login_from']) ? $_COOKIE['login_from'] : 'Form';


            if ($user->role->role_name === 'Consumer') {
                // For login page
                $expireTime = time() + 3600 * 24 * 365 * 5;

                setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('login_from', $login_from_cookie, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            }

            if (Config::get('orbit.shop.guest_mode')) {
                if ($user->role->role_name === 'Guest') {
                    $expireTime = time() + 3600 * 24 * 365 * 5;
                    $guest = User::whereHas('role', function ($q) {
                        $q->where('role_name', 'Guest');
                    })->excludeDeleted()->first();
                    setcookie('orbit_email', $guest->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('orbit_firstname', 'Orbit Guest', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                }
            }

            // // Successfull login
            // $activity->setUser($user)
            //          ->setActivityName('login_ok')
            //          ->setActivityNameLong('Sign In')
            //          ->setSessionId($this->session->getSessionId())
            //          ->responseOK();

            // static::proceedPayload($activity, $user->registration_activity_id);
        } else {
            // Login Failed
            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Sign In Failed')
                     ->setModuleName('Application')
                     ->setNotes($response->message)
                     ->responseFailed()
                     ->save();
        }

        // Save the activity
        // $activity->setModuleName('Application')->save();

        // // save to user signin table
        // if ($response->code === 0) {

        //     $signin_via = 'form';
        //     $payload = '';

        //     if (! empty(OrbitInput::get('payload'))) {
        //         $payload = OrbitInput::get('payload');
        //     } else {
        //         $payload = OrbitInput::post('payload');
        //     }

        //     if (! empty($payload)) {
        //         $key = md5('--orbit-mall--');
        //         $payload = (new Encrypter($key))->decrypt($payload);
        //         Log::info('[PAYLOAD] Payload decrypted -- ' . serialize($payload));
        //         parse_str($payload, $data);

        //         if ($data['login_from'] === 'facebook') {
        //             $signin_via = 'facebook';
        //         } else if ($data['login_from'] === 'google') {
        //             $signin_via = 'google';
        //         }
        //     }

        //     $newUserSignin = new UserSignin();
        //     $newUserSignin->user_id = $user->user_id;
        //     $newUserSignin->signin_via = $signin_via;
        //     $newUserSignin->location_id = Config::get('orbit.shop.id');
        //     $newUserSignin->activity_id = $activity->activity_id;
        //     $newUserSignin->save();
        // }

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
        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
        unset($_COOKIE['login_from']);
        unset($_COOKIE['orbit_email']);
        unset($_COOKIE['orbit_firstname']);
        setcookie('orbit_email', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                           ->getAppName();

        // Session Config
        $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);

        $this->session->getSessionConfig()->setConfig('session_origin.header.name', $orbitSessionConfig['header']['name']);
        $this->session->getSessionConfig()->setConfig('session_origin.query_string.name', $orbitSessionConfig['query_string']['name']);
        $this->session->getSessionConfig()->setConfig('session_origin.cookie.name', $orbitSessionConfig['cookie']['name']);

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

        $after_logout_url = Config::get('orbit.shop.after_logout_url', '/customer');
        // Redirect back to /customer
        return Redirect::to($after_logout_url)->withCookie($cookie);
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
        $birthdate = isset($data['birthdate']) ? $data['birthdate'] : '';
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

            if (($from === 'google' || $from === 'facebook') && $customer->status === 'pending') {
                // Only set if the previous status is pending
                $customer->status = 'active';   // make it active
            }

            // Update birthdate if necessary
            if (empty($customer->birthdate)) {
                $customer->userdetail->birthdate = $birthdate;
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
            } else {
                // always update updated_at
                $macModel->setUpdatedAt($macModel->freshTimestamp());
            }

            $macModel->ip_address = $ip;
            $macModel->save();

            Log::info('[PAYLOAD] Mac saved -- ' . serialize($macModel));
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
                        $registration_activity->activity_name_long = 'Sign Up via Mobile (Facebook)';
                        $registration_activity->save();

                        // @author Irianto Pratama <irianto@dominopos.com>
                        // send email if user status active
                        if ($customer->status === 'active') {
                            // Send email process to the queue
                            \Queue::push('Orbit\\Queue\\NewPasswordMail', [
                                'user_id' => $customer->user_id
                            ]);
                        }
                    } else if ($from === 'form') {
                        $registration_activity->activity_name_long = 'Sign Up via Mobile (Email Address)';
                        $registration_activity->save();
                    } else if ($from === 'google') {
                        $registration_activity->activity_name_long = 'Sign Up via Mobile (Google+)';
                        $registration_activity->save();

                        // @author Irianto Pratama <irianto@dominopos.com>
                        // send email if user status active
                        if ($customer->status === 'active') {
                            // Send email process to the queue
                            \Queue::push('Orbit\\Queue\\NewPasswordMail', [
                                'user_id' => $customer->user_id
                            ]);
                        }
                    }
                }
            }
        }

        // Try to update the activity
        if ($captive === 'yes') {
            switch ($from) {
                case 'facebook':
                    $activityNameLong = 'Sign In'; //Sign In via Facebook
                    break;

                case 'google':
                    $activityNameLong = 'Sign In'; //Sign In via Google
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
                    $bg = Media::where('object_id', $retailer->merchant_id)
                        ->where('media_name_id', 'retailer_background')
                        ->where('media_name_long', 'retailer_background_orig')
                        ->where('object_name', 'mall')
                        ->first();
                } catch (Exception $e) {
                }
            } catch (Exception $e) {
            }

            $display_name = Input::get('fname', '');

            if (empty($display_name)) {
                $display_name = Input::get('email', '');
            }

            // Display a view which showing that page is loading
            return View::make('mobile-ci/captive-loading', ['retailer' => $retailer, 'bg' => $bg, 'display_name' => $display_name]);
        }

        if (isset($_GET['createsession'])) {
            // Return mall_portal, cs_portal, pmp_portal etc
            $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                           ->getAppName();

            // Session Config
            $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);

            $cookieName = $orbitSessionConfig['cookie'];
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

            $sessionName = $orbitSessionConfig['query_string'];

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

            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
            setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

            return Redirect::to($redirectTo);
        }

        // Catch all
        $response = new ResponseProvider();
        return $this->render($response);
    }


    /**
     * Clear the session for RGP user
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @return Response
     */
    public function getLogoutRGP()
    {
        $from = isset($_GET['_orbit_logout_from']) === FALSE ? 'portal' : $_GET['_orbit_logout_from'];
        $location_id = isset($_GET['mall_id']) ? $_GET['mall_id'] : NULL;
        $validFrom = ['portal', 'mobile-ci', 'pos'];

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
        unset($_COOKIE['login_from']);
        unset($_COOKIE['orbit_email']);
        unset($_COOKIE['orbit_firstname']);
        setcookie('orbit_email', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');

            $userId = $this->session->read('user_id');


            if ($this->session->read('logged_in') !== TRUE || ! $userId) {
                OrbitShopAPI::throwInvalidArgument('Invalid session data.');
            }
            $user = RgpUser::excludeDeleted()->find($userId);

            if (! $user) {
                OrbitShopAPI::throwInvalidArgument('Session error: user not found.');
            }

            $response->data = NULL;

            // Store session id only from mobile-ci login & logout
            if($from == 'mobile-ci'){

            } else {
                $this->session->destroy();
            }

        } catch (Exception $e) {
            try {
                $this->session->destroy();
            } catch (Exception $e) {
            }

            $response->code = $e->getCode();
            $response->status = 'error';
            $response->message = $e->getMessage();
        }

        return $this->render($response);
    }

    /**
     * Clear the session for BPP user
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @return Response
     */
    public function getLogoutBPP()
    {
        $from = isset($_GET['_orbit_logout_from']) === FALSE ? 'portal' : $_GET['_orbit_logout_from'];
        $location_id = isset($_GET['mall_id']) ? $_GET['mall_id'] : NULL;
        $validFrom = ['portal', 'mobile-ci', 'pos'];

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');
        unset($_COOKIE['login_from']);
        unset($_COOKIE['orbit_email']);
        unset($_COOKIE['orbit_firstname']);
        setcookie('orbit_email', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', '', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        $response = new ResponseProvider();

        try {
            $this->session->start(array(), 'no-session-creation');

            $userId = $this->session->read('user_id');


            if ($this->session->read('logged_in') !== TRUE || ! $userId) {
                OrbitShopAPI::throwInvalidArgument('Invalid session data.');
            }
            $user = BppUser::excludeDeleted()->find($userId);

            if (! $user) {
                OrbitShopAPI::throwInvalidArgument('Session error: user not found.');
            }

            $response->data = NULL;

            // Store session id only from mobile-ci login & logout
            if($from == 'mobile-ci'){

            } else {
                $this->session->destroy();
            }

        } catch (Exception $e) {
            try {
                $this->session->destroy();
            } catch (Exception $e) {
            }

            $response->code = $e->getCode();
            $response->status = 'error';
            $response->message = $e->getMessage();
        }

        return $this->render($response);
    }

    /**
     * Detect the user agent of the request.
     *
     * @return string
     */
    protected function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown-UA/?';
    }
}
