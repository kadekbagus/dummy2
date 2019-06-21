<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing user registration
 */
use \IntermediateBaseController;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Orbit\Helper\Net\GuestUserGenerator;
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
use Queue;
use Orbit\Helper\Net\SignInRecorder;
use \Exception;
use App;
use Event;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;
use Country;

class RegistrationAPIController extends IntermediateBaseController
{
    protected $tmpUserObject = NULL;

    protected $mallId = NULL;

    public function postRegisterCustomer()
    {
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()
            ->setActivityType('registration');

        try {
            $email = trim(OrbitInput::post('email'));
            $password = OrbitInput::post('password');
            $from_mall = OrbitInput::post('from_mall', 'no');
            $firstname = OrbitInput::post('first_name');
            $lastname = OrbitInput::post('last_name');
            $phone = OrbitInput::post('phone');
            $gender = OrbitInput::post('gender');
            $birthdate = OrbitInput::post('birthdate');
            $password_confirmation = OrbitInput::post('password_confirmation');
            $useTransaction = OrbitInput::post('use_transaction', TRUE);
            $language = OrbitInput::get('language', 'id');
            $rewardId = OrbitInput::get('reward_id', NULL);
            $rewardType = OrbitInput::get('reward_type', NULL);
            $redirectToUrl = OrbitInput::post('to_url', NULL);
            $country = OrbitInput::get('country');
            $countryId = OrbitInput::get('country_id');

            $signUpCountryId = $this->getCountryId($country, $countryId);

            $user = User::with('role')
                        ->whereHas('role', function($q) {
                            $q->where('role_name', 'Consumer');
                        })
                        ->excludeDeleted()
                        ->where('user_email', $email);

            $user = $user->first();

            App::setLocale($language);
            if (is_object($user)) {
                $message = Lang::get('validation.orbit.exists.email');
                OrbitShopAPI::throwInvalidArgument($message);
            }
            if ($useTransaction) {
                DB::beginTransaction();
            }

            $user = $this->createCustomerUser($email, $password, $password_confirmation, $firstname, $lastname, $phone, $gender, $birthdate, $this->mallId, FALSE,
                                             NULL, NULL, NULL, NULL, 'form', $signUpCountryId);

            // let mobileci handle it's own session
            if ($this->appOrigin !== 'mobile_ci') {
                try{
                    $this->session->start([], 'no-session-creation');

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
                } catch (\Exception $e) {
                    // Start the orbit session
                    $data = array(
                        'logged_in' => TRUE,
                        'user_id'   => $user->user_id,
                        'email'     => $user->user_email,
                        'role'      => $user->role->role_name,
                        'fullname'  => $user->getFullName(),
                    );

                    $this->session->enableForceNew()->start($data);

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

                Event::fire('orbit.registration.after.createuser', array($user->user_id, $rewardId, $rewardType, $language));

                // Registration_ok activity
                if (! empty($rewardId) && ! empty($rewardType)) {
                    // registration activity that comes from promotional event page
                    $reward = PromotionalEventProcessor::create($user->user_id, $rewardId, $rewardType, $language)->getPromotionalEvent();

                    if (is_object($reward)) {
                        $activity->setActivityType('registration_with_reward')
                            ->setUser($user)
                            ->setLocation(NULL)
                            ->setObject($user)
                            ->setObjectDisplayName($reward->reward_name)
                            ->setActivityName('registration_ok')
                            ->setActivityNameLong('Sign Up via Mobile (Email Address)')
                            ->setNotes($reward->reward_id)
                            ->responseOK()
                            ->setModuleName('Application')
                            ->save();

                        // Login_ok activity
                        $activity_login = Activity::mobileci()
                            ->setActivityType('login_with_reward');
                        $activity_login->setUser($user)
                            ->setActivityName('login_ok')
                            ->setLocation(NULL)
                            ->setActivityNameLong('Sign In')
                            ->setObject($user)
                            ->setObjectDisplayName($reward->reward_name)
                            ->setNotes('Sign In via Mobile (Form) OK')
                            ->setModuleName('Application')
                            ->responseOK()
                            ->save();

                        if ($activity_login) {
                            // Save also activity user sign in in user_signin table
                            SignInRecorder::setSignInActivity($user, 'form', NULL, $activity_login, TRUE, $rewardId, $rewardType, $language);
                        }
                    }
                } else {
                    $activity->setUser($user)
                        ->setObject($user)
                        ->setLocation(NULL)
                        ->setActivityName('registration_ok')
                        ->setActivityNameLong('Sign Up via Mobile (Email Address)')
                        ->setNotes('Sign Up via Mobile (Email Address) OK')
                        ->responseOK()
                        ->setModuleName('Application')
                        ->save();

                    // Login_ok activity
                    $activity_login = Activity::mobileci()
                        ->setActivityType('login');
                    $activity_login->setUser($user)
                        ->setLocation(NULL)
                        ->setActivityName('login_ok')
                        ->setActivityNameLong('Sign In')
                        ->setObject($user)
                        ->setNotes('Sign In via Mobile (Form) OK')
                        ->setModuleName('Application')
                        ->responseOK()
                        ->save();

                    if ($activity_login) {
                        // Save also activity user sign in in user_signin table
                        SignInRecorder::setSignInActivity($user, 'form', NULL, $activity_login, TRUE);
                    }
                }
            }

            $user->redirect_to_url = $redirectToUrl;
            $this->response->data = $user;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Sign Up Success';

            if ($useTransaction) {
                DB::commit();
            }

            // Send email process to the queue
            Queue::push('Orbit\\Queue\\RegistrationMail', [
                'user_id' => $user->user_id,
                'languageId' => $language,
                'mode' => 'gotomalls',
                'redirect_to_url' => $redirectToUrl
                ],
                Config::get('orbit.registration.mobile.queue_name', 'gtm_email')
            );
        } catch (ACLForbiddenException $e) {
            if ($useTransaction) {
                DB::rollback();
            }
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        } catch (InvalidArgsException $e) {
            if ($useTransaction) {
                DB::rollback();
            }
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        } catch (\Exception $e) {
            if ($useTransaction) {
                DB::rollback();
            }
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed()
                     ->setModuleName('Application')->save();
        }

        return $this->render($this->response);
    }

    /**
     * Creates a customer user and the associated user detail and API key objects.
     *
     * May throw exception if cannot find retailer or cannot find consumer role.
     *
     * Uses retailer set using setRetailerId() or the one in config.
     *
     * @param string $email the user's email
     * @param string|null $userId the unique ID (if provided) of the user to create - used in box to match data on cloud
     * @param string|null $userDetailId .... of the user detail to create - used in box to match data on cloud
     * @param string|null $apiKeyId .... of the API key to create - used in box to match data on cloud
     * @param string|null $userStatus the user status on cloud
     * @return array [User, UserDetail, ApiKey]
     * @throws Exception
     */
    public function createCustomerUser($email, $password, $password_confirmation, $firstname, $lastname, $phone, $gender, $birthdate, $mall_id = NULL,
        $useTransaction = TRUE, $userId = null, $userDetailId = null, $apiKeyId = null, $userStatus = null, $from = 'form', $signUpCountryId = null)
    {
        $validation = TRUE;
        if ($from === 'form') {
            // if coming from form then validate password and birthdate
            $validation = $this->validateRegistrationData($email, $firstname, $lastname, $phone, $gender, $birthdate, $password, $password_confirmation);
        }
        if ($validation) {
            try {
                $customerRole = Role::where('role_name', 'Consumer')->first();
                if (empty($customerRole)) {
                    $errorMessage = Lang::get('validation.orbit.empty.consumer_role');
                    throw new Exception($errorMessage);
                }
                if ($useTransaction) {
                    DB::beginTransaction();
                }
                $new_user = new User();
                if (isset($userId)) {
                    $new_user->user_id = $userId;
                }
                $new_user->username = strtolower($email);
                $new_user->user_email = strtolower($email);
                if (! empty($password)) {
                    $new_user->user_password = Hash::make($password);
                }
                $new_user->user_firstname = $firstname;
                $new_user->user_lastname = $lastname;
                $new_user->status = isset($userStatus) ? $userStatus : 'pending';
                $new_user->user_role_id = $customerRole->role_id;
                $new_user->user_ip = $_SERVER['REMOTE_ADDR'];
                $new_user->external_user_id = 0;
                $new_user->sign_up_country_id = $signUpCountryId;

                $new_user->save();

                $user_detail = new UserDetail();

                if (isset($userDetailId)) {
                    $user_detail->user_detail_id = $userDetailId;
                }
                if (! is_null($mall_id)) {
                    $user_detail->merchant_id = $mall_id;
                }
                $user_detail->gender = substr($gender, 0, 1) === 'm' ? 'm' : (substr($gender, 0, 1) === 'f' ? 'f' : NULL);
                if (! empty($birthdate)) {
                    $user_detail->birthdate = date('Y-m-d', strtotime($birthdate));
                }

                // Add phone for signup
                $user_detail->phone = $phone;

                // Save the user details
                $user_detail = $new_user->userdetail()->save($user_detail);

                // Generate API key for this user (with given ID if specified)
                $apikey = $new_user->createApiKey($apiKeyId);

                $new_user->setRelation('userDetail', $user_detail);
                $new_user->user_detail = $user_detail;

                $new_user->setRelation('apikey', $apikey);
                $new_user->apikey = $apikey;

                if ($useTransaction) {
                    DB::commit();
                }

                $new_user->load('role');

                return $new_user;
            } catch (Exception $e) {
                if (! $useTransaction) {
                    DB::rollback();
                }
                $this->response->code = $e->getCode();
                $this->response->status = 'error';
                $this->response->message = $e->getMessage();
                $this->response->data = null;

                return $this->render($this->response);
            }
        } else {
            return $validation;
        }
    }

    /**
     * Validate the registration data.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param string $email Consumer's email
     * @return array|string
     * @throws Exception
     */
    protected function validateRegistrationData($email, $firstname, $lastname, $phone, $gender, $birthdate, $password, $password_confirmation)
    {
        $me = $this;
        Validator::extend('orbit_email_exists', function ($attribute, $value, $parameters) use ($me) {
            $user = User::excludeDeleted()
                ->where('user_email', $value)
                ->whereHas('role', function($q) {
                    $q->where('role_name', 'Consumer');
                })
                ->first();

            if (is_object($user)) {
                return FALSE;
            }

            $me->tmpUserObject = $user;

            return TRUE;
        });

        $current_date = date('Y-m-d');
        $validator = Validator::make(
            array(
                'email'      => $email,
            ),
            array(
                'email'      => 'required|email|orbit_email_exists',
            ),
            array(
                'date_of_birth.date_format' => Lang::get('validation.orbit.formaterror.date.dmy_date'),
                'orbit_email_exists' => Lang::get('validation.orbit.email.exists'),
                'date_of_birth.date' => Lang::get('validation.orbit.formaterror.date.invalid_date'),
                'date_of_birth.before' => Lang::get('validation.orbit.formaterror.date.cannot_future_date'),
                'password_confirmation.min' => Lang::get('validation.orbit.formaterror.min'),
                'password.confirmed' => Lang::get('validation.orbit.formaterror.confirmed_password'),
                'email' => Lang::get('validation.orbit.formaterror.sign_up.email'),
            )
        );

        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        return TRUE;
    }

    public function setMallId($mallId)
    {
        $this->mallId = $mallId;

        return $this;
    }

    public function setAppOrigin($appOrigin)
    {
        $this->appOrigin = $appOrigin;

        return $this;
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
