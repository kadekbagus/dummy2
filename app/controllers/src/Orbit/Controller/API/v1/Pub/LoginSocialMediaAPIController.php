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
use Country;

class LoginSocialMediaAPIController extends IntermediateBaseController
{
    /**
     * this is for user login from social media such as facebook and google
     * which doesn't have password
     *
     * @author kadek <kadek@dominopos.com>
     */
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
            $phone = OrbitInput::post('phone', NULL);
            $socialid = OrbitInput::post('socialId', '');
            $country = OrbitInput::get('country');
            $countryId = OrbitInput::get('country_id');

            $signUpCountryId = $this->getCountryId($country, $countryId);

            if (trim($userEmail) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($type) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'type'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $loggedInUser = $this->doAutoLogin($userEmail);
            if (! is_object($loggedInUser)) {
                // register user without password and birthdate
                $status = 'active';
                $response = (new Regs())->createCustomerUser($userEmail, NULL, NULL, $firstName, $lastName, $phone, $gender, NULL, NULL, TRUE, NULL, NULL, NULL, $status, ucfirst($type), $signUpCountryId);
                if (get_class($response) !== 'User') {
                    throw new Exception($response->message, $response->code);
                }

                // create registration_ok activity
                SignInRecorder::setSignUpActivity($response, $type, NULL, $rewardId, $rewardType, $language);
                // create activation_ok activity without using token
                $activation_ok = ActivationAPIController::create('raw')
                    ->setSaveAsAutoActivation($response, $type)
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

            SignInRecorder::setSignInActivity($loggedInUser, $type, NULL, NULL, TRUE, $rewardId, $rewardType, $language);

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

    public function getCountryId($countryName = null, $countryId = null)
    {
        if (!isset($countryId) && isset($countryName)) {
            $country = Country::where('name', '=', $countryName)->first();
            $countryId = ($country) ? $country->country_id : null;
        }
        return $countryId;
    }
}