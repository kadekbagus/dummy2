<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing user sign in.
 */
use Net\MacAddr;
use \IntermediateBaseController;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Session\AppOriginProcessor;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use stdClass;
use Activity;
use User;
use UserDetail;
use Hash;
use Role;
use Lang;
use Validator;
use DB;
use Artdarek\OAuth\Facade\OAuth;
use Redirect;
use URL;
use Orbit\Controller\API\v1\Pub\RegistrationAPIController as Regs;
use Orbit\Helper\Net\Domain;
use \Carbon\Carbon;
use \Exception;
use \Inbox;

class LoginAPIController extends IntermediateBaseController
{
    const APPLICATION_ID = 1;

    /**
     * POST - Login customer from gotomalls.com
     *
     * @author Ahmad ahmad@dominopos.com
     * @param email
     * @param password
     * @param from_mall (yes/no)
     */
    public function postLoginCustomer()
    {
        $this->response = new ResponseProvider();
        $roles=['Consumer'];
        $activity = Activity::portal()
                            ->setActivityType('login');
        $activity_origin = OrbitInput::post('activity_origin'); 
        if ($activity_origin === 'mobileci') {
            // set this activity as mobileci instead of portal if coming from mobileci
                $activity = Activity::mobileci()
                                ->setActivityType('login');
        };
        try {
            $email = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));
            $mall_id = OrbitInput::post('mall_id', null);
            // $mode = OrbitInput::post('mode', 'login');

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('role')
                        ->excludeDeleted()
                        ->whereHas('role', function($q) {
                            $q->where('role_name', 'Consumer');
                        })
                        ->where('user_email', $email);

