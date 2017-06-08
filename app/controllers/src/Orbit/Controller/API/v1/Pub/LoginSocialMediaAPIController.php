<?php namespace Orbit\Controller\API\v1\Pub;

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

class LoginSocialMediaAPIController extends IntermediateBaseController
{
    public function postLoginSocialMedia()
    {
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()
                        ->setActivityType('login');
        try {
            $type = OrbitInput::post('type');
            $rewardId = OrbitInput::get('reward_id', NULL);
            $rewardType = OrbitInput::get('reward_type', NULL);
            $language = OrbitInput::get('language', 'id');
            $userEmail = OrbitInput::post('userEmail', '');
            $firstName = OrbitInput::post('firstName', '');
            $lastName = OrbitInput::post('lastName', '');
            $gender = OrbitInput::post('gender', '');
            $socialid = OrbitInput::post('socialId', '');

            if (trim($userEmail) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

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
            }

            // promotional event reward event
            DB::beginTransaction();
            Event::fire('orbit.login.after.success', array($loggedInUser->user_id, $rewardId, $rewardType, $language));
            DB::commit();

            SignInRecorder::setSignInActivity($loggedInUser, 'facebook', NULL, NULL, TRUE, $rewardId, $rewardType, $language);

            // Send the session id via HTTP header
            $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
            $sessionHeader = 'Set-' . $sessionHeader;
            $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

            $this->response->data = $loggedInUser;
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
            $this->response->message = $e->getLine();
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

        $userEmail = OrbitInput::post('userEmail', '');
        $firstName = OrbitInput::post('firstName', '');
        $lastName = OrbitInput::post('lastName', '');
        $gender = OrbitInput::post('gender', '');
        $socialid = OrbitInput::post('socialId', '');
        $useExtended = false; //Config::get('orbit.social.facebook.use_extended_perms');

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
        }

        // promotional event reward event
        DB::beginTransaction();
        Event::fire('orbit.login.after.success', array($loggedInUser->user_id, $rewardId, $rewardType, $language));
        DB::commit();

        SignInRecorder::setSignInActivity($loggedInUser, 'facebook', NULL, NULL, TRUE, $rewardId, $rewardType, $language);

        // Send the session id via HTTP header
        $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
        $sessionHeader = 'Set-' . $sessionHeader;
        $this->customHeaders[$sessionHeader] = $this->session->getSessionId();

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
        }

        return $loggedInUser;
    }


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
            $email = trim(OrbitInput::post('userEmail'));
            $password = trim(OrbitInput::post('password'));
            $mall_id = OrbitInput::post('mall_id', null);
            $rewardId = OrbitInput::get('reward_id', null);
            $rewardType = OrbitInput::get('reward_type', null);
            $language = OrbitInput::get('language', 'id');
            // $mode = OrbitInput::post('mode', 'login');

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // if (trim($password) === '') {
            //     $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
            //     OrbitShopAPI::throwInvalidArgument($errorMessage);
            // }

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

            // if (! Hash::check($password, $user->user_password)) {
            //     $message = Lang::get('validation.orbit.access.loginfailed');
            //     OrbitShopAPI::throwInvalidArgument($message);
            // }

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
}