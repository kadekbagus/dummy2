<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing user sign in.
 */
use \IntermediateBaseController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
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
use \Exception;

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
            $from_mall = OrbitInput::post('from_mall', 'no');
            // $mode = OrbitInput::post('mode', 'login');

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // if (! in_array($mode, $modes)) {
            //     $mode = 'login';
            // }

            // Return the current mall object if this login process coming from the mobileci
            // todo: check later for login from mall
            $from_mall = $from_mall === 'yes' ? TRUE : FALSE;
            $mall_id = NULL;
            $mall = NULL;

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

    public function getGoogleCallbackView()
    {
        $oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $recognized = \Input::get('recognized', 'none');
        $code = \Input::get('code', NULL);
        $state = \Input::get('state', NULL);
        $caller_url = OrbitInput::get('from_url', NULL);
        $caller_url = ! is_null($caller_url) ? URL::route($caller_url) : Config::get('orbit.shop.after_social_sign_in');
        $redirect_to_url = OrbitInput::get('to_url', URL::route('ci-customer-home'));
        $mall_id = OrbitInput::get('mid', NULL);

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
                // from mall = yes, indicate the request coming from Mall CI, then use MobileCIAPIController::getGoogleCallbackView
                // to set the session and other things
                if (! empty($mall_id_from_state)) {
                    $_GET['caller_url'] = $caller_url;
                    $_GET['redirect_to_url'] = $redirect_to_url;
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
                        $parsed_caller_url = parse_url((string)$caller_url);
                        if (isset($parsed_caller_url['query'])) {
                            $caller_url .= '&error=no_email';
                        } else {
                            $caller_url .= '?error=no_email';
                        }

                        return Redirect::to($caller_url);
                    }

                    $loggedInUser = $this->doAutoLogin($userEmail);
                    if (! is_object($loggedInUser)) {
                        // register user without password and birthdate
                        $status = 'active';
                        $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $gender, NULL, TRUE, NULL, NULL, NULL, $status, 'Google');
                        if (get_class($response) !== 'User') {
                            throw new Exception($response->message, $response->code);
                        }

                        $loggedInUser = $this->doAutoLogin($response->user_email);
                    }

                    $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

                    setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                    setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                    return Redirect::to(Config::get('orbit.shop.after_social_sign_in'));
                }
            } catch (Exception $e) {
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
                $state_array = array('redirect_to_url' => $redirect_to_url, 'mid' => $mall_id);
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

    public function getSocialLoginCallbackView()
    {
        $recognized = \Input::get('recognized', 'none');
        // error=access_denied&
        // error_code=200&
        // error_description=Permissions+error
        // &error_reason=user_denied
        // &state=28d0463ac4dc53131ae19826476bff74#_=_
        $error = \Input::get('error', NULL);

        $city = '';
        $country = '';

        if (! is_null($error)) {
            return $this->getFacebookError();
        }

        $orbit_origin = \Input::get('orbit_origin', 'facebook');
        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('application_id', static::APPLICATION_ID);

        try {
            $this->session = new OrbitSession($config);
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
            return Redirect::to(Config::get('orbit.shop.after_social_sign_in') . '/#/?error=no_email');
        }

        $loggedInUser = $this->doAutoLogin($userEmail);
        if (! is_object($loggedInUser)) {
            // register user without password and birthdate
            $status = 'active';
            $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $gender, NULL, TRUE, NULL, NULL, NULL, $status, 'Facebook');
            if (get_class($response) !== 'User') {
                throw new Exception($response->message, $response->code);
            }

            $loggedInUser = $this->doAutoLogin($response->user_email);
        }

        $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

        setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
        setcookie('login_from', 'Facebook', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

        // todo can we not do this directly
        return Redirect::to(Config::get('orbit.shop.after_social_sign_in'));
    }

    /**
     * Handles social login POST
     */
    public function postSocialLoginView()
    {
        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('application_id', static::APPLICATION_ID);

        try {
            $this->session = new OrbitSession($config);
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
        $facebookCallbackUrl = URL::route('pub.social_login_callback', ['orbit_origin' => 'facebook', 'from_captive' => OrbitInput::post('from_captive'), 'mac_address' => \Input::get('mac_address', '')]);

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
}