            $user = $user->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.inactiveuser');
                OrbitShopAPI::throwInvalidArgument($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $roleIds = Role::roleIdsByName($roles);
            if (! in_array($user->user_role_id, $roleIds)) {
                $message = Lang::get('validation.orbit.access.forbidden', [
                    'action' => 'Login (Role Denied)'
                ]);
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $this->session->start(array(), 'no-session-creation');
            // get the session data
            $sessionData = $this->session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['user_id'] = $user->user_id;
            $sessionData['email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = $user->getFullName();
            $sessionData['visited_location'] = [];
            $sessionData['coupon_location'] = [];

            // update the guest session data, append user data to it so the user will be recognized
            $this->session->update($sessionData);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

            setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('login_from', 'Form', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

            if ($activity_origin !== 'mobileci') {
                $activity->setUser($user)
                         ->setActivityName('login_ok')
                         ->setActivityNameLong('Sign in')
                         ->responseOK()->setModuleName('Application')->save();
                
                $user->activity = $activity;
            } else {
                // set \MobileCI\MobileCIAPIController->session using $this->session
                $CIsession = \MobileCI\MobileCIAPIController::create()->setSession($this->session);
            }

            $this->response->data = $user;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Login Success';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        }

        return $this->render($this->response);
    }

    /**
     * Check email exist in sign in
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
     * Handle Google SignIn
     *
     * Every google sign in goes here (MobileCI, Gotomalls.com, DesktopCI(AngularCI))
     * @author Ahmad <ahmad@dominopos.com>
     *
     */
    public function getGoogleCallbackView()
    {
        $oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $recognized = \Input::get('recognized', 'none');
        $code = \Input::get('code', NULL);
        $state = \Input::get('state', NULL);
        $caller_url = OrbitInput::get('from_url', NULL); // this input using route-name
        $caller_url = ! is_null($caller_url) ? URL::route($caller_url) : Config::get('orbit.shop.after_social_sign_in');
        $encoded_caller_url_full = OrbitInput::get('from_url_full', NULL); // this input using full-url
        $encoded_redirect_to_url = OrbitInput::get('to_url', NULL); // this input using full-url
        $mall_id = OrbitInput::get('mid', NULL);
        $angular_ci = OrbitInput::get('aci', NULL);

        $googleService = OAuth::consumer( 'Google' );
        if ( !empty( $code ) ) {
            try {
                Config::set('orbit.session.availability.query_string', $oldRouteSessionConfigValue);
                $token = $googleService->requestAccessToken( $code );

                $user = json_decode( $googleService->request( 'https://www.googleapis.com/oauth2/v1/userinfo' ), true );

                $userEmail = isset($user['email']) ? $user['email'] : '';
                $firstName = isset($user['given_name']) ? $user['given_name'] : '';
                $lastName = isset($user['family_name']) ? $user['family_name'] : '';
                $gender = isset($user['gender']) ? $user['gender'] : '';
                $socialid = isset($user['id']) ? $user['id'] : '';

                $mall_id_from_state = json_decode($this->base64UrlDecode($state))->mid;
                $angular_ci_from_state = json_decode($this->base64UrlDecode($state))->aci;
                $redirect_to_url_from_state = json_decode($this->base64UrlDecode($state))->redirect_to_url;
                // from mall = yes, indicate the request coming from Mall CI, then use MobileCIAPIController::getGoogleCallbackView
                // to set the session and other things
                if (! empty($mall_id_from_state)) {
                    $_GET['caller_url'] = $caller_url;
                    $_GET['redirect_to_url'] = $encoded_redirect_to_url;
                    $_GET['state'] = $state;
                    $_GET['code'] = $code;
                    $_GET['mall_id'] = $mall_id_from_state;
                    $_GET['email'] = $userEmail;
                    $_GET['first_name'] = $firstName;
                    $_GET['last_name'] = $lastName;
                    $_GET['gender'] = $gender;
                    $_GET['socialid'] = $socialid;
                    $response = \MobileCI\MobileCIAPIController::create()->getGoogleCallbackView();

                    return $response;
                } else { // the request coming from landing page (gotomalls.com)
                    $data = [
                        'email' => $userEmail,
                        'fname' => $firstName,
                        'lname' => $lastName,
                        'gender' => $gender,
                        'login_from'  => 'google',
                        'social_id'  => $socialid,
                        'mac' => \Input::get('mac_address', ''),
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'is_captive' => 'yes',
                        'recognized' => $recognized
                    ];
                    $orbit_origin = \Input::get('orbit_origin', 'google');

                    // There is a chance that user not 'grant' his email while approving our app
                    // so we double check it here
                    if (empty($userEmail)) {
                        if (! empty($angular_ci_from_state)) {
                            $caller_url = urldecode($encoded_caller_url_full);
                        }
                        $parsed_caller_url = parse_url((string)$caller_url);
                        if (isset($parsed_caller_url['query'])) {
                            $caller_url .= '&error=no_email';
                        } else {
                            $caller_url .= '?error=no_email';
                        }

                        return Redirect::to(urldecode($encoded_caller_url_full));
                    }

                    $loggedInUser = $this->doAutoLogin($userEmail);
                    if (! is_object($loggedInUser)) {
                        // register user without password and birthdate
                        $status = 'active';
                        $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $gender, NULL, NULL, TRUE, NULL, NULL, NULL, $status, 'Google');
                        if (get_class($response) !== 'User') {
                            throw new Exception($response->message, $response->code);
                        }

                        $loggedInUser = $this->doAutoLogin($response->user_email);
                    }

                    $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

                    setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                    if (empty($angular_ci_from_state)) {
                        return Redirect::to(Config::get('orbit.shop.after_social_sign_in'));
                    }
                    // request coming from angular-ci
                    return Redirect::to(urldecode($redirect_to_url_from_state));
                }
            } catch (Exception $e) {
                if (! empty($angular_ci)) {
                    $caller_url = urldecode($encoded_caller_url_full);
                }
                $errorMessage = 'Error: ' . $e->getMessage();
                $parsed_caller_url = parse_url((string)$caller_url);
                if (isset($parsed_caller_url['query'])) {
                    $caller_url .= '&error=' . $errorMessage;
                } else {
                    $caller_url .= '?error=' . $errorMessage;
                }
                return Redirect::to($caller_url);
            }

        } else {
            try {
                // get googleService authorization
                $url = $googleService->getAuthorizationUri();
                // override state param to have our destination url inside
                $state_array = array('redirect_to_url' => $encoded_redirect_to_url, 'mid' => $mall_id, 'aci' => $angular_ci);
                $state = json_encode($state_array);
                $stateString = $this->base64UrlEncode($state);
                $parsed_url = parse_url((string)$url);
                $query = parse_str($parsed_url['query'], $output);
                $output['state'] = $stateString;
                $query_string = http_build_query($output);
                $parsed_url['query'] = $query_string;
                // rebuild the googleService authorization url
                $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'] . '?' . $parsed_url['query'];

                return Redirect::to((string)$new_url);
            } catch (Exception $e) {
                if (! empty($angular_ci)) {
                    $caller_url = urldecode($encoded_caller_url_full);
                }
                $errorMessage = 'Error: ' . $e->getMessage();
                $parsed_caller_url = parse_url((string)$caller_url);
                if (isset($parsed_caller_url['query'])) {
                    $caller_url .= '&error=' . $errorMessage;
                } else {
                    $caller_url .= '?error=' . $errorMessage;
                }
                return Redirect::to($caller_url);
            }
        }   
    }

    protected function getFacebookError($encoded_caller_url = NULL)
    {
        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $fbError = 'Unknown Error';

        $errorDesc = \Input::get('error_description', NULL);
        if (! is_null($errorDesc)) {
            $fbError = $errorDesc;
        }
        $caller_url = Config::get('orbit.shop.after_social_sign_in');
        if (! empty($encoded_caller_url)) {
            $caller_url = urldecode($encoded_caller_url);
        }
        $errorMessage = 'Facebook Error: ' . $fbError;
        $parsed_caller_url = parse_url((string)$caller_url);
        if (isset($parsed_caller_url['query'])) {
            $caller_url .= '&error=' . $errorMessage;
        } else {
            $caller_url .= '?error=' . $errorMessage;
        }
        return Redirect::to($caller_url);
    }

    public function getSocialLoginCallbackView()
    {
        $recognized = \Input::get('recognized', 'none');
        $encoded_caller_url = \Input::get('caller_url', NULL);
        $encoded_redirect_to_url = \Input::get('redirect_to_url', NULL);
        $angular_ci = \Input::get('aci', FALSE);

        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $error = \Input::get('error', NULL);

        $city = '';
        $country = '';

        if (! is_null($error)) {
            if ($angular_ci) {
                return $this->getFacebookError($encoded_caller_url);
            }
            return $this->getFacebookError();
        }

        $orbit_origin = \Input::get('orbit_origin', 'facebook');

        // set the query session string to FALSE, so the CI will depend on session cookie
        Config::set('orbit.session.availability.query_string', FALSE);

        // Return mall_portal, cs_portal, pmp_portal etc
        $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                       ->getAppName();

        // Session Config
        $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
        $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

        // Instantiate the OrbitSession object
        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('session_origin', $orbitSessionConfig);
        $config->setConfig('application_id', $applicationId);

        try {
            $this->session = new Session($config);
            $this->session->start(array(), 'no-session-creation');
        } catch (Exception $e) {
            $this->session->start();
        }

        $fb = new \Facebook\Facebook([
            'persistent_data_handler' => new \Orbit\FacebookSessionAdapter($this->session),
            'app_id' => Config::get('orbit.social_login.facebook.app_id'),
            'app_secret' => Config::get('orbit.social_login.facebook.app_secret'),
            'default_graph_version' => Config::get('orbit.social_login.facebook.version')
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $accessToken = $helper->getAccessToken();
        $useExtended = Config::get('orbit.social.facebook.use_extended_perms');

        $query = '/me?fields=id,email,name,first_name,last_name,gender';
        if ($useExtended) {
            $query .= ',location,relationship_status,photos,work,education';
        }
        $response = $fb->get($query, $accessToken->getValue());
        $user = $response->getGraphUser();

        $userEmail = isset($user['email']) ? $user['email'] : '';
        $firstName = isset($user['first_name']) ? $user['first_name'] : '';
        $lastName = isset($user['last_name']) ? $user['last_name'] : '';
        $gender = isset($user['gender']) ? $user['gender'] : '';
        $socialid = isset($user['id']) ? $user['id'] : '';

        $data = [
            'email' => $userEmail,
            'fname' => $firstName,
            'lname' => $lastName,
            'gender' => $gender,
            'login_from'  => 'facebook',
            'social_id'  => $socialid,
            'mac' => \Input::get('mac_address', ''),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'is_captive' => 'yes',
            'recognized' => $recognized,
        ];
        $extendedData = [];

        if ($useExtended === TRUE) {
            $relationship = isset($user['relationship_status']) ? $user['relationship_status'] : '';
            $work = isset($user['work']) ? $user['work'][0]['employer']['name'] : '';
            $education = isset($user['education']) ? $user['education'][0]['type'] : '';

            if (isset($user['location']['name'])) {
                $location = explode(',', $user['location']['name']);
                $city = isset($location[0]) ? $location[0] : '';
                $country = isset($location[1]) ? $location[1] : '';
            }

            $extendedData = [
                'relationship'  => $relationship,
                'work'  => $work,
                'education'  => $education,
                'city'  => $city,
                'country'  => $country,
            ];
        }

        // Merge the standard and extended permission (if any)
        $data = $extendedData + $data;

        // There is a chance that user not 'grant' his email while approving our app
        // so we double check it here
        if (empty($userEmail)) {
            if ($angular_ci) {
                return Redirect::to(urldecode($encoded_caller_url) . '/#/?error=no_email');
            }
            return Redirect::to(Config::get('orbit.shop.after_social_sign_in') . '/#/?error=no_email');
        }

        $loggedInUser = $this->doAutoLogin($userEmail);
        if (! is_object($loggedInUser)) {
            // register user without password and birthdate
            $status = 'active';
            $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $gender, NULL, NULL, TRUE, NULL, NULL, NULL, $status, 'Facebook');
            if (get_class($response) !== 'User') {
                throw new Exception($response->message, $response->code);
            }

            $loggedInUser = $this->doAutoLogin($response->user_email);
        }

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

        setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', 'Facebook', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        if ($angular_ci) {
            return Redirect::to(urldecode($encoded_redirect_to_url));
        }
        return Redirect::to(Config::get('orbit.shop.after_social_sign_in'));
    }

    /**
     * Handle Facebook SignIn
     *
     * Some facebook sign in goes here (Gotomalls.com and DesktopCI(AngularCI), MobileCI is on MobileCIAPIController)
     * @author Ahmad <ahmad@dominopos.com>
     *
     */
    public function postSocialLoginView()
    {
        $encoded_caller_url_full = OrbitInput::get('from_url_full', NULL);
        $encoded_redirect_to_url = OrbitInput::get('to_url', NULL);
        $angular_ci = OrbitInput::get('aci', FALSE);

        // Return mall_portal, cs_portal, pmp_portal etc
        $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                       ->getAppName();

        // Session Config
        $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
        $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

        // Instantiate the OrbitSession object
        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('session_origin', $orbitSessionConfig);
        $config->setConfig('application_id', $applicationId);

        try {
            $this->session = new Session($config);
            $this->session->start();
        } catch (Exception $e) {

        }

        $fb = new \Facebook\Facebook([
            'persistent_data_handler' => new \Orbit\FacebookSessionAdapter($this->session),
            'app_id' => Config::get('orbit.social_login.facebook.app_id'),
            'app_secret' => Config::get('orbit.social_login.facebook.app_secret'),
            'default_graph_version' => Config::get('orbit.social_login.facebook.version')
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $permissions = Config::get('orbit.social.facebook.scope', ['email', 'public_profile']);
        $facebookCallbackUrl = URL::route('pub.social_login_callback', [
            'orbit_origin' => 'facebook',
            'from_captive' => OrbitInput::post('from_captive'),
            'mac_address' => \Input::get('mac_address', ''),
            'caller_url' => $encoded_caller_url_full,
            'redirect_to_url' => $encoded_redirect_to_url,
            'aci' => $angular_ci,
        ]);

        // This is to re-popup the permission on login in case some of the permissions revoked by user
        $rerequest = '&auth_type=rerequest';

        $url = $helper->getLoginUrl($facebookCallbackUrl, $permissions) . $rerequest;

        // No need to grant temporary https access anymore, we are using dnsmasq --ipset features for walled garden
        // $this->grantInternetAcces('social');

        return Redirect::to($url);
    }

    /**
     * The purpose of this function is to by pass the new sign in process that use password
     * e.g: User came from Facebook / Google sign in
     * 
     * @author Ahmad <ahmad@dominopos.com>
     * @param string $email User email
     * @return User $user (IF user exist; FALSE: user not exist)
     */
    public function doAutoLogin($email)
    {
        $user = User::excludeDeleted()
            ->with('role')
            ->where('user_email', $email)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (is_object($user)) {
            $this->session->start(array(), 'no-session-creation');

            \MobileCI\MobileCIAPIController::create()->setSession($this->session)->linkGuestToUser($user, FALSE);
            // get the session data
            $sessionData = $this->session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['user_id'] = $user->user_id;
            $sessionData['email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = $user->getFullName();
            $sessionData['visited_location'] = [];
            $sessionData['coupon_location'] = [];

            // update the guest session data, append user data to it so the user will be recognized
            $this->session->update($sessionData);

            return $user;
        }

        return FALSE;
    }

    public function base64UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }

    public function base64UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }

    public function postDesktopCILogin()
    {
        $this->response = new ResponseProvider();
        $roles = ['Consumer'];
        $activity = Activity::mobileci()->setActivityType('mobileci');
        try {
            $email = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));
            $password_confirmation = OrbitInput::post('password_confirmation');
            $mall_id = OrbitInput::post('mall_id', null);
            $mode = OrbitInput::post('mode', 'login');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $mall_id,
                    'email' => $email,
                    'password' => $password,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'email' => 'required|email',
                    'password' => 'required',
                )
            );

            DB::beginTransaction();
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($mode === 'registration') {
                // do the registration
                $_POST['activity_name_long'] = 'Sign Up via Mobile (Email Address)';
                $_POST['activity_origin'] = 'mobileci';
                $_POST['use_transaction'] = FALSE;
                $registration = \Orbit\Controller\API\v1\Pub\RegistrationAPIController::create('raw');
                $response = $registration->setMallId($mall_id)->postRegisterCustomer();
                $response_data = json_decode($response->getOriginalContent());

                unset($_POST['activity_name_long']);
                unset($_POST['activity_origin']);
                if($response_data->code !== 0) {
                    $errorMessage = $response_data->message;
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // set activation notification
                $inbox = new Inbox();
                $inbox->addToInbox($response_data->data->user_id, $response_data->data, $mall_id, 'activation');
            }

            $mall = Mall::excludeDeleted()->where('merchant_id', $mall_id)->first();

            // do the login
            $user = User::with('role')
                        ->excludeDeleted()
                        ->whereHas('role', function($q) {
                            $q->where('role_name', 'Consumer');
                        })
                        ->where('user_email', $email);

            $user = $user->first();

            if (! is_object($user)) {
                $message = 'User with the specified email is not found';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $roleIds = Role::roleIdsByName($roles);
            if (! in_array($user->user_role_id, $roleIds)) {
                $message = Lang::get('validation.orbit.access.forbidden', [
                    'action' => 'Login (Role Denied)'
                ]);
                OrbitShopAPI::throwInvalidArgument($message);
            }

            // prevent inactive user to access
            $notAllowedStatus = ['inactive'];
            $lowerCasedStatus = strtolower($user->status);
            if (in_array($lowerCasedStatus, $notAllowedStatus)) {
                OrbitShopAPI::throwInvalidArgument('You are not allowed to login. Please check with Customer Service.');
            }

            // Return mall_portal, cs_portal, pmp_portal etc
            $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                           ->getAppName();

            // Session Config
            $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
            $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

            // Instantiate the OrbitSession object
            $config = new SessionConfig(Config::get('orbit.session'));
            $config->setConfig('session_origin', $orbitSessionConfig);
            $config->setConfig('application_id', $applicationId);

            try {
                $this->session = new Session($config);
                $this->session->start(array(), 'no-session-creation');
                $sessionData = $this->session->read(NULL);
                $sessionData['logged_in'] = TRUE;
                $sessionData['user_id'] = $user->user_id;
                $sessionData['email'] = $user->user_email;
                $sessionData['role'] = $user->role->role_name;
                $sessionData['fullname'] = $user->getFullName();

                $this->session->update($sessionData);
            } catch (Exception $e) {
                // get the session data
                $sessionData = array();
                $sessionData['logged_in'] = TRUE;
                $sessionData['user_id'] = $user->user_id;
                $sessionData['email'] = $user->user_email;
                $sessionData['role'] = $user->role->role_name;
                $sessionData['fullname'] = $user->getFullName();
                $this->session->enableForceNew()->start($sessionData);
            }

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            // link guest to user
            \MobileCI\MobileCIAPIController::create()->setSession($this->session)->linkGuestToUser($user, FALSE);

            // acquire user
            // todo: remove comment if the QA ok'ed this implementation, so it not affect dashboard
            // $firstAcquired = $mall->acquireUser($user, 'form');

            // if the user is viewing the mall for the 1st time then set the signup activity
            // todo: remove comment if the QA ok'ed this implementation, so it not affect dashboard
            // if ($firstAcquired) {
                // \MobileCI\MobileCIAPIController::create()->setSession($this->session)->setSignUpActivity($user, 'form', $mall);
            // }

            // if the user is viewing the mall for the 1st time in this session
            // then set also the sign in activity
            $visited_locations = [];
            if (! empty($this->session->read('visited_location'))) {
                $visited_locations = $this->session->read('visited_location');
            }
            if (! in_array($mall->merchant_id, $visited_locations)) {
                // todo: remove comment if the QA ok'ed this implementation, so it not affect dashboard
                // \MobileCI\MobileCIAPIController::create()->setSession($this->session)->setSignInActivity($user, 'form', $mall, null);
                $this->session->write('visited_location', array_merge($visited_locations, [$mall->merchant_id]));
            }

            // update last visited records
            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $mall->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now($mall->timezone->timezone_name);
            $user_detail->save();

            // auto coupon issuance checkwill happen on each page after the login success
            // todo: remove comment if the QA ok'ed this implementation, so it not affect dashboard
            // \Coupon::issueAutoCoupon($mall, $user, $this->session);

            DB::commit();
            $data = new stdClass();
            $data->user_id = $user->user_id;
            $data->user_firstname = $user->user_firstname;
            $data->user_lastname = $user->user_lastname;
            $data->user_email = $user->user_email;
            $data->orbit_session = $this->session->getSessionId();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Login Success';

        } catch (ACLForbiddenException $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        } catch (InvalidArgsException $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        } catch (Exception $e) {
            DB::rollback();
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getFile(), $e->getLine()];

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        }

        return $this->render($this->response);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            \App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }
}
