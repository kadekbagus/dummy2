<?php
/**
 * An API controller for login user.
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class LoginAPIController extends ControllerAPI
{
    /** @var string|null the retailer ID (may be force set in cloud to match ID of requesting box) */
    private $retailerId = null;

    /**
     * POST - Login user
     *
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogin()
    {
        $activity = Activity::portal()
                            ->setActivityType('login');
        try {
            $email = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                        ->active()
                        ->where('user_email', $email)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign in')
                     ->responseOK();

            $this->response->data = $user;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    /**
     * POST - Login Admin User
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginAdmin()
    {
        return $this->postLoginRole(['super admin']);
    }

    /**
     * POST - Login Mall Owner or Admin User
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginMall()
    {
        $_GET['from_portal'] = 'mall';
        return $this->postLoginRole(['Super Admin', 'Mall Owner', 'Mall Admin']);
    }

    /**
     * POST - Login Mall Customer Service
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginMallCustomerService()
    {
        $_GET['from_portal'] = 'cs-portal';
        return $this->postLoginRole(['Mall Customer Service']);
    }

    /**
     * POST - Login Mall Consumer
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @return Illuminate\Support\Facades\Response
     */
    public function postLoginCustomer()
    {
        return $this->postLoginRole(['Consumer']);
    }

    /**
     * POST - Logout user
     *
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postLogout()
    {
        // There is no exactly logout proses on the backend API
        return $this->render();
    }

    /**
     * POST - Register new customer
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                     (required) - Email address of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postRegisterUserInShop()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('registration');
        try {
            $httpCode = 200;

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');

            $validator = Validator::make(
                array(
                    'email'     => $email,
                ),
                array(
                    'email'     => 'required|email|orbit.email.exists',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            list($newuser, $userdetail, $apikey) = $this->createCustomerUser($email);

            $this->response->data = $newuser;

            // Commit the changes
            if (Config::get('orbit.registration.mobile.fake') !== TRUE) {
                $this->commit();
            }

            // Successfull registration
            $activity->setUser($newuser)
                     ->setActivityName('registration_ok')
                     ->setActivityNameLong('Sign Up')
                     ->setModuleName('Application')
                     ->responseOK();

            // Send email process to the queue
            Queue::push('Orbit\\Queue\\RegistrationMail', [
                'user_id' => $newuser->user_id
            ]);

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            // Rollback the changes
            $this->rollBack();

            // Failed Registration
            $activity->setUser('guest')
                     ->setActivityName('registration_failed')
                     ->setActivityNameLong('Registration Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

        // We want the registration activity to have 'from Facebook' or 'from Email'...
        // Rather than passing the origin here, we save the ID of the registration activity
        // so the caller can add 'from Facebook' later.
        if ($activity->response_status == Activity::ACTIVITY_REPONSE_OK) {
            $this->response->data->setAttribute('registration_activity_id', $activity->activity_id);
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Setup Password By Token
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`                     (required) - Token to be check
     * @param string    `password`                  (required) - Password for the account
     * @param string    `password_confirmation`     (required) - Confirmation
     * @return Illuminate\Support\Facades\Response
     */
    public function postSetupPasswordByToken()
    {
        $activity = Activity::portal()
                            ->setActivityType('activation');
        try {
            $this->registerCustomValidation();

            $tokenValue = trim(OrbitInput::post('token'));
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');

            $validator = Validator::make(
                array(
                    'token_value'   => $tokenValue,
                    'password'      => $password,
                    'password_confirmation' => $password2
                ),
                array(
                    'token_value'   => 'required|orbit.empty.token',
                    'password'      => 'required|min:5|confirmed',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.token');
            $user = User::with('userdetail')
                        ->excludeDeleted()
                        ->where('user_id', $token->user_id)
                        ->first();

            if (! is_object($token) || ! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

            // Update user password and activate them
            $user->user_password = Hash::make($password);
            $user->status = 'active';
            $user->save();

            $this->response->message = Lang::get('statuses.orbit.updated.your_password');
            $this->response->data = $user;

            if (Config::get('orbit.registration.mobile.send_welcome_email') === TRUE) {
                // Sign page link
                $signinUrl = Config::get('orbit.registration.mobile.signin_url');

                $data = array(
                    'email'         => $user->user_email,
                    'password'      => $password,
                    'signin_url'    => $signinUrl
                );
                $mailviews = array(
                    'html' => 'emails.registration.activated-html',
                    'text' => 'emails.registration.activated-text'
                );
                Mail::send($mailviews, $data, function($message) use ($user)
                {
                    $emailconf = Config::get('orbit.registration.mobile.sender');
                    $from = $emailconf['email'];
                    $name = $emailconf['name'];

                    $message->from($from, $name)->subject('Your Account on Orbit has been Activated!');
                    $message->to($user->user_email);
                });
            }

            // Commit the changes
            $this->commit();

            // Successfull activation
            $activity->setUser($user)
                     ->setActivityName('activation_ok')
                     ->setActivityNameLong('Account Activation')
                     ->setModuleName('Application')
                     ->responseOK();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render();
    }

    /**
     * Post - Update Service Agreement
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`          (required) - Token to be check
     * @param string    `first_name`     (required) - value of first name
     * @param string    `last_name`      (required) - value of last name
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateServiceAgreement()
    {
        $activity = Activity::portal()
                            ->setActivityType('activation');
        try {
            $this->registerCustomValidation();

            $tokenValue = trim(OrbitInput::post('token'));
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');

            $validator = Validator::make(
                array(
                    'token_value'   => $tokenValue,
                    'first_name'    => $first_name,
                    'last_name'     => $last_name
                ),
                array(
                    'token_value'   => 'required|orbit.empty.token',
                    'first_name'    => 'required|min:3',
                    'last_name'     => 'required|min:3',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.token');
            $user  = User::excludeDeleted()
                        ->where('user_id', $token->user_id)
                        ->first();
            $mall = Mall::excludeDeleted()->where('user_id', $token->user_id)->first();

            if (! is_object($token) || ! is_object($user) || ! is_object($mall)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

            // Update settings service agreement
            $settings = $mall->settings()
                             ->where('object_id', $mall->merchant_id)
                             ->where('object_type', 'merchant')
                             ->get();

            foreach ($settings as $idx => $setting) {
                if ($setting->setting_name === 'agreement_accepted') {
                    $setting->setting_value = 'true';
                }
                if ($setting->setting_name === 'agreement_acceptor_first_name') {
                    $setting->setting_value = $first_name;
                }
                if ($setting->setting_name === 'agreement_acceptor_last_name') {
                    $setting->setting_value = $last_name;
                }
            }

            $mall->setRelation('settings', $settings);

            $this->response->message = Lang::get('statuses.orbit.updated.serviceagreement');
            $this->response->data = $mall;

            // Commit the changes
            $this->commit();

            // Successfull activation
            $activity->setUser($user)
                     ->setActivityName('service_agreement')
                     ->setActivityNameLong('Update Service Agreement Successfull')
                     ->setModuleName('Application')
                     ->responseOK();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('mall')
                     ->setActivityName('service_agreement_failed')
                     ->setActivityNameLong('Update Service Agreement Failed')
                     ->setModuleName('Application')
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('mall')
                     ->setActivityName('service_agreement_failed')
                     ->setActivityNameLong('Update Service Agreement Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('mall')
                     ->setActivityName('service_agreement_failed')
                     ->setActivityNameLong('Update Service Agreement Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render();
    }

    /**
     * POST - Activate Account
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `first_name`     (required) - first name
     * @param string    `last_name`      (required) - last name
     * @param string    `birthdate`      (required) - date of birth date
     * @param string    `gender`         (required) - gender 'm','f','unknown'
     * @param string    `token`          (required) - Token to be check
     * @return Illuminate\Support\Facades\Response
     */
    public function postActivateAccount()
    {
        $activity = Activity::portal()
                            ->setActivityType('activation');
        try {
            $this->registerCustomValidation();

            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $birthdate = OrbitInput::post('birthdate');
            $gender = OrbitInput::post('gender');
            $token = trim(OrbitInput::post('token'));

            $validator = Validator::make(
                array(
                    'first_name'     => $first_name,
                    'last_name'      => $last_name,
                    'birthdate'      => $birthdate,
                    'gender'         => $gender,
                    'token'          => $token,
                ),
                array(
                    'first_name'     => 'required|min:3',
                    'last_name'      => 'required|min:3',
                    'birthdate'      => 'required|date_format:Y-m-d H:i:s',
                    'gender'         => 'required|in:m,f',
                    'token'          => 'required|orbit.empty.token',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.token');
            $user = User::excludeDeleted()
                        ->where('user_id', $token->user_id)
                        ->first();

            if (! is_object($token) || ! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

            // Update user first, last name and status
            $user->user_firstname = $first_name;
            $user->user_lastname = $last_name;
            $user->status = 'active';
            $user->save();

            // Update user detail birthdate and gender
            $user_detail = UserDetail::where('user_id', $user->user_id)
                                     ->first();
            $user_detail->birthdate = $birthdate;
            $user_detail->gender = $gender;

            // Save the user details
            $user_detail->save();

            // @author Irianto Pratama <irianto@dominopos.com>
            // send email if user status active
            if ($user->status === 'active') {
                // Send email process to the queue
                \Queue::push('Orbit\\Queue\\NewPasswordMail', [
                    'user_id' => $user->user_id
                ]);
            }

            $this->response->message = Lang::get('statuses.orbit.activate.account');
            $this->response->data = $user;

            // Commit the changes
            $this->commit();

            // Successfull activation
            $activity->setUser($user)
                     ->setActivityName('activation_ok')
                     ->setActivityNameLong('Account Activation')
                     ->setModuleName('Application')
                     ->responseOK();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Activation
            $activity->setUser('guest')
                     ->setActivityName('activation_failed')
                     ->setActivityNameLong('Account Activation Failed')
                     ->setModuleName('Application')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render();
    }

    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

        // Check the existance of token
        Validator::extend('orbit.empty.token', function ($attribute, $value, $parameters) {
            $token = Token::active()->NotExpire()->where('token_value', $value)->first();

            if (empty($token)) {
                return FALSE;
            }

            App::instance('orbit.empty.token', $token);

            return TRUE;
        });
    }

    /**
     * Role based login.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    protected function postLoginRole($roles=[])
    {
        $activity = Activity::portal()
                            ->setActivityType('login');
        try {
            $email = trim(OrbitInput::post('email'));
            $password = trim(OrbitInput::post('password'));

            if (trim($email) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (trim($password) === '') {
                $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // check for superadmin login as user
            $loginFromSuperadmin = false;
            // superadmin@domain logs in as user@domain using an email of this format:
            // superadmin@domain-----user--AT--domain
            $emailDelimiter = '-----';
            $emailAtReplacement = '--AT--';
            if (strpos($email, $emailDelimiter) !== FALSE) {
                $parts = explode($emailDelimiter, $email);
                if (count($parts) !== 2) {
                    // something odd?
                    $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $adminEmail = $parts[0];
                $loginAsEmail = $parts[1];
                if (strpos($loginAsEmail, $emailAtReplacement) === FALSE) {
                    // no --AT-- in second part
                    $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // check for super admin user first
                $superAdminUser = User::with('role')
                    ->active()
                    ->where('user_email', $adminEmail)
                    ->first();

                if (! is_object($superAdminUser)) {
                    $message = Lang::get('validation.orbit.access.inactiveuser');
                    ACL::throwAccessForbidden($message);
                }

                if (! Hash::check($password, $superAdminUser->user_password)) {
                    $message = Lang::get('validation.orbit.access.loginfailed');
                    ACL::throwAccessForbidden($message);
                }

                $roleIds = Role::roleIdsByName(['Super Admin']);
                if (! in_array($superAdminUser->user_role_id, $roleIds)) {
                    $message = Lang::get('validation.orbit.access.forbidden', [
                        'action' => 'Login (Role Denied) ' . implode(', ', $roleIds) . ' ' . $superAdminUser->user_role_id
                    ]);
                    ACL::throwAccessForbidden($message);
                }

                // then if the superadmin checks pass, use the user email specified
                $email = str_replace($emailAtReplacement, '@', $loginAsEmail);
                $loginFromSuperadmin = true;
            }

            $user = User::with('role')
                        ->active()
                        ->where('user_email', $email)
                        ->first();

            if (! is_object($user)) {
                $message = Lang::get('validation.orbit.access.inactiveuser');
                ACL::throwAccessForbidden($message);
            }

            if (! $loginFromSuperadmin && ! Hash::check($password, $user->user_password)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            $roleIds = Role::roleIdsByName($roles);
            if (! in_array($user->user_role_id, $roleIds)) {
                $message = Lang::get('validation.orbit.access.forbidden', [
                    'action' => 'Login (Role Denied)'
                ]);
                ACL::throwAccessForbidden($message);
            }

            // Return the current mall object if this login process coming
            // from mall or cs-portal
            $from = OrbitInput::get('from_portal', NULL);
            $mall = NULL;

            if (in_array($from, ['mall', 'cs-portal'])) {
                if ($from === 'mall') {
                    if (strtolower($user->role->role_name) === 'mall admin') {
                        $mall = $user->employee->retailers[0];
                    } else {
                        $mall = Mall::excludeDeleted()->where('user_id', $user->user_id)->first();
                    }
                } elseif ($from === 'cs-portal') {
                    $mall = $user->employee->retailers[0];
                }
            }
            $user->mall = $mall;

            // Successfull login
            $activity->setUser($user)
                     ->setActivityName('login_ok')
                     ->setActivityNameLong('Sign in')
                     ->responseOK();

            $this->response->data = $user;

            if ($from === 'mall') {
                // @author Irianto Pratama <irianto@dominopos.com>
                $agreement_accepted = $mall->settings()
                                           ->where('setting_name', 'agreement_accepted')
                                           ->where('setting_value', 'true')
                                           ->where('object_id', $mall->merchant_id)
                                           ->where('object_type', 'merchant')
                                           ->first();

                if (empty($agreement_accepted) || $agreement_accepted->setting_value !== 'true') {

                    // Token expiration, fallback to 30 days
                    $expireInDays = Config::get('orbit.registration.mobile.activation_expire', 30);

                    // Token Settings
                    $token = new Token();
                    $token->token_name = 'service_agreement';
                    $token->token_value = $token->generateToken($user->user_email);
                    $token->status = 'active';
                    $token->email = $user->user_email;
                    $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
                    $token->ip_address = $user->user_ip;
                    $token->user_id = $user->user_id;
                    $token->save();

                    $this->response->code = 302;
                    $this->response->status = 'redirect';
                    $this->response->message = Lang::get('validation.orbit.access.agreement');
                    $this->response->data = Config::get('orbit.agreement.url') . $token->token_value;
                }
            }
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser('guest')
                     ->setActivityName('login_failed')
                     ->setActivityNameLong('Login Failed')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    /**
     * @return mixed
     */
    protected function getRetailerId()
    {
        if (isset($this->retailerId)) {
            return $this->retailerId;
        }
        return Config::get('orbit.shop.id');
    }

    /**
     * @param $id
     * @return self
     */
    public function setRetailerId($id)
    {
        $this->retailerId = $id;
        return $this;
    }

    /**
     * Creates a customer user and the associated user detail and API key objects.
     *
     * NOT transactional, wrap in transaction yourself.
     *
     * May throw exception if cannot find retailer or cannot find consumer role.
     *
     * Uses retailer set using setRetailerId() or the one in config.
     *
     * @param string $email the user's email
     * @param string|null $userId the unique ID (if provided) of the user to create - used in box to match data on cloud
     * @param string|null $userDetailId .... of the user detail to create - used in box to match data on cloud
     * @param string|null $apiKeyId .... of the API key to create - used in box to match data on cloud
     * @return array [User, UserDetail, ApiKey]
     * @throws Exception
     */
    public function createCustomerUser($email, $userId = null, $userDetailId = null, $apiKeyId = null)
    {
        // The retailer (shop) which this registration taken
        $retailerId = $this->getRetailerId();
        $retailer = Mall::excludeDeleted()
            ->where('merchant_id', $retailerId)
            ->first();

        if (empty($retailer)) {
            $errorMessage = Lang::get('validation.orbit.empty.retailer');
            throw new Exception($errorMessage);
        }

        $customerRole = Role::where('role_name', 'Consumer')->first();
        if (empty($customerRole)) {
            $errorMessage = Lang::get('validation.orbit.empty.consumer_role');
            throw new Exception($errorMessage);
        }

        $new_user = new User();
        if (isset($userId)) {
            $new_user->user_id = $userId;
        }
        $new_user->username = strtolower($email);
        $new_user->user_email = strtolower($email);
        $new_user->status = 'pending';
        $new_user->user_role_id = $customerRole->role_id;
        $new_user->user_ip = $_SERVER['REMOTE_ADDR'];
        $new_user->external_user_id = 0;

        $new_user->save();

        $user_detail = new UserDetail();

        if (isset($userDetailId)) {
            $user_detail->user_detail_id = $userDetailId;
        }
        // Fill the information about retailer (shop)
        $user_detail->merchant_id = $retailer->parent_id;
        $user_detail->merchant_acquired_date = date('Y-m-d H:i:s');
        $user_detail->retailer_id = $retailer->merchant_id;

        // Save the user details
        $user_detail = $new_user->userdetail()->save($user_detail);

        // Generate API key for this user (with given ID if specified)
        $apikey = $new_user->createApiKey($apiKeyId);

        $new_user->setRelation('userDetail', $user_detail);
        $new_user->user_detail = $user_detail;

        $new_user->setRelation('apikey', $apikey);
        $new_user->apikey = $apikey;
        return [$new_user, $user_detail, $apikey];
    }
}
