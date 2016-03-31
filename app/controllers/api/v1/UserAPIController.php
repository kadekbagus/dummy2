<?php
/**
 * An API controller for managing user.
 */
use Orbit\CloudMAC;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Email\MXEmailChecker;

class UserAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;
    protected $detailYes = FALSE;

    /**
     * POST - Create new user
     *
     * @author <Ahmad Anshori> <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`                 (required) - Email address of the user
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `role_id`               (required) - Role ID
     * @param string    `firstname`             (optional) - User first name
     * @param string    `lastname`              (optional) - User last name
     * @param boolean   `new_consumer_from_captive` (optional) - if 'Y' then role is always consumer, password random
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewUser()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newuser = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postnewuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postnewuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postnewuser.before.authz', array($this, $user));

            if (! ACL::create($user)) {
                Event::fire('orbit.user.postnewuser.authz.notallowed', array($this, $user));
                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postnewuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $user_role_id = OrbitInput::post('role_id');
            $is_new_consumer_from_captive = OrbitInput::post('new_consumer_from_captive', 'N') === 'Y';

            if ($is_new_consumer_from_captive) {
                $mallId = Config::get('orbit.shop.id');

                if (empty($mallId)) {
                    $domainName = $_SERVER['HTTP_HOST'];
                    Log::info( sprintf('-- API/USER/NEW -- Missing current_mall trying to guess from %s', $domainName) );

                    // try to guess from domain name
                    $mallFromDomain = Setting::getMallByDomain($domainName);
                    Log::info( sprintf('-- API/USER/NEW -- Mall ID/Name get from guessing: %s/%s', $mallFromDomain->merchant_id, $mallFromDomain->name));

                    if (! $mallFromDomain) {
                        $errorMessage = 'Mall is not found.';
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                    $mallId = $mallFromDomain->merchant_id;
                }

                $validator = Validator::make(
                    array(
                        'email'     => $email,
                    ),
                    array(
                        'email'     => 'required|email|orbit.email.exists:' . $mallId,
                    )
                );
            } else {
                $mallId = OrbitInput::post('current_mall');;
                $validator = Validator::make(
                    array(
                        'current_mall'  => $mallId,
                        'email'         => $email,
                        'password'      => $password,
                        'password_confirmation' => $password2,
                        'role_id'       => $user_role_id,
                    ),
                    array(
                        'current_mall'  => 'required|orbit.empty.mall',
                        'email'         => 'required|email|orbit.email.exists:' . $mallId,
                        'password'      => 'required|min:6|confirmed',
                        'role_id'       => 'required|orbit.empty.role',
                    )
                );
            }


            Event::fire('orbit.user.postnewuser.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // we need this for the registration activity.
            $captive_location = null;
            if ($is_new_consumer_from_captive) {
                $captive_location = Mall::excludeDeleted()->where('user_id', '=', $user->user_id)->first();

                if (! isset($captive_location)) {
                    OrbitShopAPI::throwInvalidArgument('cannot find captive portal location');
                }
            }

            Event::fire('orbit.user.postnewuser.after.validation', array($this, $validator));

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->status = 'pending';
            if ($is_new_consumer_from_captive) {
                $consumer_role = Role::where('role_name', '=', 'consumer')->first();
                if ($consumer_role === null) {
                    OrbitShopAPI::throwInvalidArgument('consumer role not found');  // should never happen?
                }
                $newuser->user_role_id = $consumer_role->role_id;
                $newuser->user_password = Hash::make(mcrypt_create_iv(32));  // just some random password
            } else {
                $newuser->user_role_id = $user_role_id;
                $newuser->user_password = Hash::make($password);
            }

            $newuser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newuser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postnewuser.before.save', array($this, $newuser));

            $newuser->save();

            $userdetail = new UserDetail();
            $userdetail = $newuser->userdetail()->save($userdetail);

            $newuser->setRelation('userdetail', $userdetail);
            $newuser->userdetail = $userdetail;

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newuser);
            $apikey->api_secret_key = Apikey::genSecretKey($newuser);
            $apikey->status = 'active';
            $apikey->user_id = $newuser->user_id;
            $apikey = $newuser->apikey()->save($apikey);

            $newuser->setRelation('apikey', $apikey);
            $newuser->apikey = $apikey;
            $newuser->setHidden(array('user_password'));

            Event::fire('orbit.user.postnewuser.after.save', array($this, $newuser));
            $this->response->data = $newuser;

            $acq = new UserAcquisition();
            $acq->user_id = $newuser->user_id;
            $acq->acquirer_id = $mallId;
            // @todo remove this hardcoded value of signup_via
            $acq->signup_via = 'form';
            $acq->save();

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('User Created: %s', $newuser->username);
            $activity->setUser($user)
                    ->setActivityName('create_user')
                    ->setActivityNameLong('Create User OK')
                    ->setObject($newuser)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.user.postnewuser.after.commit', array($this, $newuser));

            if ($is_new_consumer_from_captive) {
                Log::info(sprintf('-- API/USER/NEW -- Saving registration from captive for user %s', $newuser->user_email));
                $registration_activity = Activity::mobileci()
                    ->setActivityType('registration')
                    ->setLocation($captive_location)
                    ->setUser($newuser)
                    ->setActivityName('registration_ok')
                    ->setActivityNameLong('Sign Up via Mobile (Email Address)')  // todo make this configurable?
                    ->setModuleName('Application')
                    ->responseOK();
                $registration_activity->save();
            }

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postnewuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_user')
                    ->setActivityNameLong('Create User Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postnewuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_user')
                    ->setActivityNameLong('Create User Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postnewuser.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_user')
                    ->setActivityNameLong('Create User Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.user.postnewuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_user')
                    ->setActivityNameLong('Create User Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete user
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - ID of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteUser()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deleteuser = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postdeleteuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postdeleteuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postdeleteuser.before.authz', array($this, $user));

            if (! ACL::create($user)) {
                Event::fire('orbit.user.postdeleteuser.authz.notallowed', array($this, $user));
                $deleteUserLang = Lang::get('validation.orbit.actionlist.delete_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.user.postdeleteuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $user_id = OrbitInput::post('user_id');

            // Error message when access is forbidden
            $deleteYourSelf = Lang::get('validation.orbit.actionlist.delete_your_self');
            $message = Lang::get('validation.orbit.access.forbidden',
                                 array('action' => $deleteYourSelf));

            $validator = Validator::make(
                array(
                    'user_id' => $user_id,
                ),
                array(
                    'user_id' => 'required|orbit.empty.user|no_delete_themself',
                ),
                array(
                    'no_delete_themself' => $message,
                )
            );

            Event::fire('orbit.user.postdeleteuser.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postdeleteuser.after.validation', array($this, $validator));

            $deleteuser = User::with(array('apikey'))->find($user_id);
            $deleteuser->status = 'deleted';
            $deleteuser->modified_by = $this->api->user->user_id;

            $deleteapikey = Apikey::where('apikey_id', '=', $deleteuser->apikey->apikey_id)->first();
            $deleteapikey->status = 'deleted';

            Event::fire('orbit.user.postdeleteuser.before.save', array($this, $deleteuser));

            $deleteuser->save();
            $deleteapikey->save();

            Event::fire('orbit.user.postdeleteuser.after.save', array($this, $deleteuser));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.user');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('User Deleted: %s', $deleteuser->username);
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User OK')
                    ->setObject($deleteuser)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.user.postdeleteuser.after.commit', array($this, $deleteuser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postdeleteuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deleteuser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postdeleteuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deleteuser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postdeleteuser.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deleteuser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.user.postdeleteuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deleteuser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.postdeleteuser.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * POST - Update user (currently only basic info)
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `user_id`               (required) - ID of the user
     * @param string    `email`                 (optional) - User email address
     * @param string    `username`              (optional) - Username
     * @param integer   `role_id`               (optional) - Role ID
     * @param string    `firstname`             (optional) - User first name
     * @param string    `lastname`              (optional) - User last name
     * @param string    `status`                (optional) - Status of the user 'active', 'pending', 'blocked', or 'deleted'
     * @param boolean   `updated_consumer_from_captive` (optional) - if 'Y' then only updates name, gender, birth date.
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateUser()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updateduser = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.user.postupdateuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postupdateuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postupdateuser.before.authz', array($this, $user));

            $is_updated_consumer_from_captive = OrbitInput::post('updated_consumer_from_captive', 'N') === 'Y';
            $captive_from_mall_owner = $is_updated_consumer_from_captive && $user->isRoleName('mall owner');
            $user_id = OrbitInput::post('user_id');
            if (! (ACL::create($user)->isAllowed('update_user') || $captive_from_mall_owner)) {
                // No need to check if it is the user itself
                if ((string)$user->user_id !== (string)$user_id) {
                    Event::fire('orbit.user.postupdateuser.authz.notallowed', array($this, $user));
                    $updateUserLang = Lang::get('validation.orbit.actionlist.update_user');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateUserLang));
                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.user.postupdateuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $username = OrbitInput::post('username');
            $user_firstname = OrbitInput::post('firstname');
            $user_lastname = OrbitInput::post('lastname');
            $status = OrbitInput::post('status');
            $user_role_id = OrbitInput::post('role_id');

            // Details
            $birthdate = OrbitInput::post('birthdate');
            $gender = OrbitInput::post('gender');
            $address1 = OrbitInput::post('address_line1');
            $address2 = OrbitInput::post('address_line2');
            $address3 = OrbitInput::post('address_line3');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $postal_code = OrbitInput::post('postal_code');
            $country = OrbitInput::post('country');
            $phone1 = OrbitInput::post('phone');
            $phone2 = OrbitInput::post('phone2');
            $relationship_status = OrbitInput::post('relationship_status');
            $number_of_children = OrbitInput::post('number_of_children');
            $occupation = OrbitInput::post('occupation');
            $sector_of_activity = OrbitInput::post('sector_of_activity');
            $company_name = OrbitInput::post('company_name');
            $education_level = OrbitInput::post('education_level');
            $preferred_lang = OrbitInput::post('preferred_language');
            $avg_annual_income1 = OrbitInput::post('avg_annual_income1');
            $avg_annual_income2 = OrbitInput::post('avg_annual_income2');
            $avg_monthly_spent1 = OrbitInput::post('avg_monthly_spent1');
            $avg_monthly_spent2 = OrbitInput::post('avg_monthly_spent2');
            $personal_interests = OrbitInput::post('personal_interests');

            $idcard = OrbitInput::post('idcard_number');
            $mobile = OrbitInput::post('mobile_phone');
            $mobile2 = OrbitInput::post('mobile_phone2');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $postal_code = OrbitInput::post('postal_code');
            $workphone = OrbitInput::post('work_phone');

            $validate_data = array(
                'user_id'               => $user_id,
                'username'              => $username,
                'email'                 => $email,
                'role_id'               => $user_role_id,
                'status'                => $status,

                'firstname'             => $user_firstname,
                'lastname'              => $user_lastname,

                'birthdate'             => $birthdate,
                'gender'                => $gender,
                'address_line1'         => $address1,
                'city'                  => $city,
                'province'              => $province,
                'postal_code'           => $postal_code,
                'country'               => $country,
                'phone'                 => $phone1,
                'phone2'                => $phone2,
                'relationship_status'   => $relationship_status,
                'number_of_children'    => $number_of_children,
                'education_level'       => $education_level,
                'preferred_language'    => $preferred_lang,
                'avg_annual_income1'    => $avg_annual_income1,
                'avg_annual_income2'    => $avg_annual_income2,
                'avg_monthly_spent1'    => $avg_monthly_spent1,
                'avg_monthly_spent2'    => $avg_monthly_spent2,
                'personal_interests'    => $personal_interests,
            );

            $validate_rules = array(
                'user_id'               => 'required',
                'username'              => 'orbit.exists.username',
                'email'                 => 'email|email_exists_but_me',
                'role_id'               => 'orbit.empty.role',
                'status'                => 'orbit.empty.user_status',

                'firstname'             => 'required',
                'lastname'              => '',

                'birthdate'             => 'date_format:Y-m-d',
                'gender'                => 'in:m,f',
                // 'address_line1'         => 'required',
                // 'city'                  => 'required',
                // 'province'              => 'required',
                // 'postal_code'           => 'required',
                // 'country'               => 'required',
                // 'phone'                 => 'required',
                'relationship_status'   => 'in:none,single,in a relationship,engaged,married,divorced,widowed',
                'number_of_children'    => 'numeric|min:0',
                'education_level'       => 'in:none,junior high school,high school,diploma,bachelor,master,ph.d,doctor,other',
                'preferred_language'    => 'in:en,id',
                'avg_annual_income1'    => 'numeric',
                'avg_annual_income2'    => 'numeric',
                'avg_monthly_spent1'    => 'numeric',
                'avg_monthly_spent2'    => 'numeric',
                'personal_interests'    => 'array|min:0|orbit.empty.personal_interest',
            );

            if ($is_updated_consumer_from_captive) {
                // only use & validate a subset of the fields.
                // also must ensure nothing is passed except these to prevent people sneaking in updates unvalidated
                $captive_fields = ['user_id', 'firstname', 'lastname', 'gender', 'birthdate', 'status'];
                $new_validate_data = [];
                $new_validate_rules = [];
                $old_post = $_POST;
                $old_post['status'] = 'active';
                $_POST = [];
                foreach ($captive_fields as $field) {
                    $new_validate_data[$field] = $validate_data[$field];
                    $new_validate_rules[$field] = $validate_rules[$field];
                    $_POST[$field] = $old_post[$field];
                }
                $_POST['status'] = 'active';
                $validate_data = $new_validate_data;
                $validate_rules = $new_validate_rules;
            }

            $validator = Validator::make(
                $validate_data,
                $validate_rules,
                array('email_exists_but_me' => Lang::get('validation.orbit.email.exists'))
            );

            Event::fire('orbit.user.postupdateuser.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postupdateuser.after.validation', array($this, $validator));

            $updateduser = User::with('userdetail')
                               ->excludeDeleted()
                               ->find($user_id);

            if ($is_updated_consumer_from_captive) {
                // can only be called for pending consumers, probably inappropriate error message...
                if ($updateduser->role === null || !$updateduser->isRoleName('consumer')) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.user_status'));
                }
                if ($updateduser->status !== 'pending') {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.user_status'));
                }
            }

            OrbitInput::post('username', function($username) use ($updateduser) {
                $updateduser->username = $username;
            });

            OrbitInput::post('email', function($email) use ($updateduser) {
                $updateduser->user_email = $email;
            });

            OrbitInput::post('firstname', function($firstname) use ($updateduser) {
                $updateduser->user_firstname = $firstname;
            });

            OrbitInput::post('lastname', function($lastname) use ($updateduser) {
                $updateduser->user_lastname = $lastname;
            });

            // User cannot update their own status
            if ((string)$user->user_id !== (string)$updateduser->user_id) {
                OrbitInput::post('status', function($status) use ($updateduser) {
                    $updateduser->status = $status;
                });
            }

            OrbitInput::post('role_id', function($role_id) use ($updateduser) {
                $updateduser->user_role_id = $role_id;
            });

            // Details

            OrbitInput::post('birthdate', function($date) use ($updateduser) {
                $updateduser->userdetail->birthdate = $date;
            });

            OrbitInput::post('gender', function($gender) use ($updateduser) {
                $updateduser->userdetail->gender = $gender;
            });

            OrbitInput::post('address_line1', function($addr1) use ($updateduser) {
                $updateduser->userdetail->address_line1 = $addr1;
            });

            OrbitInput::post('address_line2', function($addr2) use ($updateduser) {
                $updateduser->userdetail->address_line2 = $addr2;
            });

            OrbitInput::post('address_line3', function($addr3) use ($updateduser) {
                $updateduser->userdetail->address_line3 = $addr3;
            });

            OrbitInput::post('city', function($city) use ($updateduser) {
                $updateduser->userdetail->city = $city;
            });

            OrbitInput::post('province', function($province) use ($updateduser) {
                $updateduser->userdetail->province = $province;
            });

            OrbitInput::post('postal_code', function($postal) use ($updateduser) {
                $updateduser->userdetail->postal_code = $postal;
            });

            OrbitInput::post('country', function($country) use ($updateduser) {
                $updateduser->userdetail->country_id = $country;
                $country_name = Country::where('country_id', $country)->first()->name;
                $updateduser->userdetail->country = $country_name;
            });

            OrbitInput::post('phone', function($phone1) use ($updateduser) {
                $updateduser->userdetail->phone = $phone1;
            });

            OrbitInput::post('phone2', function($phone2) use ($updateduser) {
                $updateduser->userdetail->phone2 = $phone2;
            });

            OrbitInput::post('relationship_status', function($status) use ($updateduser) {
                $updateduser->userdetail->relationship_status = $status;
            });

            OrbitInput::post('number_of_children', function($number) use ($updateduser) {
                $updateduser->userdetail->number_of_children = $number;

                if ($number > 0) {
                    $updateduser->userdetail->has_children = 'Y';
                }
            });

            OrbitInput::post('education_level', function($level) use ($updateduser) {
                $updateduser->userdetail->last_education_degree = $level;
            });

            OrbitInput::post('preferred_language', function($lang) use ($updateduser) {
                $updateduser->userdetail->preferred_language = $lang;
            });

            OrbitInput::post('avg_annual_income1', function($income1) use ($updateduser) {
                $updateduser->userdetail->avg_annual_income1 = $income1;
            });

            OrbitInput::post('avg_annual_income2', function($income2) use ($updateduser) {
                $updateduser->userdetail->avg_annual_income2 = $income2;
            });

            OrbitInput::post('avg_monthly_spent1', function($spent1) use ($updateduser) {
                $updateduser->userdetail->avg_monthly_spent1 = $spent1;
            });

            OrbitInput::post('avg_monthly_spent2', function($spent2) use ($updateduser) {
                $updateduser->userdetail->avg_monthly_spent2 = $spent2;
            });

            OrbitInput::post('occupation', function($occupation) use ($updateduser) {
                $updateduser->userdetail->occupation = $occupation;
            });

            OrbitInput::post('sector_of_activity', function($soc) use ($updateduser) {
                $updateduser->userdetail->sector_of_activity = $soc;
            });

            OrbitInput::post('company_name', function($company) use ($updateduser) {
                $updateduser->userdetail->company_name = $company;
            });

            // OrbitInput::post('personal_interests', function($interests) use ($updateduser) {
            //     $updateduser->interests()->sync($interests);
            // });

            // additions
            OrbitInput::post('mobile_phone', function($phone) use ($updateduser) {
                $updateduser->userdetail->phone = $phone;
            });

            OrbitInput::post('mobile_phone2', function($phone3) use ($updateduser) {
                $updateduser->userdetail->phone3 = $phone3;
            });

            OrbitInput::post('work_phone', function($phone) use ($updateduser) {
                $updateduser->userdetail->phone2 = $phone;
            });

            OrbitInput::post('idcard', function($data) use ($updateduser) {
                $updateduser->userdetail->idcard = $data;
            });

            OrbitInput::post('idcard_number', function($data) use ($updateduser) {
                $updateduser->userdetail->idcard = $data;
            });

            OrbitInput::post('phone', function($phone) use ($updateduser) {
                $updateduser->userdetail->phone = $phone;
            });

            OrbitInput::post('phone2', function($phone2) use ($updateduser) {
                $updateduser->userdetail->phone2 = $phone2;
            });

            OrbitInput::post('phone3', function($phone3) use ($updateduser) {
                $updateduser->userdetail->phone3 = $phone3;
            });


            // // Flag for deleting all personal interests which belongs to this user
            // OrbitInput::post('personal_interests_delete_all', function($delete) use ($updateduser) {
            //     if ($delete === 'yes') {
            //         $updateduser->interests()->detach();
            //     }
            // });

            // save user categories
            OrbitInput::post('personal_interests_delete_all', function($delete) use ($updateduser) {
                if ($delete == 'yes') {
                    $deleted_category_ids = UserPersonalInterest::where('user_id', $updateduser->user_id)
                                                                ->where('object_type', 'interest')
                                                                ->get(array('personal_interest_id'))
                                                                ->toArray();
                    $updateduser->interests()->detach($deleted_category_ids);
                    $updateduser->load('interests');
                }
            });

            OrbitInput::post('personal_interests', function($category_ids) use ($updateduser) {
                // validate category_ids
                $category_ids = (array) $category_ids;
                foreach ($category_ids as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => '',
                        )
                    );

                    Event::fire('orbit.user.postupdateuser.before.categoryvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.user.postupdateuser.after.categoryvalidation', array($this, $validator));
                }
                // sync new set of category ids
                $pivotData = array_fill(0, count($category_ids), ['object_type' => 'interest']);
                $syncData = array_combine($category_ids, $pivotData);

                $deleted_category_ids = UserPersonalInterest::where('user_id', $updateduser->user_id)
                                                            ->where('object_type', 'interest')
                                                            ->get(array('personal_interest_id'))
                                                            ->toArray();

                // detach old relation
                if (sizeof($deleted_category_ids) > 0) {
                    $updateduser->interests()->detach($deleted_category_ids);
                }

                // attach new relation
                $updateduser->interests()->attach($syncData);

                // reload interests relation
                $updateduser->load('interests');
            });

            $updateduser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postupdateuser.before.save', array($this, $updateduser));

            $updateduser->save();
            $updateduser->userdetail->modified_by = $user->user_id;
            $updateduser->userdetail->save();

            $apikey = Apikey::where('user_id', '=', $updateduser->user_id)->first();
            if ($status != 'pending') {
                $apikey->status = $status;
            } else {
                $apikey->status = 'blocked';
            }
            $apikey->save();
            $updateduser->setRelation('apikey', $apikey);

            $updateduser->apikey = $apikey;

            Event::fire('orbit.user.postupdateuser.after.save', array($this, $updateduser));
            $this->response->data = $updateduser;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('User updated: %s', $updateduser->username);
            $activity->setUser($user)
                    ->setActivityName('update_user')
                    ->setActivityNameLong('Update User OK')
                    ->setObject($updateduser)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.user.postupdateuser.after.commit', array($this, $updateduser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postupdateuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_user')
                    ->setActivityNameLong('Update User Failed')
                    ->setObject($updateduser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postupdateuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_user')
                    ->setActivityNameLong('Update User Failed')
                    ->setObject($updateduser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postupdateuser.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_user')
                    ->setActivityNameLong('Update User Failed')
                    ->setObject($updateduser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.user.postupdateuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_user')
                    ->setActivityNameLong('Update User Failed')
                    ->setObject($updateduser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Search user (currently only basic info)
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek Bagus <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sort_by`               (optional) - column order by
     * @param string   `sort_mode`             (optional) - asc or desc
     * @param integer  `user_id`               (optional)
     * @param integer  `role_id`               (optional)
     * @param string   `username`              (optional)
     * @param string   `email`                 (optional)
     * @param string   `firstname`             (optional)
     * @param string   `lastname`              (optional)
     * @param string   `status`                (optional)
     * @param string   `username_like`         (optional)
     * @param string   `email_like`            (optional)
     * @param string   `firstname_like`        (optional)
     * @param string   `lastname_like`         (optional)
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param array    `with`                  (optional) -
     * @param datetime      `created_begin_date`        (optional) - Created begin date. Example: 2015-05-12 00:00:00
     * @param datetime      `created_end_date`          (optional) - Created end date. Example: 2014-05-12 23:59:59
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchUser()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.getsearchuser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.getsearchuser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.getsearchuser.before.authz', array($this, $user));

            if (! ACL::create($user)) {
                $user_ids = OrbitInput::get('user_id');
                $need_check = TRUE;
                if (! empty($user_ids) && is_array($user_ids)) {
                    if (in_array($user->user_id, $user_ids)) {
                        $need_check = FALSE;
                    }
                }

                if ($need_check) {
                    Event::fire('orbit.user.getsearchuser.authz.notallowed', array($this, $user));
                    $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.user.getsearchuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by'   => $sort_by,
                    'with'      => OrbitInput::get('with')
                ),
                array(
                    'sort_by'   => 'in:username,email,firstname,lastname,registered_date,updated_at',
                    'with'      => 'array|min:0'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.user.getsearchuser.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.getsearchuser.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.user.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.user.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $with = array('userdetail');

            OrbitInput::get('with', function($_with) use (&$with) {
                $with = array_merge($_with, $with);
            });
            $users = User::Consumers()
                        ->select('users.*')
                        ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'user_details.last_visit_shop_id')
                        ->with(array('userDetail', 'interestsShop', 'userDetail.lastVisitedShop'))
                        ->excludeDeleted('users');

            // Filter user by Ids
            OrbitInput::get('user_id', function ($userIds) use ($users) {
                $users->whereIn('users.user_id', $userIds);
            });

            // Filter user by username
            OrbitInput::get('username', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstname', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
            });

            // Filter user by their firstname pattern
            OrbitInput::get('firstname_like', function ($firstname) use ($users) {
                $users->where('users.user_firstname', 'like', "%$firstname%");
            });

            // Filter user by their lastname
            OrbitInput::get('lastname', function ($lastname) use ($users) {
                $users->whereIn('users.user_lastname', $lastname);
            });

            // Filter user by their lastname pattern
            OrbitInput::get('lastname_like', function ($firstname) use ($users) {
                $users->where('users.user_lastname', 'like', "%$firstname%");
            });

            // Filter user by their email
            OrbitInput::get('email', function ($email) use ($users) {
                $users->whereIn('users.user_email', $email);
            });

            // Filter user by their status
            OrbitInput::get('status', function ($status) use ($users) {
                $users->whereIn('users.status', $status);
            });

            // Filter user by their role id
            OrbitInput::get('role_id', function ($roleId) use ($users) {
                $users->whereIn('users.user_role_id', $roleId);
            });

            // Filter user by created_at for begin_date
            OrbitInput::get('created_begin_date', function($begindate) use ($users)
            {
                $users->where('users.created_at', '>=', $begindate);
            });

            // Filter user by created_at for end_date
            OrbitInput::get('created_end_date', function($enddate) use ($users)
            {
                $users->where('users.created_at', '<=', $enddate);
            });

            // Filter user by their role id
            OrbitInput::get('role_name', function ($roleId) use ($users) {
                $users->whereHas('role', function($q) use ($roleId) {
                    $q->where('roles.role_name', $roleId);
                });
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $users->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $users) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $users->skip($skip);

            // Default sort by
            $sortBy = 'users.user_firstname';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'users.created_at',
                    'updated_at'        => 'users.updated_at',
                    'username'          => 'users.username',
                    'email'             => 'users.user_email',
                    'lastname'          => 'users.user_lastname',
                    'firstname'         => 'users.user_firstname'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $users->orderBy($sortBy, $sortMode);

            $totalUsers = RecordCounter::create($_users)->count();
            $listOfUsers = $users->get();

            $data = new stdclass();
            $data->total_records = $totalUsers;
            $data->returned_records = count($listOfUsers);
            $data->records = $listOfUsers;

            if ($totalUsers === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.user');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.getsearchuser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.getsearchuser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.user.getsearchuser.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.user.getsearchuser.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.getsearchuser.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Consumer (currently only basic info)
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek Bagus <kadek@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `sort_by`           (optional) - column order by
     * @param string        `sort_mode`         (optional) - asc or desc
     * @param integer       `user_id`           (optional)
     * @param string        `username`          (optional)
     * @param string        `email`             (optional)
     * @param string        `firstname`         (optional)
     * @param string        `lastname`          (optional)
     * @param string        `status`            (optional)
     * @param string        `username_like`     (optional)
     * @param string        `email_like`        (optional)
     * @param string        `firstname_like`    (optional)
     * @param string        `lastname_like`     (optional)
     * @param array|string  `merchant_id`       (optional) - Id of the merchant, could be array or string with comma separated value
     * @param array|string  `retailer_id`       (optional) - Id of the retailer (Shop), could be array or string with comma separated value
     * @param integer       `take`              (optional) - limit
     * @param integer       `skip`              (optional) - limit offset
     * @param integer       `details`           (optional) - Include detailed issued coupon and lucky draw number
     * @param datetime      `created_begin_date`        (optional) - Created begin date. Example: 2015-05-12 00:00:00
     * @param datetime      `created_end_date`          (optional) - Created end date. Example: 2014-05-12 23:59:59
     * @param datetime      `last_visit_begin_date`     (optional) - Last visit begin date. Example: 2015-05-12 00:00:00
     * @param datetime      `last_visit_end_date`       (optional) - Last visit end date. Example: 2015-05-12 23:59:59
     * @return Illuminate\Support\Facades\Response
     */
    public function getConsumerListing()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.getconsumer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.getconsumer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.getconsumer.before.authz', array($this, $user));

            if (! ACL::create($user)) {
                $user_ids = OrbitInput::get('user_id');
                $need_check = TRUE;
                if (! empty($user_ids) && is_array($user_ids)) {
                    if (in_array($user->user_id, $user_ids)) {
                        $need_check = FALSE;
                    }
                }

                if ($need_check) {
                    Event::fire('orbit.user.getconsumer.authz.notallowed', array($this, $user));
                    $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.user.getconsumer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $details = OrbitInput::get('details');
            $merchantIds = OrbitInput::get('merchant_id');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:status,total_lucky_draw_number,total_usable_coupon,total_redeemed_coupon,username,email,firstname,lastname,registered_date,gender,city,last_visit_shop,last_visit_date,last_spent_amount,mobile_phone,membership_number,join_date,created_at,updated_at,first_visit_date',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.user_sortby'),
                )
            );

            Event::fire('orbit.user.getconsumer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.getconsumer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.user.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.user.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Available merchant to query
            $listOfMerchantIds = [];

            // Available retailer to query
            $listOfRetailerIds = [];

            // get user mall_ids
            $listOfMallIds = $user->getUserMallIds($merchantIds);

            if (empty($listOfMallIds)) { // invalid mall id
                $filterMallIds = 'and 0';
                $filterMembershipNumberMallIds = 'and 0';
            } elseif ($listOfMallIds[0] === 1) { // if super admin
                $filterMallIds = '';
                $filterMembershipNumberMallIds = '';
            } else { // valid mall id
                $filterMallIds = ' and p.merchant_id in ("' . join('","', $listOfMallIds) . '") ';
                $filterMembershipNumberMallIds = ' and m.merchant_id in ("' . join('","', $listOfMallIds) . '") ';
                $filterLuckyDrawMallIds = ' and ld.mall_id in ("' . join('","', $listOfMallIds) . '") ';
            }

            // Builder object
            $prefix = DB::getTablePrefix();
            $users = User::Consumers()
                         ->select('users.user_id', 'users.username', 'users.user_email', 'users.user_firstname', 'users.user_lastname', 'users.user_last_login', 'users.user_ip', 'users.user_role_id', 'users.status', 'users.remember_token', 'users.external_user_id', 'users.modified_by', 'users.created_at', 'users.updated_at', 'user_details.gender', 'user_details.phone')
                         ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                         ->leftJoin('merchants', 'merchants.merchant_id', '=', 'user_details.last_visit_shop_id')
                         ->with(array('userDetail', 'userDetail.lastVisitedShop'))
                         ->with(array('categories' => function ($q) use ($listOfMallIds) {
                            if (empty($listOfMallIds)) { // invalid mall id
                                $q->whereRaw('0');
                            } elseif ($listOfMallIds[0] === 1) { // if super admin
                                // show all users
                            } else { // valid mall id
                                $q->whereIn('categories.merchant_id', $listOfMallIds);
                            }
                         }))
                         ->with(array('banks' => function ($q) use ($listOfMallIds) {
                            if (empty($listOfMallIds)) { // invalid mall id
                                $q->whereRaw('0');
                            } elseif ($listOfMallIds[0] === 1) { // if super admin
                                // show all users
                            } else { // valid mall id
                                $q->whereIn('objects.merchant_id', $listOfMallIds);
                            }
                         }))
                         ->excludeDeleted('users')
                         ->groupBy('users.user_id')
                         ->with(array('membershipNumbers' => function ($q) use ($listOfMallIds) {
                            $q->excludeDeleted()
                              ->whereHas('membership', function ($q2) use ($listOfMallIds) {
                                $q2->excludeDeleted();
                                if (empty($listOfMallIds)) { // invalid mall id
                                    $q2->whereRaw('0');
                                } elseif ($listOfMallIds[0] === 1) { // if super admin
                                    // show all users
                                } else { // valid mall id
                                    $q2->whereIn('memberships.merchant_id', $listOfMallIds);
                                }
                            });
                         }));

            if ($details === 'yes' || $this->detailYes === true) {
                $users->addSelect(DB::raw("{$prefix}user_acquisitions.created_at as first_visit_date, 'Unknown', '0'"), DB::raw("CASE WHEN {$prefix}tmp_lucky.total_lucky_draw_number is null THEN 0 ELSE {$prefix}tmp_lucky.total_lucky_draw_number END AS total_lucky_draw_number"),
                               DB::raw("(select count(cp.user_id) from {$prefix}issued_coupons cp
                                        inner join {$prefix}promotions p on cp.promotion_id = p.promotion_id {$filterMallIds}
                                        where cp.user_id={$prefix}users.user_id) as total_usable_coupon,
                                        (select count(cp2.user_id) from {$prefix}issued_coupons cp2
                                        inner join {$prefix}promotions p on cp2.promotion_id = p.promotion_id {$filterMallIds}
                                        where cp2.status='redeemed' and cp2.user_id={$prefix}users.user_id) as total_redeemed_coupon"))
                                  ->leftJoin(
                                        // Table
                                        DB::raw("(select ldn.user_id, count(ldn.user_id) AS total_lucky_draw_number from `{$prefix}lucky_draw_numbers` ldn
                                                 join {$prefix}lucky_draws ld on ld.lucky_draw_id=ldn.lucky_draw_id
                                                 where ldn.status='active' and ld.status='active'
                                                 {$filterLuckyDrawMallIds}
                                                 and (ldn.user_id is not null and ldn.user_id != '0')
                                                 group by ldn.user_id)
                                                 {$prefix}tmp_lucky"),
                                        // ON
                                        'tmp_lucky.user_id', '=', 'users.user_id');
            } else {
                $users->addSelect(DB::raw("{$prefix}user_acquisitions.created_at as first_visit_date"));
            }

            $users->join('user_acquisitions', 'user_acquisitions.user_id', '=', 'users.user_id')
                  ->addSelect('tmp_membership_numbers.membership_number', 'tmp_membership_numbers.join_date')
                  ->leftJoin(
                    DB::raw("(select mn.user_id, mn.membership_number, mn.join_date from {$prefix}membership_numbers mn
                        left join {$prefix}memberships m on m.membership_id = mn.membership_id
                        where mn.status != 'deleted'
                            and m.status != 'deleted'
                            {$filterMembershipNumberMallIds}
                        ) as {$prefix}tmp_membership_numbers"),
                    'tmp_membership_numbers.user_id', '=', 'users.user_id');

            $current_mall = OrbitInput::get('current_mall');

            if (empty($listOfMallIds)) { // invalid mall id
                $users->whereRaw('0');
            } elseif ($listOfMallIds[0] === 1) { // if super admin
                // show all users
            } else { // valid mall id
                $users->whereIn('user_acquisitions.acquirer_id', $listOfMallIds);
            }

            // Filter by retailer (shop) ids
            OrbitInput::get('retailer_id', function($retailerIds) use ($users) {
                // $users->retailerIds($retailerIds);
                $listOfRetailerIds = (array)$retailerIds;
            });

            if ($user->isRoleName('consumer')) {
                $users->whereIn('users.user_id', (array)$user->user_id);
            } else {
                // Filter user by Ids
                OrbitInput::get('user_id', function ($userIds) use ($users) {
                    $users->whereIn('users.user_id', $userIds);
                });
            }

            // Filter user by external_user_id
            OrbitInput::get('external_user_id', function ($data) use ($users) {
                $users->whereIn('users.external_user_id', $data);
            });

            // Filter user by username
            OrbitInput::get('username', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstname', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
            });

            // Filter retailer by name_like (first_name last_name)
            OrbitInput::get('name_like', function($data) use ($users) {
                $users->where(DB::raw('CONCAT(COALESCE(user_firstname, ""), " ", COALESCE(user_lastname, ""))'), 'like', "%$data%");
            });

            // Filter user by their firstname pattern
            OrbitInput::get('firstname_like', function ($firstname) use ($users) {
                $users->where('users.user_firstname', 'like', "%$firstname%");
            });

            // Filter user by their lastname
            OrbitInput::get('lastname', function ($lastname) use ($users) {
                $users->whereIn('users.user_lastname', $lastname);
            });

            // Filter user by their lastname pattern
            OrbitInput::get('lastname_like', function ($lastname) use ($users) {
                $users->where('users.user_lastname', 'like', "%$lastname%");
            });

            // Filter user by their email
            OrbitInput::get('email', function ($email) use ($users) {
                $users->whereIn('users.user_email', $email);
            });

            // Filter user by their email pattern
            OrbitInput::get('email_like', function ($email) use ($users) {
                $users->where('users.user_email', 'like', "%$email%");
            });

            // Filter user by gender
            OrbitInput::get('gender', function ($gender) use ($users) {
                $users->whereHas('userdetail', function ($q) use ($gender) {
                    $q->whereIn('gender', $gender);
                });
            });

            // Filter user by membership number
            OrbitInput::get('membership_number', function ($data) use ($users) {
                $users->whereIn('tmp_membership_numbers.membership_number', $data);
            });

            // Filter user by membership number
            OrbitInput::get('membership_number_like', function ($arg) use ($users) {
                $users->where('tmp_membership_numbers.membership_number', 'like', "%$arg%");
            });

            // Filter user by created_at for begin_date
            OrbitInput::get('created_begin_date', function ($begindate) use ($users)
            {
                $users->where('users.created_at', '>=', $begindate);
            });

            // Filter user by created_at for end_date
            OrbitInput::get('created_end_date', function ($enddate) use ($users)
            {
                $users->where('users.created_at', '<=', $enddate);
            });

            // Filter user by their status
            OrbitInput::get('status', function ($status) use ($users) {
                $users->whereIn('users.status', $status);
            });

            // Filter user by created_at from date
            OrbitInput::get('created_at_from', function ($from) use ($users)
            {
                $users->where('users.created_at', '>=', $from);
            });

            // Filter user by created_at to date
            OrbitInput::get('created_at_to', function ($to) use ($users)
            {
                $users->where('users.created_at', '<=', $to);
            });

            // Filter user by updated_at from date
            OrbitInput::get('updated_at_from', function ($from) use ($users)
            {
                $users->where('users.updated_at', '>=', $from);
            });

            // Filter user by updated_at to date
            OrbitInput::get('updated_at_to', function ($to) use ($users)
            {
                $users->where('users.updated_at', '<=', $to);
            });

            // Filter user by membership number
            OrbitInput::get('is_member', function ($isMember) use ($users)
            {
                if ($isMember === 'yes') {
                    $users->where('tmp_membership_numbers.membership_number', '!=', '');
                } elseif ($isMember === 'no') {
                    $users->where(function ($q) {
                        $q->where('tmp_membership_numbers.membership_number', '=', '')
                          ->orWhereNull('tmp_membership_numbers.membership_number');
                    });
                }
            });

            // Filter by created_at date
            OrbitInput::get('created_at_after', function($data) use ($users) {
                $users->where('users.created_at', '>=', $data);
            });

            // Filter by created_at date
            OrbitInput::get('created_at_before', function($data) use ($users) {
                $users->where('users.created_at', '<=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_after', function($data) use ($users) {
                $users->where('users.updated_at', '>=', $data);
            });

            // Filter by updated_at date
            OrbitInput::get('updated_at_before', function($data) use ($users) {
                $users->where('users.updated_at', '<=', $data);
            });

            // Filter user by last_visit_begin_date
            OrbitInput::get('last_visit_begin_date', function($begindate) use ($users)
            {
                $users->whereHas('userdetail', function ($q) use ($begindate) {
                    $q->where('last_visit_any_shop', '>=', $begindate);
                });
            });

            // Filter user by last_visit_end_date
            OrbitInput::get('last_visit_end_date', function($enddate) use ($users)
            {
                $users->whereHas('userdetail', function ($q) use ($enddate) {
                    $q->where('last_visit_any_shop', '<=', $enddate);
                });
            });

            // Filter user by idcard
            OrbitInput::get('idcard', function($data) use ($users)
            {
                $users->whereHas('userdetail', function ($q) use ($data) {
                    $q->whereIn('idcard', $data);
                });
            });

            // Filter user by first_visit date begin_date
            OrbitInput::get('first_visit_begin_date', function($begindate) use ($users)
            {
                $users->having('first_visit_date', '>=', $begindate);
            });

            // Filter user by first visit date end_date
            OrbitInput::get('first_visit_end_date', function($enddate) use ($users)
            {
                $users->having('first_visit_date', '<=', $enddate);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_users = clone $users;

            // if not printing / exporting data then do pagination.
            if (! $this->returnBuilder) {
                // Get the take args
                $take = $perPage;
                OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;

                    if ((int)$take <= 0) {
                        $take = $maxRecord;
                    }
                });
                $users->take($take);

                $skip = 0;
                OrbitInput::get('skip', function ($_skip) use (&$skip, $users) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $users->skip($skip);
            }

            // Default sort by
            $sortBy = 'users.created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'         => 'users.created_at',
                    'username'                => 'users.username',
                    'email'                   => 'users.user_email',
                    'lastname'                => 'users.user_lastname',
                    'firstname'               => 'users.user_firstname',
                    'gender'                  => 'user_details.gender',
                    'city'                    => 'user_details.city',
                    'mobile_phone'            => 'user_details.phone',
                    'membership_number'       => 'tmp_membership_numbers.membership_number',
                    'join_date'               => 'tmp_membership_numbers.join_date',
                    'created_at'              => 'users.created_at',
                    'updated_at'              => 'users.updated_at',
                    'status'                  => 'users.status',
                    'last_visit_shop'         => 'merchants.name',
                    'last_visit_date'         => 'user_details.last_visit_any_shop',
                    'last_spent_amount'       => 'user_details.last_spent_any_shop',
                    'total_usable_coupon'     => 'total_usable_coupon',
                    'total_redeemed_coupon'   => 'total_redeemed_coupon',
                    'total_lucky_draw_number' => 'total_lucky_draw_number',
                    'first_visit_date'        => 'first_visit_date',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            if ($sortBy !== 'users.status') {
                $users->orderBy('users.status', 'asc');
            }

            $users->orderBy($sortBy, $sortMode);

            $summary = [];
            $summary['Total Customers'] = RecordCounter::create($_users)->count();
            if (Input::get('name_like')) {
                $summary['Filter by Customer Name'] = Input::get('name_like');
            }

            if (Input::get('email_like')) {
                $summary['Filter by Email'] = Input::get('email_like');
            }

            if (Input::get('gender') && is_array(Input::get('gender'))) {
                foreach (Input::get('gender') as $code) {
                    if ($code == 'm') $genders[] = 'Male';
                    if ($code == 'f') $genders[] = 'Female';
                }

                $summary['Filter by Gender'] = implode(', ', $genders);
            }

            if (Input::get('membership_number_like')) {
                $summary['Filter by Membership Number'] = Input::get('membership_number_like');
            }

            if (Input::get('status') && is_array(Input::get('status'))) {
                foreach (Input::get('status') as $status) {
                    $statuses[] = ucfirst($status);
                }

                $summary['Filter by Status'] = implode(', ', $statuses);
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return [
                    'builder' => $users,
                    'summary' => $summary,
                ];
            }

            $totalUsers = RecordCounter::create($_users)->count();
            $listOfUsers = $users->get();

            $data = new stdclass();

            // set enable_membership_card value from table settings
            $settingName = 'enable_membership_card';

            $setting = Setting::active()
                              ->where('object_type', 'merchant')
                              ->whereIn('object_id', $listOfMallIds)
                              ->where('setting_name', $settingName)
                              ->first();

            if (empty($setting)) {
                $data->enable_membership_card = 'false';
            } else {
                $data->enable_membership_card = $setting->setting_value;
            }

            $data->total_records = $totalUsers;
            $data->returned_records = count($listOfUsers);
            $data->records = $listOfUsers;

            if ($totalUsers === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.user');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.getconsumer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.getconsumer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.user.getconsumer.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.user.getconsumer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.getconsumer.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Change password user
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                 (required) - ID of the user
     * @param string     `old_password`            (required) - user's old password
     * @param string     `new_password`            (required) - user's new password
     * @param string     `confirm_password`        (required) - confirmation user's new password
     * @return Illuminate\Support\Facades\Response
     */
    public function postChangePassword()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postchangepassword.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postchangepassword.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postchangepassword.before.authz', array($this, $user));

            $user_id = OrbitInput::post('user_id');
            if (! ACL::create($user)->isAllowed('change_password')) {
                if ((string)$user->user_id !== (string)$user_id) {
                    Event::fire('orbit.user.postchangepassword.authz.notallowed', array($this, $user));
                    $changePasswordUserLang = Lang::get('validation.orbit.actionlist.change_password');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $changePasswordUserLang));
                    ACL::throwAccessForbidden($message);
                }
            }
            Event::fire('orbit.user.postchangepassword.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $old_password = OrbitInput::post('old_password');
            $new_password = OrbitInput::post('new_password');
            $new_password_confirmation = OrbitInput::post('confirm_password');

            // Error message when old password is not correct
            $message = Lang::get('validation.orbit.access.old_password_not_match');

            $validator = Validator::make(
                array(
                    'user_id'                   => $user_id,
                    'old_password'              => $old_password,
                    'new_password'              => $new_password,
                    'new_password_confirmation' => $new_password_confirmation,
                ),
                array(
                    'user_id'                   => 'required|orbit.empty.user',
                    'old_password'              => 'required|min:6|valid_user_password:'.$user_id,
                    'new_password'              => 'required|min:6|confirmed',
                ),
                array(
                    'valid_user_password'       => $message,
                )
            );

            Event::fire('orbit.user.postchangepassword.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postchangepassword.after.validation', array($this, $validator));

            $passupdateduser = User::excludeDeleted()
                                    ->where('user_id', $user_id)
                                    ->first();
            $passupdateduser->user_password = Hash::make($new_password);
            $passupdateduser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postchangepassword.before.save', array($this, $passupdateduser));

            $passupdateduser->save();

            Event::fire('orbit.user.postchangepassword.after.save', array($this, $passupdateduser));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.updated.user');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.user.postchangepassword.after.commit', array($this, $passupdateduser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postchangepassword.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postchangepassword.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postchangepassword.query.error', array($this, $e));

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
        } catch (Exception $e) {
            Event::fire('orbit.user.postchangepassword.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.postchangepassword.before.render', array($this, $output));

        return $output;
    }

    /**
     * POST - Create new membership
     *
     * @author me@rioastamal.net
     *
     * List of API Parameters
     * @param array     `category_ids`          (optional) - Category IDs
     * @param array     `bank_object_ids`       (optional) - Bank Object IDs
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewMembership()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newuser = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postnewmembership.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postnewmembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postnewmembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)) {
                Event::fire('orbit.user.postnewmembership.authz.notallowed', array($this, $user));
                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            // validate user mall id for current_mall
            $mallId = OrbitInput::post('current_mall');
            $listOfMallIds = $user->getUserMallIds($mallId);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mallId = $listOfMallIds[0];
            }

            Event::fire('orbit.user.postnewmembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $firstname = OrbitInput::post('firstname');
            $lastname = OrbitInput::post('lastname');
            $gender = OrbitInput::post('gender');
            $birthdate = OrbitInput::post('birthdate');
            $phone = OrbitInput::post('phone');
            $join_date = OrbitInput::post('join_date');
            $membershipNumberCode = OrbitInput::post('membership_number');
            $status = OrbitInput::post('status');
            $idcard = OrbitInput::post('idcard');
            if (trim($idcard) === '') {
                $idcard = OrbitInput::post('idcard_number');
            }

            $mobile = OrbitInput::post('mobile_phone');
            $mobile2 = OrbitInput::post('mobile_phone2');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $postal_code = OrbitInput::post('postal_code');
            $workphone = OrbitInput::post('work_phone');
            $occupation = OrbitInput::post('occupation');
            $dateofwork = OrbitInput::post('date_of_work');
            $homeAddress = OrbitInput::post('home_address');
            $workAddress = OrbitInput::post('work_address');
            $category_ids = OrbitInput::post('category_ids');
            $category_ids = (array) $category_ids;
            $bank_object_ids = OrbitInput::post('bank_object_ids');
            $bank_object_ids = (array) $bank_object_ids;
            $external_user_id = OrbitInput::post('external_user_id');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'current_mall'          => $mallId,
                    'membership_card'       => $mallId,
                    'external_user_id'      => $external_user_id,
                    'email'                 => $email,
                    'firstname'             => $firstname,
                    'lastname'              => $lastname,
                    'gender'                => $gender,
                    'birthdate'             => $birthdate,
                    'join_date'             => $join_date,
                    'membership_number'     => $membershipNumberCode,
                    'status'                => $status,
                    'category_ids'          => $category_ids,
                    'bank_object_ids'       => $bank_object_ids,
                    'idcard'                => $idcard,
                    'mobile_phone'          => $mobile,
                    'work_phone'            => $workphone,
                    'occupation'            => $occupation,
                    'date_of_work'          => $dateofwork,
                ),
                array(
                    'current_mall'          => 'required|orbit.empty.mall',
                    'membership_card'       => 'orbit.empty.mall_have_membership_card',
                    'external_user_id'      => 'required',
                    'email'                 => 'required|email|orbit.email.checker.mxrecord|orbit.exists.email',
                    'firstname'             => 'required',
                    'lastname'              => '',
                    'gender'                => 'in:m,f',
                    'birthdate'             => 'date_format:Y-m-d',
                    'join_date'             => 'date_format:Y-m-d',
                    'membership_number'     => 'alpha_num|orbit.exists.membership_number',
                    'status'                => 'required|in:active,inactive,pending',
                    'category_ids'          => 'array',
                    'bank_object_ids'       => 'array',
                    'idcard'                => 'numeric',
                    'mobile_phone'          => '',
                    'work_phone'            => '',
                    'occupation'            => '',
                    'date_of_work'          => 'date_format:Y-m-d'
                ),
                array(
                    'alpha_num' => 'The membership number must letter and number.',
                )
            );

            Event::fire('orbit.user.postnewmembership.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validate category_ids
            foreach ($category_ids as $category_id_check) {
                $validator = Validator::make(
                    array(
                        'category_id'   => $category_id_check,
                    ),
                    array(
                        'category_id'   => 'orbit.empty.category:' . $mallId,
                    )
                );

                Event::fire('orbit.user.postnewmembership.before.categoryvalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.user.postnewmembership.after.categoryvalidation', array($this, $validator));
            }

            // validate bank_object_ids
            foreach ($bank_object_ids as $bank_object_id_check) {
                $validator = Validator::make(
                    array(
                        'bank_object_id'  => $bank_object_id_check,
                    ),
                    array(
                        'bank_object_id'  => 'orbit.empty.bank_object:' . $mallId,
                    )
                );

                Event::fire('orbit.user.postnewmembership.before.bankobjectvalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.user.postnewmembership.after.bankobjectvalidation', array($this, $validator));
            }

            Event::fire('orbit.user.postnewmembership.after.validation', array($this, $validator));

            $roleConsumer = Role::where('role_name', 'consumer')->first();
            if (empty($roleConsumer)) {
                OrbitShopAPI::throwInvalidArgument('Could not find role named "Consumer".');
            }

            $newuser = new User();
            $newuser->external_user_id = $external_user_id;
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->status = $status;
            $newuser->user_firstname = $firstname;
            $newuser->user_lastname = $lastname;
            $newuser->user_role_id = $roleConsumer->role_id;
            $newuser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.user.postnewmembership.before.save', array($this, $newuser));

            $newuser->save();

            // create membership number
            // create if only param membership_number or join_date is being sent
            if ((trim($membershipNumberCode) !== '') || (trim($join_date) !== '')) {
                $m = new MembershipNumber();
                $membershipCard = App::make('orbit.empty.mall_have_membership_card');
                $m->membership_id = $membershipCard->membership_id;
                $m->user_id = $newuser->user_id;
                $m->membership_number = $membershipNumberCode;
                $m->join_date = $join_date . ' 00:00:00';
                $m->status = 'active';
                $m->created_by = $user->user_id;
                $m->save();
            }
            $newuser->load('membershipNumbers');

            $userdetail = new UserDetail();
            $userdetail->gender = $gender;
            $userdetail->birthdate = $birthdate;
            $userdetail->phone = $mobile;
            $userdetail->phone3 = $mobile2;
            $userdetail->city = $city;
            $userdetail->province = $province;
            $userdetail->postal_code = $postal_code;
            $userdetail->phone2 = $workphone;
            $userdetail->idcard = $idcard;
            $userdetail->occupation = $occupation;
            $userdetail->date_of_work = $dateofwork;
            $userdetail->address_line1 = $homeAddress;
            $userdetail->address_line2 = $workAddress;
            $userdetail->merchant_acquired_date = date('Y-m-d H:i:s');

            $userdetail = $newuser->userdetail()->save($userdetail);

            $newuser->setRelation('userdetail', $userdetail);
            $newuser->userdetail = $userdetail;
            $newuser->load('userdetail');

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newuser);
            $apikey->api_secret_key = Apikey::genSecretKey($newuser);
            $apikey->status = 'active';
            $apikey->user_id = $newuser->user_id;
            $apikey = $newuser->apikey()->save($apikey);

            $newuser->setHidden(array('user_password'));

            // save categories
            $userCategories = array();
            foreach ($category_ids as $category_id) {
                $userPersonalInterest = new UserPersonalInterest();
                $userPersonalInterest->user_id = $newuser->user_id;
                $userPersonalInterest->personal_interest_id = $category_id;
                $userPersonalInterest->object_type = 'category';
                $userPersonalInterest->save();
                $userCategories[] = $userPersonalInterest;
            }
            $newuser->categories = $userCategories;
            $newuser->load('categories');

            // save bank_object_ids
            $userBanks = array();
            foreach ($bank_object_ids as $bank_object_id) {
                $objectRelation = new ObjectRelation();
                $objectRelation->main_object_id = $bank_object_id;
                $objectRelation->main_object_type = 'bank';
                $objectRelation->secondary_object_id = $newuser->user_id;
                $objectRelation->secondary_object_type = 'user';
                $objectRelation->save();
                $userBanks[] = $objectRelation;
            }
            $newuser->banks = $userBanks;
            $newuser->load('banks');

            Event::fire('orbit.user.postnewmembership.after.save', array($this, $newuser));
            $this->response->data = $newuser;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('User Created: %s', $newuser->username);
            $activity->setUser($user)
                    ->setActivityName('create_membership')
                    ->setActivityNameLong('Create Membership')
                    ->setObject($newuser)
                    ->setNotes($activityNotes)
                    ->responseOK();

            //Event::fire('orbit.user.postnewmembership.after.commit', array($this, $newuser, $mallId));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postnewmembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_membership')
                    ->setActivityNameLong('Create Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postnewmembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_membership')
                    ->setActivityNameLong('Create Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postnewmembership.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_membership')
                    ->setActivityNameLong('Create Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.user.postnewmembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_membership')
                    ->setActivityNameLong('Create Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update membership
     *
     * @author me@rioastamal.net
     *
     * List of API Parameters
     * @param string     `no_category`           (optional) - Flag to delete all category links. Valid value: Y.
     * @param array      `category_ids`          (optional) - Category IDs
     * @param string     `no_bank_object`        (optional) - Flag to delete all bank object links. Valid value: Y.
     * @param array      `bank_object_ids`       (optional) - Bank Object IDs
     * ----------------------
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMembership()
    {
        $activity = Activity::portal()
                            ->setActivityType('update');

        $user = NULL;
        $newuser = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postupdatemembership.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postupdatemembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postupdatemembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)) {
                Event::fire('orbit.user.postupdatemembership.authz.notallowed', array($this, $user));
                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner', 'mall customer service'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            // validate user mall id for current_mall
            $mallId = OrbitInput::post('current_mall');
            $listOfMallIds = $user->getUserMallIds($mallId);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mallId = $listOfMallIds[0];
            }

            Event::fire('orbit.user.postupdatemembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            // set mall id
            //$mallId = OrbitInput::post('current_mall');

            // get user mall_ids
            $listOfMallIds = $user->getUserMallIds($mallId);

            $email = OrbitInput::post('email');
            $firstname = OrbitInput::post('firstname');
            $lastname = OrbitInput::post('lastname');
            $gender = OrbitInput::post('gender');
            $birthdate = OrbitInput::post('birthdate');
            $join_date = OrbitInput::post('join_date');
            $membershipNumberCode = OrbitInput::post('membership_number');
            $status = OrbitInput::post('status');
            $category_ids = OrbitInput::post('category_ids');
            $category_ids = (array) $category_ids;
            $bank_object_ids = OrbitInput::post('bank_object_ids');
            $bank_object_ids = (array) $bank_object_ids;

            $idcard = OrbitInput::post('idcard');
            if (trim($idcard) === '') {
                $idcard = OrbitInput::post('idcard_number');
            }

            $mobile = OrbitInput::post('mobile_phone');
            $mobile2 = OrbitInput::post('mobile_phone2');
            $workphone = OrbitInput::post('work_phone');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $postal_code = OrbitInput::post('postal_code');
            $occupation = OrbitInput::post('occupation');
            $dateofwork = OrbitInput::post('date_of_work');
            $homeAddress = OrbitInput::post('home_address');
            $workAddress = OrbitInput::post('work_address');
            $userId = OrbitInput::post('user_id');
            $externalUserId = OrbitInput::post('external_user_id');

            $validator = Validator::make(
                array(
                    'current_mall'          => $mallId,
                    'user_id'               => $userId,
                    'membership_card'       => $mallId,
                    'email'                 => $email,
                    'firstname'             => $firstname,
                    'lastname'              => $lastname,
                    'gender'                => $gender,
                    'birthdate'             => $birthdate,
                    'join_date'             => $join_date,
                    'membership_number'     => $membershipNumberCode,
                    'status'                => $status,
                    'idcard'                => $idcard,
                    'mobile_phone'          => $mobile,
                    'work_phone'            => $workphone,
                    'occupation'            => $occupation,
                    'date_of_work'          => $dateofwork,
                ),
                array(
                    'current_mall'          => 'required|orbit.empty.mall',
                    'user_id'               => 'required|orbit.empty.user',
                    'membership_card'       => 'orbit.empty.mall_have_membership_card',
                    'email'                 => 'email|email_exists_but_me|orbit.email.checker.mxrecord',
                    'firstname'             => '',
                    'lastname'              => '',
                    'gender'                => 'in:m,f',
                    'birthdate'             => 'date_format:Y-m-d',
                    'join_date'             => 'date_format:Y-m-d',
                    'membership_number'     => 'alpha_num|membership_number_exists_but_me',
                    'status'                => 'in:active,inactive,pending',
                    'idcard'                => '',
                    'mobile_phone'          => '',
                    'work_phone'            => '',
                    'occupation'            => '',
                    'date_of_work'          => 'date_format:Y-m-d',
                ),
                array(
                    'email_exists_but_me' => Lang::get('validation.orbit.email.exists'),
                    'alpha_num' => 'The membership number must letter and number.',
                )
            );

            Event::fire('orbit.user.postupdatemembership.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Update email in tokens table when change email before user setup a password
            // Check setup password user or no
            $userPass = User::excludeDeleted()
                ->where('user_id', '=', $userId)
                ->where('user_password', '=', '')
                ->count('user_id');

            if ($userPass > 0) {
                // update email in token
                $checkToken = Token::excludeDeleted()
                    ->where('user_id', $userId)
                    ->where('token_name', 'user_setup_password')
                    ->first();

                if ($checkToken !== null) {
                    $checkToken->email = $email;
                    $checkToken->save();
                }
            }

            Event::fire('orbit.user.postupdatemembership.after.validation', array($this, $validator));

            $role = Role::where('role_name', 'consumer')->first();

            $updateduser = App::make('orbit.empty.user');
            $userdetail = $updateduser->userdetail;

            $membershipCard = App::make('orbit.empty.mall_have_membership_card');
            $membershipNumbers = $updateduser->getMembershipNumbers($membershipCard);

            OrbitInput::post('email', function($email) use ($updateduser) {
                $updateduser->username = $email;
                $updateduser->user_email = $email;
            });

            OrbitInput::post('firstname', function($firstname) use ($updateduser) {
                $updateduser->user_firstname = $firstname;
            });

            OrbitInput::post('lastname', function($lastname) use ($updateduser) {
                $updateduser->user_lastname = $lastname;
            });

            // User cannot update their own status
            if ((string)$user->user_id !== (string)$updateduser->user_id) {
                OrbitInput::post('status', function($status) use ($updateduser) {
                    $updateduser->status = $status;
                });
            }

            OrbitInput::post('external_user_id', function($data) use ($updateduser) {
                $updateduser->external_user_id = $data;
            });

            OrbitInput::post('birthdate', function($date) use ($userdetail) {
                $userdetail->birthdate = $date;
            });

            OrbitInput::post('gender', function($gender) use ($userdetail) {
                $userdetail->gender = $gender;
            });

            OrbitInput::post('mobile_phone', function($phone1) use ($userdetail) {
                $userdetail->phone = $phone1;
            });

            OrbitInput::post('mobile_phone2', function($phone2) use ($userdetail) {
                $userdetail->phone2 = $phone2;
            });

            OrbitInput::post('work_phone', function($phone3) use ($userdetail) {
                $userdetail->phone3 = $phone3;
            });

            OrbitInput::post('city', function($city) use ($userdetail) {
                $userdetail->city = $city;
            });

            OrbitInput::post('province', function($province) use ($userdetail) {
                $userdetail->province = $province;
            });

            OrbitInput::post('postal_code', function($postal) use ($userdetail) {
                $userdetail->postal_code = $postal;
            });

            OrbitInput::post('home_address', function($data) use ($userdetail) {
                $userdetail->address_line1 = $data;
            });

            OrbitInput::post('work_address', function($data) use ($userdetail) {
                $userdetail->address_line2 = $data;
            });

            OrbitInput::post('idcard', function($data) use ($userdetail) {
                $userdetail->idcard = $data;
            });

            OrbitInput::post('idcard_number', function($data) use ($userdetail) {
                $userdetail->idcard = $data;
            });

            OrbitInput::post('occupation', function($data) use ($userdetail) {
                $userdetail->occupation = $data;
            });

            OrbitInput::post('date_of_work', function($data) use ($userdetail) {
                $userdetail->date_of_work = $data;
            });

            // Save updated by
            $updateduser->modified_by = $this->api->user->user_id;
            $userdetail->modified_by = $this->api->user->user_id;
            $updateduser->touch();


            /**
             * create/update membership number
             */
            if ($membershipNumbers->first()) {
                // update
                $m = $membershipNumbers->first();

                OrbitInput::post('membership_number', function ($arg) use ($m) {
                    $m->membership_number = $arg;
                });

                OrbitInput::post('join_date', function ($arg) use ($m) {
                    $m->join_date = $arg . ' 00:00:00';
                });

                if ((empty($membershipNumberCode)) && (empty($join_date))) {
                    $m->status = 'inactive';
                } else {
                    $m->status = 'active';
                }

                $m->modified_by = $user->user_id;
                $m->save();
            } else {
                // create
                // create if only param membership_number or join_date is being sent
                if ((trim($membershipNumberCode) !== '') || (trim($join_date) !== '')) {
                    $m = new MembershipNumber();
                    $m->membership_id = $membershipCard->membership_id;
                    $m->user_id = $updateduser->user_id;
                    $m->membership_number = $membershipNumberCode;
                    $m->join_date = $join_date . ' 00:00:00';
                    $m->status = 'active';
                    $m->created_by = $user->user_id;
                    $m->save();
                }
            }

            Event::fire('orbit.user.postupdatemembership.before.save', array($this, $updateduser));

            $updateduser->save();
            $userdetail->save();

            $membershipNumbers = $updateduser->getMembershipNumbers($membershipCard);
            if ($membershipNumbers->first()) {
                $updateduser->membership_number = $membershipNumbers->first()->membership_number;
                $updateduser->join_date  = $membershipNumbers->first()->join_date;
            } else {
                $updateduser->membership_number = null;
                $updateduser->join_date  = '0000-00-00 00:00:00';
            }

            $updateduser->membership_numbers = $membershipNumbers;

            // save user categories
            OrbitInput::post('no_category', function($no_category) use ($updateduser) {
                if ($no_category == 'Y') {
                    $deleted_category_ids = UserPersonalInterest::where('user_id', $updateduser->user_id)
                                                                ->where('object_type', 'category')
                                                                ->get(array('personal_interest_id'))
                                                                ->toArray();
                    $updateduser->categories()->detach($deleted_category_ids);
                    $updateduser->load('categories');
                }
            });

            OrbitInput::post('category_ids', function($category_ids) use ($updateduser, $listOfMallIds) {
                // validate category_ids
                $category_ids = (array) $category_ids;
                foreach ($category_ids as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => 'orbit.empty.category',
                        )
                    );

                    Event::fire('orbit.user.postupdatemembership.before.categoryvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.user.postupdatemembership.after.categoryvalidation', array($this, $validator));
                }
                // sync new set of category ids
                $pivotData = array_fill(0, count($category_ids), ['object_type' => 'category']);
                $syncData = array_combine($category_ids, $pivotData);

                $deleted_category_ids = UserPersonalInterest::where('user_id', $updateduser->user_id)
                                                            ->where('object_type', 'category')
                                                            ->join('categories', 'categories.category_id', '=', 'user_personal_interest.personal_interest_id');

                if (empty($listOfMallIds)) { // invalid mall id
                    $deleted_category_ids->whereRaw('0');
                } elseif ($listOfMallIds[0] === 1) { // if super admin
                    // show all users
                } else { // valid mall id
                    $deleted_category_ids->whereIn('categories.merchant_id', $listOfMallIds);
                }

                $deleted_category_ids = $deleted_category_ids->get(array('personal_interest_id'))
                                                             ->toArray();

                // detach old relation
                if (sizeof($deleted_category_ids) > 0) {
                    $updateduser->categories()->detach($deleted_category_ids);
                }

                // attach new relation
                $updateduser->categories()->attach($syncData);

                // reload categories relation
                $updateduser->load('categories');
            });

            // save user bank_object_ids
            OrbitInput::post('no_bank_object', function($no_bank_object) use ($updateduser) {
                if ($no_bank_object == 'Y') {
                    $deleted_bank_object_ids = ObjectRelation::where('secondary_object_id', $updateduser->user_id)
                                                             ->where('secondary_object_type', 'user')
                                                             ->where('main_object_type', 'bank')
                                                             ->get(array('main_object_id'))
                                                             ->toArray();
                    $updateduser->banks()->detach($deleted_bank_object_ids);
                    $updateduser->load('banks');
                }
            });

            OrbitInput::post('bank_object_ids', function($bank_object_ids) use ($updateduser, $mallId, $listOfMallIds) {
                // validate bank_object_ids
                $bank_object_ids = (array) $bank_object_ids;
                foreach ($bank_object_ids as $bank_object_id_check) {
                    $validator = Validator::make(
                        array(
                            'bank_object_id'  => $bank_object_id_check,
                        ),
                        array(
                            'bank_object_id'  => 'orbit.empty.bank_object:' . $mallId,
                        )
                    );

                    Event::fire('orbit.user.postupdatemembership.before.bankobjectvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.user.postupdatemembership.after.bankobjectvalidation', array($this, $validator));
                }
                // sync new set of bank_object_ids
                $pivotData = array_fill(0, count($bank_object_ids), ['main_object_type' => 'bank', 'secondary_object_type' => 'user']);
                $syncData = array_combine($bank_object_ids, $pivotData);

                $deleted_bank_ids = ObjectRelation::where('secondary_object_id', $updateduser->user_id)
                                                  ->where('secondary_object_type', 'user')
                                                  ->where('main_object_type', 'bank')
                                                  ->join('objects', 'objects.object_id', '=', 'object_relation.main_object_id');

                if (empty($listOfMallIds)) { // invalid mall id
                    $deleted_bank_ids->whereRaw('0');
                } elseif ($listOfMallIds[0] === 1) { // if super admin
                    // show all users
                } else { // valid mall id
                    $deleted_bank_ids->whereIn('objects.merchant_id', $listOfMallIds);
                }

                $deleted_bank_ids = $deleted_bank_ids->get(array('main_object_id'))
                                                     ->toArray();

                // detach old relation
                if (sizeof($deleted_bank_ids) > 0) {
                    $updateduser->banks()->detach($deleted_bank_ids);
                }

                // attach new relation
                $updateduser->banks()->attach($syncData);

                // reload banks relation
                $updateduser->load('banks');
            });

            Event::fire('orbit.user.postupdatemembership.after.save', array($this, $updateduser, $mallId));
            $this->response->data = $updateduser;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('User Created: %s', $updateduser->username);
            $activity->setUser($updateduser)
                    ->setActivityName('update_membership')
                    ->setActivityNameLong('Update Membership')
                    ->setStaff($user)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.user.postupdatemembership.after.commit', array($this, $updateduser, $mallId));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postupdatemembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_membership')
                    ->setActivityNameLong('Update Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postupdatemembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_membership')
                    ->setActivityNameLong('Update Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postupdatemembership.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_membership')
                    ->setActivityNameLong('Update Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.user.postupdatemembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('update_membership')
                    ->setActivityNameLong('Update Member Failed')
                    ->setModuleName('Membership')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete membership
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `user_id`                 (required) - ID of the user
     * @param string    `password`                (required) - The mall master password
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMembership()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletedUser = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.user.postdeletemembership.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.user.postdeletemembership.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.user.postdeletemembership.before.authz', array($this, $user));

/*
            if (! ACL::create($user)) {
                Event::fire('orbit.user.postdeletemembership.authz.notallowed', array($this, $user));
                $deleteUserLang = Lang::get('validation.orbit.actionlist.delete_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteUserLang));
                ACL::throwAccessForbidden($message);
            }
*/

            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // validate user mall id for current_mall
            $mallId = OrbitInput::post('current_mall');
            $listOfMallIds = $user->getUserMallIds($mallId);
            if (empty($listOfMallIds)) { // invalid mall id
                $errorMessage = 'Invalid mall id.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                $mallId = $listOfMallIds[0];
            }

            Event::fire('orbit.user.postdeletemembership.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $user_id = OrbitInput::post('user_id');
            $password = OrbitInput::post('password');

            // Error message when access is forbidden
            $deleteYourSelf = Lang::get('validation.orbit.actionlist.delete_your_self');
            $message = Lang::get('validation.orbit.access.forbidden',
                                 array('action' => $deleteYourSelf));

            $validator = Validator::make(
                array(
                    'current_mall' => $mallId,
                    'user_id'   => $user_id,
                    'password'  => $password,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'user_id'   => 'required|orbit.empty.membership|no_delete_themself',
                    'password'  => 'required|orbit.masterpassword.delete:' . $mallId,
                ),
                array(
                    'no_delete_themself'            => $message,
                    'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.user.postdeletemembership.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.user.postdeletemembership.after.validation', array($this, $validator));

            $deletedUser = App::make('orbit.empty.membership');
            $deletedUser->status = 'deleted';
            $deletedUser->modified_by = $this->api->user->user_id;

            $deleteapikey = $deletedUser->apikey;
            $deleteapikey->status = 'deleted';

            Event::fire('orbit.user.postdeletemembership.before.save', array($this, $deletedUser));

            $deletedUser->save();
            $deleteapikey->save();

            // hard delete user personal interest.
            $deleteUserPersonalInterests = UserPersonalInterest::where('user_id', $deletedUser->user_id)->get();
            foreach ($deleteUserPersonalInterests as $deleteUserPersonalInterest) {
                $deleteUserPersonalInterest->delete();
            }

            // hard delete user bank_object_ids.
            $deleteUserBankObjects = ObjectRelation::where('secondary_object_id', $deletedUser->user_id)
                                                         ->where('secondary_object_type', 'user')
                                                         ->where('main_object_type', 'bank')
                                                         ->get();
            foreach ($deleteUserBankObjects as $deleteUserBankObject) {
                $deleteUserBankObject->delete();
            }

            Event::fire('orbit.user.postdeletemembership.after.save', array($this, $deletedUser));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.user');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('User Deleted: %s', $deletedUser->username);
            $activity->setUser($user)
                    ->setActivityName('delete_membership')
                    ->setActivityNameLong('Delete Membership OK')
                    ->setObject($deletedUser)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.user.postdeletemembership.after.commit', array($this, $deletedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.user.postdeletemembership.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_membership')
                    ->setActivityNameLong('Delete Membership Failed')
                    ->setObject($deletedUser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.user.postdeletemembership.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deletedUser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postdeletemembership.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deletedUser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.user.postdeletemembership.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_user')
                    ->setActivityNameLong('Delete User Failed')
                    ->setObject($deletedUser)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.postdeletemembership.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * Runs on box.
     *
     * Returns response of user_id
     * Passes email, retailer_id, callback_url in parameters
     *
     * @param string $email
     * @param string $current_mall
     * @return Redirect
     */
    public function redirectToCloudGetID() {
        try {
            $this->response->code = 302; // must not be 0
            $this->response->status = 'success';
            $this->response->message = 'Redirecting to cloud'; // stored in activity by IntermediateLoginController

            $url = Config::get('orbit.registration.mobile.cloud_login_url');
            $email = OrbitInput::get('email');
            $retailer_id = OrbitInput::get('current_mall');
            $from = OrbitInput::get('from');
            $check_only = OrbitInput::get('check_only', 'no') === 'yes';
            $auto_login = OrbitInput::get('auto_login', 'no');
            $from_captive = OrbitInput::get('from_captive', 'no');
            $socmed_redirect_to = OrbitInput::get('socmed_redirect_to', '');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'current_mall'          => $retailer_id,
                    'email'                 => $email,
                    'from'                  => $from,
                ),
                array(
                    'current_mall'          => 'required|orbit.empty.mall',
                    'email'                 => 'required|email|orbit.email.checker.mxrecord|orbit.email.exists:' . $retailer_id,
                    'from'                  => 'in:cs',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $values = [
                'email' => $email,
                'retailer_id' => $retailer_id,
                'callback_url' => URL::route('customer-login-callback-show-id'),
                'payload' => '',
                'from' => $from,
                'full_data' => 'yes',
                'check_only' => $check_only ? 'yes' : 'no',
                'auto_login' => $auto_login,
                'from_captive' => $from_captive,
                'socmed_redirect_to' => $socmed_redirect_to
            ];
            $values = CloudMAC::wrapDataFromBox($values);
            $req = \Symfony\Component\HttpFoundation\Request::create($url, 'GET', $values);
            $this->response->data = [
                'url' => $req->getUri(),
            ];
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();
        }

        return $this->render();
        // return Redirect::to($req->getUri());
    }

    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function ($attribute, $value, $parameters) {

            // get current mall id and its mall group
            $currentRetailerId = $parameters[0];
            $retailer = Mall::select('parent_id')
                                ->where('merchant_id', $currentRetailerId)
                                ->first();
            if (! is_object($retailer)) {
                $errorMessage = 'Mall is not found.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $currentMerchantId = $retailer->parent_id;

            $user = User::excludeDeleted()
                        ->Consumers()
                        ->whereHas('userdetail', function ($q) use ($currentMerchantId) {
                            $q->where('merchant_id', $currentMerchantId);
                        })
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

        // Check user email address, it should not exists
        Validator::extend('orbit.exists.email', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->Consumers()
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

        // Check user membership, it should not exists
        Validator::extend('orbit.membership.exists', function ($attribute, $value, $parameters) {
            $check = Config::get('orbit.shop.membership_number_unique_check');

            // get current mall id and its mall group
            $currentRetailerId = $parameters[0];
            $retailer = Mall::select('parent_id')
                                ->where('merchant_id', $currentRetailerId)
                                ->first();
            $currentMerchantId = $retailer->parent_id;

            if ($check) {
                $user = User::excludeDeleted()
                            ->Consumers()
                            ->whereHas('userdetail', function ($q) use ($currentMerchantId) {
                                $q->where('merchant_id', $currentMerchantId);
                            })
                            ->where('membership_number', $value)
                            ->first();

                if (! empty($user)) {
                    $errorMessage = 'Membership number already exists.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                App::instance('orbit.validation.user', $user);
            }

            return TRUE;
        });

        // Check membership number should be unique in one mall, but not unique in different malls
        Validator::extend('orbit.exists.membership_number', function ($attribute, $value, $parameters) {
            $check = Config::get('orbit.shop.membership_number_unique_check');

            $mall = App::make('orbit.empty.mall');

            if (! $check) {
                $user = User::excludeDeleted('users')
                            ->Consumers()
                            ->join('membership_numbers', 'membership_numbers.user_id', '=', 'users.user_id')
                            ->join('memberships', 'membership_numbers.membership_id', '=', 'memberships.membership_id')
                            ->where('memberships.status', '!=', 'deleted')
                            ->where('memberships.merchant_id', $mall->merchant_id)
                            ->where('membership_numbers.status', '!=', 'deleted')
                            ->where('membership_numbers.membership_number', $value);

                if ($user->first()) {
                    $errorMessage = 'Membership number has already been exists.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

            }

            return TRUE;
        });

        // Check membership number should be unique in one mall, but not unique in different malls
        Validator::extend('membership_number_exists_but_me', function ($attribute, $value, $parameters) {
            $check = Config::get('orbit.shop.membership_number_unique_check');

            $mall = App::make('orbit.empty.mall');

            $user = App::make('orbit.empty.user');

            // currently, mall have one membership card
            $membershipCard = App::make('orbit.empty.mall_have_membership_card');

            // currently, user have one membership number based on the mall membership card
            $membershipNumbers = $user->getMembershipNumbers($membershipCard);

            App::instance('membership_number_exists_but_me', $membershipNumbers);

            if (! $check) {
                $user = User::excludeDeleted('users')
                            ->Consumers()
                            ->join('membership_numbers', 'membership_numbers.user_id', '=', 'users.user_id')
                            ->join('memberships', 'membership_numbers.membership_id', '=', 'memberships.membership_id')
                            ->where('memberships.status', '!=', 'deleted')
                            ->where('memberships.merchant_id', $mall->merchant_id)
                            ->where('membership_numbers.status', '!=', 'deleted')
                            ->where('membership_numbers.membership_number', $value);

                // if user have membership number then exclude the number
                if ($membershipNumbers->first()) {
                    $user->where('membership_numbers.membership_number_id', '!=', $membershipNumbers->first()->membership_number_id);
                }

                if ($user->first()) {
                    $errorMessage = 'Membership number has already been exists.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

            }

            return TRUE;
        });

        // Check username, it should not exists
        Validator::extend('orbit.exists.username', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('username', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.username', $user);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_id', $value)
                        ->first();

            if (empty($user)) {
                return FALSE;
            }

            if ($user->isSuperAdmin()) {
                $errorMessage = 'You can not delete super admin account.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            App::instance('orbit.empty.membership', $user);

            App::instance('orbit.empty.user', $user);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.membership', function ($attribute, $value, $parameters) {
            $role = Role::where('role_name', 'consumer')->first();

            $user = User::excludeDeleted()
                        ->where('user_id', $value)
                        ->where('user_role_id', $role->role_id)
                        ->first();

            if (empty($user)) {
                return FALSE;
            }

            if ($user->isSuperAdmin()) {
                $errorMessage = 'You can not delete super admin account.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            App::instance('orbit.empty.membership', $user);

            return TRUE;
        });

        // Check user old password
        Validator::extend('valid_user_password', function ($attribute, $value, $parameters) {
            $user_id = trim($parameters[0]);
            $user = User::excludeDeleted()
                        ->where('user_id', $user_id)
                        ->first();

            if (empty($user)) {
                return FALSE;
            } elseif (Hash::check($value, $user->user_password)) {
                return TRUE;
            }

            return FALSE;
        });

        // Check self
        Validator::extend('no_delete_themself', function ($attribute, $value, $parameters) {
            if ((string) $value === (string) $this->api->user->user_id) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of the Role
        Validator::extend('orbit.empty.role', function ($attribute, $value, $parameters) {
            $role = Role::find($value);

            if (empty($role)) {
                return FALSE;
            }

            App::instance('orbit.validation.role', $role);

            return TRUE;
        });

        // Check the existance of the Role
        Validator::extend('orbit.empty.user_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check user email address, it should not exists
        Validator::extend('email_exists_but_me', function ($attribute, $value, $parameters) {
            $user_id = OrbitInput::post('user_id');
            $from = OrbitInput::post('from');
            $role_name = '';

            if ($from === 'cs') {
                $role_name = 'Consumer';
            }

            $user = User::excludeDeleted()
                        ->where('user_id', '!=', $user_id)
                        ->where('user_email', '=', $value);

            if ($role_name !== '') {
                $user = $user->where('user_role_id', '=', function($q) use ($role_name) {
                            $q->select('role_id')
                                ->from('roles')
                                ->where('role_name', $role_name);
                        });
            }

            $user = $user->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });

        Validator::extend('orbit.empty.personal_interest', function ($attribute, $value, $parameters) {
            $personal_interest_ids = $value;
            $number = count($personal_interest_ids);
            $real_number = PersonalInterest::ExcludeDeleted()
                                           ->whereIn('personal_interest_id', $personal_interest_ids)
                                           ->count();

            if ((string)$real_number !== (string)$number) {
                return FALSE;
            }

            return TRUE;
        });

        // Membership deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall location
            $currentMall = $parameters[0];

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = 'The master password is not set.';
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = 'The master password is incorrect.';
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                                ->where('category_id', $value);

            if (! empty($parameters)) {
                $mallId = $parameters[0];
                $category->where('merchant_id', $mallId);
            }

            $category = $category->first();

            if (empty($category)) {
                return FALSE;
            }

            App::instance('orbit.empty.category', $category);

            return TRUE;
        });

        // Check the existance of bank object id
        Validator::extend('orbit.empty.bank_object', function ($attribute, $value, $parameters) {
            $mallId = $parameters[0];

            $bankObject = Object::excludeDeleted()
                                ->where('merchant_id', $mallId)
                                ->where('object_type', 'bank')
                                ->where('object_id', $value)
                                ->first();

            if (empty($bankObject)) {
                return FALSE;
            }

            App::instance('orbit.empty.bank_object', $bankObject);

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check if mall have membership card
        Validator::extend('orbit.empty.mall_have_membership_card', function ($attribute, $value, $parameters) {
            $mallId = $value;

            $membershipCard = Membership::excludeDeleted()
                                        ->active()
                                        ->where('merchant_id', $mallId)
                                        ->first();

            if (empty($membershipCard)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall_have_membership_card', $membershipCard);

            return TRUE;
        });

        //Check email with mxrecord
        Validator::extend('orbit.email.checker.mxrecord', function ($attribute, $value, $parameters) {
            $hosts = MXEmailChecker::create($value)->check()->getMXRecords();

            if (empty($hosts)) {
                $errorMessage = \Lang::get('validation.email', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            App::instance('orbit.email.checker.mxrecord', $hosts);

            return TRUE;
        });
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    public function setDetail($bool)
    {
        $this->detailYes = $bool;

        return $this;
    }
}
