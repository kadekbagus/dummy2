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

            switch ($type) {
                case 'facebook':
                    $social = $this->getSocialLoginCallbackView();
                    break;

                case 'google':
                    $social = '';

                default:
                    $message = sprintf('social login is not found.', $type);
                    throw new Exception($message);
            }

            $this->response->data = $social;

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
}