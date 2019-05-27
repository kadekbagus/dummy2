<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing user sign in.
 */
use Net\MacAddr;
use Orbit\Helper\Net\GuestUserGenerator;
use \IntermediateBaseController;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\PubControllerAPI;
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
use UserSignin;
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
use Orbit\Helper\Session\UserGetter;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Net\SignInRecorder;
use Orbit\Controller\API\v1\Pub\ActivationAPIController;
use Event;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;

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
        $activity = Activity::mobileci()
                            ->setActivityType('login');

        try {
            $email = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));
            $mall_id = OrbitInput::post('mall_id', null);
            $rewardId = OrbitInput::get('reward_id', null);
            $rewardType = OrbitInput::get('reward_type', null);
            $language = OrbitInput::get('language', 'id');
            $redirect_to_url = OrbitInput::post('to_url', null);
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

            try {
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
                $sessionData['status'] = $user->status;

                $guest_id = $this->session->read('guest_user_id');

                // check guest user id on session if empty create new one
                if (empty($guest_id)) {
                    $guestConfig = [
                        'session' => $this->session
                    ];
                    $guest = GuestUserGenerator::create($guestConfig)->generate();
                    $sessionData['guest_user_id'] = $guest->user_id;
                    $sessionData['guest_email'] = $guest->user_email;
                }

                // update the guest session data, append user data to it so the user will be recognized
                $this->session->update($sessionData);
            } catch (Exception $e) {

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

                // get the session data
                $sessionData = array();
                $sessionData['logged_in'] = TRUE;
                $sessionData['user_id'] = $user->user_id;
                $sessionData['email'] = $user->user_email;
                $sessionData['role'] = $user->role->role_name;
                $sessionData['fullname'] = $user->getFullName();
                $sessionData['status'] = $user->status;

                $this->session->enableForceNew()->start($sessionData);

                $guestConfig = [
                    'session' => $this->session
                ];
                $guest = GuestUserGenerator::create($guestConfig)->generate();
                $guestData = array();
                $guestData['guest_user_id'] = $guest->user_id;
                $guestData['guest_email'] = $guest->user_email;

                $this->session->update($guestData);
            }

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

            setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('login_from', 'Form', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

            DB::beginTransaction();
            Event::fire('orbit.login.after.success', array($user->user_id, $rewardId, $rewardType, $language));
            DB::commit();

            if ($this->appOrigin !== 'mobile_ci') {
                if (! empty($rewardId) && ! empty($rewardType)) {
                    // registration activity that comes from promotional event page
                    $reward = PromotionalEventProcessor::create($user->user_id, $rewardId, $rewardType, $language)->getPromotionalEvent();

                    if (is_object($reward)) {
                        $activity->setActivityType('login_with_reward')
                                 ->setUser($user)
                                 ->setObject($user)
                                 ->setLocation(NULL)
                                 ->setObjectDisplayName($reward->reward_name)
                                 ->setActivityName('login_ok')
                                 ->setActivityNameLong('Sign in')
                                 ->responseOK()
                                 ->setModuleName('Application')
                                 ->save();

                        $user->activity = $activity;

                        // Save also activity user sign in in user_signin table
                        SignInRecorder::setSignInActivity($user, 'form', NULL, $activity, TRUE, $rewardId, $rewardType, $language);
                    }
                } else {
                    $activity->setUser($user)
                             ->setLocation(NULL)
                             ->setActivityName('login_ok')
                             ->setActivityNameLong('Sign in')
                             ->responseOK()
                             ->setModuleName('Application')
                             ->save();

                    $user->activity = $activity;

                    // Save also activity user sign in in user_signin table
                    SignInRecorder::setSignInActivity($user, 'form', NULL, $activity, TRUE, $rewardId, $rewardType, $language);
                }

            } else {
                // set \MobileCI\MobileCIAPIController->session using $this->session
                $CIsession = \MobileCI\MobileCIAPIController::create()->setSession($this->session);
            }

            $user->redirect_to_url = $redirect_to_url;

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
        $mall_id_from_desktop = OrbitInput::get('mall_id', NULL);
        $user_location = OrbitInput::get(Config::get('orbit.user_location.query_string.name', 'ul'), NULL);
        $angular_ci = OrbitInput::get('aci', NULL);
        $rewardId = OrbitInput::get('reward_id', NULL);
        $rewardType = OrbitInput::get('reward_type', NULL);
        $language = OrbitInput::get('language', 'id');

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
                $mall_id_from_desktop_state = json_decode($this->base64UrlDecode($state))->mall_id;
                $angular_ci_from_state = json_decode($this->base64UrlDecode($state))->aci;
                $redirect_to_url_from_state = empty(json_decode($this->base64UrlDecode($state))->redirect_to_url) ? Config::get('orbit.shop.after_social_sign_in') : json_decode($this->base64UrlDecode($state))->redirect_to_url;
                $_GET[Config::get('orbit.user_location.query_string.name', 'ul')] = json_decode($this->base64UrlDecode($state))->user_location;

                $reward_id_from_state = json_decode($this->base64UrlDecode($state))->reward_id;
                $reward_type_from_state = json_decode($this->base64UrlDecode($state))->reward_type;
                $language_from_state = json_decode($this->base64UrlDecode($state))->language;

                $this->session = SessionPreparer::prepareSession();

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
                    if (! empty($encoded_caller_url_full)) {
                        $encoded_caller_url_full = $this->addHttps($encoded_caller_url_full);
                        $caller_url = $encoded_caller_url_full;
                    }
                    $parsed_caller_url = parse_url((string)$caller_url);
                    if (isset($parsed_caller_url['query'])) {
                        $caller_url .= '&error=no_email';
                    } else {
                        $caller_url .= '?error=no_email';
                    }

                    return Redirect::to($encoded_caller_url_full);
                }

                $loggedInUser = $this->doAutoLogin($userEmail);
                if (! is_object($loggedInUser)) {
                    // register user without password and birthdate
                    $status = 'active';
                    $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $gender, NULL, NULL, TRUE, NULL, NULL, NULL, $status, 'Google');
                    if (get_class($response) !== 'User') {
                        throw new Exception($response->message, $response->code);
                    }

                    SignInRecorder::setSignUpActivity($response, 'google', NULL, $reward_id_from_state, $reward_type_from_state, $language_from_state);
                    // create activation_ok activity without using token
                    $activation_ok = ActivationAPIController::create('raw')
                        ->setSaveAsAutoActivation($response, 'google')
                        ->postActivateAccount();

                    $loggedInUser = $this->doAutoLogin($response->user_email);
                    // promotional event reward event
                    DB::beginTransaction();
                    Event::fire('orbit.registration.after.createuser', array($loggedInUser->user_id, $reward_id_from_state, $reward_type_from_state, $language_from_state));
                    DB::commit();
                }

                SignInRecorder::setSignInActivity($loggedInUser, 'google', NULL, NULL, TRUE, $reward_id_from_state, $reward_type_from_state, $language_from_state);

                // promotional event reward event
                DB::beginTransaction();
                Event::fire('orbit.login.after.success', array($loggedInUser->user_id, $reward_id_from_state, $reward_type_from_state, $language_from_state));
                DB::commit();

                $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

                setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                $mallId = $mall_id_from_desktop_state;

                if (!empty($mallId)) {
                    // request comes from desktop ci / mobile ci
                    $this->registerCustomValidation();

                    $validator = Validator::make(
                        array(
                            'mall_id' => $mallId,
                        ),
                        array(
                            'mall_id' => 'orbit.empty.mall',
                        )
                    );

                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $retailer = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();

                    $this->session->write('login_from', 'google');

                    $user = UserGetter::getLoggedInUserOrGuest($this->session);

                    if (is_object($user)) {
                        $this->acquireUser($retailer, $user, 'google', $reward_id_from_state, $reward_type_from_state, $language_from_state);
                    }
                }
                $redirect_to_url_from_state = $this->addHttps($redirect_to_url_from_state);

                return Redirect::to($redirect_to_url_from_state);
            } catch (Exception $e) {
                if (! empty($angular_ci)) {
                    $caller_url = $encoded_caller_url_full;
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
                $state_array = array(
                    'redirect_to_url' => $encoded_redirect_to_url,
                    'mid' => $mall_id,
                    'mall_id' => $mall_id_from_desktop,
                    'aci' => $angular_ci,
                    'user_location' => $user_location,
                    'reward_id' => $rewardId,
                    'reward_type' => $rewardType,
                    'language' => $language
                );
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
                    $caller_url = $encoded_caller_url_full;
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
        $encoded_redirect_to_url = \Input::get('redirect_to_url', Config::get('orbit.shop.after_social_sign_in'));
        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $fbError = 'access_denied';
        $fbErrorMessage = 'Unknown Error';
        $error = \Input::get('error', NULL);
        if (! is_null($error)) {
            $fbError = $error;
        }

        $errorDesc = \Input::get('error_description', NULL);
        if (! is_null($errorDesc)) {
            $fbErrorMessage = $errorDesc;
        }
        $caller_url = Config::get('orbit.shop.after_social_sign_in');
        if (! empty($encoded_caller_url)) {
            $caller_url = $encoded_caller_url;
        }

        $_POST['plain'] = 'yes';
        $errorParam = [
            'error' => $fbError,
            'errorMessage' => $fbErrorMessage,
            'to_url' => $encoded_redirect_to_url
        ];

        $rewardId = \Input::get('reward_id', NULL);
        $rewardType = \Input::get('reward_type', NULL);
        if (! empty($rewardId)) {
            $errorParam['reward_id'] = $rewardId;
            $errorParam['reward_type'] = $rewardType;
        }

        $queryString = http_build_query($errorParam);

        $qmark = strpos($caller_url, '?');
        if ($qmark === false) {
            $queryString = '?' . $queryString;
        } else {
            $queryString = '&' . $queryString;
        }
        return Redirect::to($caller_url . $queryString);
    }

    public function getSocialLoginCallbackView()
    {
        $recognized = \Input::get('recognized', 'none');
        $encoded_caller_url = \Input::get('caller_url', Config::get('orbit.shop.after_social_sign_in'));
        $encoded_redirect_to_url = \Input::get('redirect_to_url', Config::get('orbit.shop.after_social_sign_in'));
        $angular_ci = \Input::get('aci', FALSE);
        $mall_id = \Input::get('mall_id', NULL);
        $rewardId = \Input::get('reward_id', NULL);
        $rewardType = \Input::get('reward_type', NULL);
        $language = \Input::get('language', 'id');

        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $error = \Input::get('error', NULL);

        $city = '';
        $country = '';

        if (! is_null($error)) {
            if (! empty($encoded_caller_url)) {
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
        $config->setConfig('expire', $orbitSessionConfig['expire']);
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

        // There is a chance that user not 'grant' his email while approving our app
        // or user sign up facebook using phone number via facebook mobile app
        // so we double check it here
        if (empty($userEmail)) {
            $_POST['plain'] = 'yes';
            $errorParam = [
                'error' => 'no_fb_email',
                'errorMessage' => 'User not providing email address',
                'to_url' => $encoded_redirect_to_url
            ];

            if (! empty($rewardId)) {
                $errorParam['reward_id'] = $rewardId;
                $errorParam['reward_type'] = $rewardType;
            }

            $queryString = http_build_query($errorParam);

            $qmark = strpos($encoded_caller_url, '?');
            if ($qmark === false) {
                $queryString = '?' . $queryString;
            } else {
                $queryString = '&' . $queryString;
            }
            return Redirect::to($encoded_caller_url . $queryString);
        }

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

        $loggedInUser = $this->doAutoLogin($userEmail);
        if (! is_object($loggedInUser)) {
            // register user without password and birthdate
            $status = 'active';
            $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $gender, NULL, NULL, TRUE, NULL, NULL, NULL, $status, 'Facebook');
            if (get_class($response) !== 'User') {
                throw new Exception($response->message, $response->code);
            }

            // create registration_ok activity
            SignInRecorder::setSignUpActivity($response, 'facebook', NULL, $rewardId, $rewardType, $language);
            // create activation_ok activity without using token
            $activation_ok = ActivationAPIController::create('raw')
                ->setSaveAsAutoActivation($response, 'facebook')
                ->postActivateAccount();
            $loggedInUser = $this->doAutoLogin($response->user_email);

            // promotional event reward event
            DB::beginTransaction();
            Event::fire('orbit.registration.after.createuser', array($loggedInUser->user_id, $rewardId, $rewardType, $language));
            DB::commit();

            Event::fire('orbit.user.activation.success', [$loggedInUser]);
        }

        // promotional event reward event
        DB::beginTransaction();
        Event::fire('orbit.login.after.success', array($loggedInUser->user_id, $rewardId, $rewardType, $language));
        DB::commit();

        SignInRecorder::setSignInActivity($loggedInUser, 'facebook', NULL, NULL, TRUE, $rewardId, $rewardType, $language);

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

        setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', 'Facebook', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        if (! empty($encoded_redirect_to_url)) {

            if (!empty($mall_id)) {
                $this->registerCustomValidation();

                $validator = Validator::make(
                    array(
                        'mall_id' => $mall_id,
                    ),
                    array(
                        'mall_id' => 'orbit.empty.mall',
                    )
                );

                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $retailer = Mall::excludeDeleted()->where('merchant_id', $mall_id)->first();

                $this->session = SessionPreparer::prepareSession();
                $this->session->write('login_from', 'facebook');

                $user = UserGetter::getLoggedInUserOrGuest($this->session);

                if (is_object($user)) {
                    $this->acquireUser($retailer, $user, 'facebook', $rewardId, $rewardType, $language);
                }
            }
            $encoded_redirect_to_url = $this->addHttps($encoded_redirect_to_url);
            return Redirect::to($encoded_redirect_to_url);
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
        $mall_id = OrbitInput::get('mall_id', NULL);
        $user_location = OrbitInput::get(Config::get('orbit.user_location.query_string.name', 'ul'), NULL);
        $encoded_caller_url_full = OrbitInput::get('from_url_full', NULL);
        $encoded_redirect_to_url = OrbitInput::get('to_url', NULL);
        $angular_ci = OrbitInput::get('aci', FALSE);
        $rewardId = OrbitInput::get('reward_id', NULL);
        $rewardType = OrbitInput::get('reward_type', NULL);
        $language = OrbitInput::get('language', 'id');

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
            'mall_id' => $mall_id,
            Config::get('orbit.user_location.query_string.name', 'ul') => $user_location,
            'reward_id' => $rewardId,
            'reward_type' => $rewardType,
            'language' => $language,
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
            $sessionData['status'] = $user->status;

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
                $_POST['use_transaction'] = FALSE;
                $registration = \Orbit\Controller\API\v1\Pub\RegistrationAPIController::create('raw');
                $response = $registration
                    ->setAppOrigin('mobile_ci')
                    ->setMallId($mall_id)
                    ->postRegisterCustomer();
                $response_data = json_decode($response->getOriginalContent());

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
            $config->setConfig('expire', $orbitSessionConfig['expire']);
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
                $sessionData['status'] = $user->status;

                $this->session->update($sessionData);
            } catch (Exception $e) {
                // get the session data
                $sessionData = array();
                $sessionData['logged_in'] = TRUE;
                $sessionData['user_id'] = $user->user_id;
                $sessionData['email'] = $user->user_email;
                $sessionData['role'] = $user->role->role_name;
                $sessionData['fullname'] = $user->getFullName();
                $sessionData['status'] = $user->status;
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
            $firstAcquired = $mall->acquireUser($user, 'form');

            // if the user is viewing the mall for the 1st time then set the signup activity
            // todo: remove comment if the QA ok'ed this implementation, so it not affect dashboard
            if ($firstAcquired) {
                SignInRecorder::setSignUpActivity($user, 'form', $mall);
            }

            // set sign in activity
            SignInRecorder::setSignInActivity($user, 'form', $mall, NULL, TRUE);
            $this->session->write('visited_location', [$mall->merchant_id]);
            $this->session->write('login_from', 'form');

            // update last visited records
            $user_detail = UserDetail::where('user_id', $user->user_id)->first();
            $user_detail->last_visit_shop_id = $mall->merchant_id;
            $user_detail->last_visit_any_shop = Carbon::now($mall->timezone->timezone_name);
            $user_detail->save();

            $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

            setcookie('orbit_email', $user->user_email, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('orbit_firstname', $user->user_firstname, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
            setcookie('login_from', 'Form', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

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

    public function setAppOrigin($appOrigin)
    {
        $this->appOrigin = $appOrigin;

        return $this;
    }

    protected function acquireUser($retailer, $user, $signUpVia = null, $rewardId = null, $rewardType = null, $language = 'en')
    {
        if (is_null($signUpVia)) {
            $signUpVia = 'form';
            if (isset($_COOKIE['login_from'])) {
                switch (strtolower($_COOKIE['login_from'])) {
                    case 'google':
                        $signUpVia = 'google';
                        break;
                    case 'facebook':
                        $signUpVia = 'facebook';
                        break;
                    default:
                        $signUpVia = 'form';
                        break;
                }
            }

            $signUpVia = $user->isGuest() ? 'guest' : $signUpVia;
        }

        if ($user->isConsumer()) {
            $firstAcquired = $retailer->acquireUser($user, $signUpVia);

            // if the user is viewing the mall for the 1st time then set the signup activity
            if ($firstAcquired) {
                SignInRecorder::setSignUpActivity($user, $signUpVia, $retailer, $rewardId, $rewardType, $language);
            }
        }
    }

    protected function addHttps($url) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

}
