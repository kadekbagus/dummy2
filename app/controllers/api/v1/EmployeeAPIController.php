<?php
/**
 * An API controller for managing user.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class EmployeeAPIController extends ControllerAPI
{
    protected $employeeViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    protected $employeeModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    /**
     * POST - Create New Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `firstname`             (required) - Employee first name
     * @param string    `lastname`              (required) - Employee last name
     * @param string    `birthdate`             (optional) - Employee birthdate
     * @param string    `position`              (optional) - Employee position, i.e: 'Cashier 1', 'Supervisor'
     * @param string    `employee_id_char`      (optional) - Employee ID, i.e: 'EMP001', 'CASHIER001`
     * @param string    `username`              (required) - Username used to login
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `employee_role`         (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
     * @param array     `retailer_ids`          (optional) - List of Retailer IDs
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewEmployee()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newEmployee = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postnewemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postnewemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postnewemployee.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_employee')) {
                Event::fire('orbit.employee.postnewemployee.authz.notallowed', array($this, $user));

                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.employee.postnewemployee.after.authz', array($this, $user));
            // dd();
            $this->registerCustomValidation();

            $loginId = OrbitInput::post('username');
            $birthdate = OrbitInput::post('birthdate');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $position = OrbitInput::post('position');
            $employeeId = OrbitInput::post('employee_id_char');
            $firstName = OrbitInput::post('firstname');
            $lastName = OrbitInput::post('lastname');
            $employeeRole = OrbitInput::post('employee_role');
            $retailerIds = OrbitInput::post('retailer_ids', []);

            $errorMessage = [
                'orbit.empty.employee.role' => Lang::get('validation.orbit.empty.employee.role', array(
                    'role' => $employeeRole
                ))
            ];

            $validator = Validator::make(
                array(
                    'firstname'             => $firstName,
                    'lastname'              => $lastName,
                    'date_of_birth'         => $birthdate,
                    'position'              => $position,
                    'employee_id_char'      => $employeeId,
                    'username'              => $loginId,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                    'employee_role'         => $employeeRole,
                    'retailer_ids'          => $retailerIds
                ),
                array(
                    'firstname'        => 'required',
                    'lastname'         => 'required',
                    'date_of_birth'    => 'date_format:Y-m-d',
                    'employee_id_char' => 'orbit.exists.employeeid',
                    'username'         => 'required|orbit.exists.username',
                    'password'         => 'required|min:5|confirmed',
                    'employee_role'    => 'required|orbit.empty.employee.role',
                    'retailer_ids'     => 'array|min:1|orbit.empty.retailer'
                ),
                array(
                    'orbit.empty.employee.role' => $errorMessage['orbit.empty.employee.role'],
                )
            );

            Event::fire('orbit.employee.postnewemployee.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                // WTHell Laravel message wont work!! I need to subtitute manually
                $errorMessage = str_replace('username', 'Email Login', $errorMessage);
                $errorMessage = str_replace('employee id char', 'Employee ID', $errorMessage);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postnewemployee.after.validation', array($this, $validator));

            $role = App::make('orbit.empty.employee.role');

            $newUser = new User();
            $newUser->username = $loginId;
            $newUser->user_email = sprintf('%s@local.localdomain', $loginId);
            $newUser->user_password = Hash::make($password);
            $newUser->status = 'active';
            $newUser->user_role_id = $role->role_id;
            $newUser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newUser->modified_by = $this->api->user->user_id;
            $newUser->user_firstname = $firstName;
            $newUser->user_lastname = $lastName;

            Event::fire('orbit.employee.postnewemployee.before.save', array($this, $newUser));

            $newUser->save();

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newUser);
            $apikey->api_secret_key = Apikey::genSecretKey($newUser);
            $apikey->status = 'active';
            $apikey->user_id = $newUser->user_id;
            $apikey = $newUser->apikey()->save($apikey);

            $newUser->setRelation('apikey', $apikey);
            $newUser->setHidden(array('user_password'));

            $userdetail = new UserDetail();

            OrbitInput::post('birthdate', function($_birthdate) use ($userdetail) {
                $userdetail->birthdate = $_birthdate;
            });
            $userdetail = $newUser->userdetail()->save($userdetail);

            $newUser->setRelation('userDetail', $userdetail);

            $newEmployee = new Employee();
            $newEmployee->employee_id_char = $employeeId;
            $newEmployee->position = $position;
            $newEmployee->status = $newUser->status;
            $newEmployee = $newUser->employee()->save($newEmployee);

            $newUser->setRelation('employee', $newEmployee);

            $myRetailerIds = $user->getMyRetailerIds();
            $retailerIds = array_merge($retailerIds, $myRetailerIds);

            if ($retailerIds) {
                $newEmployee->retailers()->sync($retailerIds);
            }

            Event::fire('orbit.employee.postnewemployee.after.save', array($this, $newUser));
            $this->response->data = $newUser;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Employee Created: %s', $newUser->username);
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee OK')
                    ->setObject($newEmployee)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.employee.postnewemployee.after.commit', array($this, $newUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postnewemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postnewemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postnewemployee.query.error', array($this, $e));

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
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.employee.postnewemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Create New Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `firstname`               (required) - Employee first name
     * @param string    `lastname`                (required) - Employee last name
     * @param string    `birthdate`               (optional) - Employee birthdate
     * @param string    `position`                (optional) - Employee position, i.e: 'Cashier 1', 'Supervisor'
     * @param string    `employee_id_char`        (optional) - Employee ID, i.e: 'EMP001', 'CASHIER001`
     * @param string    `username`                (required) - Username used to login
     * @param string    `password`                (required) - Password for the account
     * @param string    `password_confirmation`   (required) - Confirmation password
     * @param string    `employee_role`           (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
     * @param array     `retailer_ids`            (optional) - List of Retailer IDs
     * @param string    `cs_verification_numbers` (optional) - Unique verification number
     * @param array     `status`                  (optional) - 'active' or 'inactive'
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewMallEmployee()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newEmployee = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postnewemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postnewemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postnewemployee.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_employee')) {
                Event::fire('orbit.employee.postnewemployee.authz.notallowed', array($this, $user));

                $createUserLang = Lang::get('validation.orbit.actionlist.add_new_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createUserLang));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.employee.postnewemployee.after.authz', array($this, $user));
            // dd();
            $this->registerCustomValidation();

            $loginId = OrbitInput::post('username');
            $birthdate = OrbitInput::post('birthdate');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $position = OrbitInput::post('position');
            $employeeId = OrbitInput::post('employee_id_char');
            $firstName = OrbitInput::post('firstname');
            $lastName = OrbitInput::post('lastname');
            $employeeRole = OrbitInput::post('employee_role');
            $retailerIds = OrbitInput::post('retailer_ids', []);
            $empStatus = OrbitInput::post('status', 'active');
            $csVerificationNumbers = OrbitInput::post('cs_verification_numbers');
            $myRetailerIds = OrbitInput::post('current_mall');

            $errorMessage = [
                'orbit.empty.employee.role' => Lang::get('validation.orbit.empty.employee.role', array(
                    'role' => $employeeRole
                ))
            ];

            $validator = Validator::make(
                array(
                    'current_mall'            => $myRetailerIds,
                    'firstname'               => $firstName,
                    'lastname'                => $lastName,
                    'date_of_birth'           => $birthdate,
                    'position'                => $position,
                    'employee_id_char'        => $employeeId,
                    'username'                => $loginId,
                    'password'                => $password,
                    'password_confirmation'   => $password2,
                    'employee_role'           => $employeeRole,
                    'retailer_ids'            => $retailerIds,
                    'cs_verification_numbers' => $csVerificationNumbers,
                    'status'                  => $empStatus
                ),
                array(
                    'current_mall'            => 'required|orbit.empty.mall',
                    'firstname'               => 'required',
                    'lastname'                => 'required',
                    'date_of_birth'           => 'date_format:Y-m-d',
                    'employee_id_char'        => 'orbit.exists.employeeid:' . $myRetailerIds,
                    'username'                => 'required|orbit.exists.username.mall',
                    'password'                => 'required|min:6|confirmed',
                    'employee_role'           => 'required|orbit.empty.employee.role',
                    'retailer_ids'            => 'array|min:1|orbit.empty.retailer',
                    'cs_verification_numbers' => 'alpha_num|orbit.exist.verification.numbers:' . $myRetailerIds ,
                    'status'                  => 'in:active,inactive'
                ),
                array(
                    'orbit.empty.employee.role'        => $errorMessage['orbit.empty.employee.role'],
                    'orbit.exist.verification.numbers' => 'The verification number already used by other',
                    'alpha_num' => 'The verification number must letter and number.',
                )
            );

            Event::fire('orbit.employee.postnewemployee.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                // WTHell Laravel message wont work!! I need to subtitute manually
                $errorMessage = str_replace('username', 'Email Login', $errorMessage);
                $errorMessage = str_replace('employee id char', 'Employee ID', $errorMessage);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postnewemployee.after.validation', array($this, $validator));

            $role = App::make('orbit.empty.employee.role');

            $newUser = new User();
            $newUser->username = $loginId;
            $newUser->user_email = $loginId;
            $newUser->user_password = Hash::make($password);
            $newUser->status = $empStatus;
            $newUser->user_role_id = $role->role_id;
            $newUser->user_ip = $_SERVER['REMOTE_ADDR'];
            $newUser->modified_by = $this->api->user->user_id;
            $newUser->user_firstname = $firstName;
            $newUser->user_lastname = $lastName;

            Event::fire('orbit.employee.postnewemployee.before.save', array($this, $newUser));

            $newUser->save();

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newUser);
            $apikey->api_secret_key = Apikey::genSecretKey($newUser);
            $apikey->status = 'active';
            $apikey->user_id = $newUser->user_id;
            $apikey = $newUser->apikey()->save($apikey);

            $newUser->setRelation('apikey', $apikey);
            $newUser->setHidden(array('user_password'));

            $userdetail = new UserDetail();

            OrbitInput::post('birthdate', function($_birthdate) use ($userdetail) {
                $userdetail->birthdate = $_birthdate;
            });
            $userdetail = $newUser->userdetail()->save($userdetail);

            $newUser->setRelation('userDetail', $userdetail);

            $newEmployee = new Employee();
            $newEmployee->employee_id_char = $employeeId;
            $newEmployee->position = $position;
            $newEmployee->status = $newUser->status;
            $newEmployee = $newUser->employee()->save($newEmployee);

            $newUser->setRelation('employee', $newEmployee);

            // User verification numbers
            $newUserVerificationNumber = new UserVerificationNumber();
            OrbitInput::post('cs_verification_numbers', function($_csVerificationNumbers) use ($newUserVerificationNumber, $newUser, $myRetailerIds) {
                $newUserVerificationNumber->user_id = $newUser->user_id;
                $newUserVerificationNumber->verification_number = $_csVerificationNumbers;
                $newUserVerificationNumber->merchant_id = $myRetailerIds;
            });

            $newUserVerificationNumber->save();
            $newUser->setRelation('userVerificationNumber', $newUserVerificationNumber);

            // @Todo: Remove this hardcode
            $retailerIds = array_merge($retailerIds, (array)$myRetailerIds);

            if ($retailerIds) {
                $newEmployee->retailers()->sync($retailerIds);
            }

            Event::fire('orbit.employee.postnewemployee.after.save', array($this, $newUser));
            $this->response->data = $newUser;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Employee Created: %s', $newUser->username);
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee OK')
                    ->setObject($newEmployee)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.employee.postnewemployee.after.commit', array($this, $newUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postnewemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postnewemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postnewemployee.query.error', array($this, $e));

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
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.employee.postnewemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_employee')
                    ->setActivityNameLong('Create Employee Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Existing Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `user_id`               (required) - User ID of the employee
     * @param string    `firstname`             (required) - Employee first name (Unchangeable)
     * @param string    `lastname`              (optional) - Employee last name (Unchangeable)
     * @param string    `birthdate`             (optional) - Employee birthdate
     * @param string    `position`              (optional) - Employee position, i.e: 'Cashier 1', 'Supervisor'
     * @param string    `employee_id_char`      (optional) - Employee ID, i.e: 'EMP001', 'CASHIER001'
     * @param string    `username`              (required) - Username used to login (Unchangable)
     * @param string    `password`              (required) - Password for the account
     * @param string    `password_confirmation` (required) - Confirmation password
     * @param string    `employee_role`         (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
     * @param array     `retailer_ids`          (optional) - List of Retailer IDs
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateEmployee()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $employee = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postupdateemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postupdateemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postupdateemployee.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_employee')) {
                Event::fire('orbit.employee.postupdateemployee.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.update_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.employee.postupdateemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');
            $loginId = OrbitInput::post('username');
            $birthdate = OrbitInput::post('birthdate');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $position = OrbitInput::post('position');
            $employeeId = OrbitInput::post('employee_id_char');
            $employeeRole = OrbitInput::post('employee_role');
            $retailerIds = OrbitInput::post('retailer_ids', []);
            $status = OrbitInput::post('status');

            $errorMessage = [
                'orbit.empty.employee.role'         => Lang::get('validation.orbit.empty.employee.role', array(
                    'role' => $employeeRole
                )),
                'orbit.exists.employeeid_but_me'    => 'The employee ID is not available.'
            ];

            $validator = Validator::make(
                array(
                    'user_id'               => $userId,
                    'date_of_birth'         => $birthdate,
                    'password'              => $password,
                    'password_confirmation' => $password2,
                    'employee_id_char'      => $employeeId,
                    'employee_role'         => $employeeRole,
                    'retailer_ids'          => $retailerIds,
                    'status'                => $status
                ),
                array(
                    'user_id'               => 'orbit.empty.user',
                    'date_of_birth'         => 'date_format:Y-m-d',
                    'password'              => 'min:5|confirmed',
                    'employee_role'         => 'orbit.empty.employee.role',
                    'employee_id_char'      => 'orbit.exists.employeeid_but_me',
                    'retailer_ids'          => 'array|min:1|orbit.empty.retailer',
                    'status'                => 'orbit.empty.user_status',
                ),
                array(
                    'orbit.empty.employee.role'         => $errorMessage['orbit.empty.employee.role'],
                    'orbit.exists.employeeid_but_me'    => $errorMessage['orbit.exists.employeeid_but_me']
                )
            );

            Event::fire('orbit.employee.postupdateemployee.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                // WTHell Laravel message wont work!! I need to subtitute manually
                $errorMessage = str_replace('username', 'Email Login', $errorMessage);
                $errorMessage = str_replace('employee id char', 'Employee ID', $errorMessage);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postupdateemployee.after.validation', array($this, $validator));

            $updatedUser = App::make('orbit.empty.user');

            OrbitInput::post('password', function($password) use ($updatedUser) {
                $updatedUser->user_password = Hash::make($password);
            });

            OrbitInput::post('status', function($status) use ($updatedUser) {
                $updatedUser->status = 'active';
            });

            OrbitInput::post('employee_role', function($_role) use ($updatedUser) {
                $role = App::make('orbit.empty.employee.role');
                $updatedUser->user_role_id = $role->role_id;
            });

            $updatedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postupdateemployee.before.save', array($this, $updatedUser));

            $updatedUser->save();
            $updatedUser->apikey;

            // Get the relation
            $employee = $updatedUser->employee;
            $userDetail = $updatedUser->userDetail;

            OrbitInput::post('position', function($_position) use ($employee) {
                $employee->position = $_position;
            });

            OrbitInput::post('employee_id_char', function($empId) use ($employee) {
                $employee->employee_id_char = $empId;
            });

            $employee->status = $updatedUser->status;
            $employee->save();

            OrbitInput::post('birthdate', function($_birthdate) use ($userDetail) {
                $userDetail->birthdate = $_birthdate;
            });

            $userDetail->save();

            $myRetailerIds = $user->getMyRetailerIds();
            $retailerIds = array_merge($retailerIds, $myRetailerIds);

            if ($retailerIds) {
                $employee->retailers()->sync($retailerIds);
            }

            Event::fire('orbit.employee.postupdateemployee.after.save', array($this, $updatedUser));
            $this->response->data = $updatedUser;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Employee updated: %s', $updatedUser->username);
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee OK')
                    ->setObject($employee)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.employee.postupdateemployee.after.commit', array($this, $updatedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postupdateemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postupdateemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postupdateemployee.query.error', array($this, $e));

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
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.employee.postupdateemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Existing Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `user_id`                 (required) - User ID of the employee
     * @param string    `firstname`               (required) - Employee first name (Unchangeable)
     * @param string    `lastname`                (optional) - Employee last name (Unchangeable)
     * @param string    `birthdate`               (optional) - Employee birthdate
     * @param string    `position`                (optional) - Employee position, i.e: 'Cashier 1', 'Supervisor'
     * @param string    `employee_id_char`        (optional) - Employee ID, i.e: 'EMP001', 'CASHIER001'
     * @param string    `username`                (required) - Username used to login (Unchangable)
     * @param string    `password`                (required) - Password for the account
     * @param string    `password_confirmation`   (required) - Confirmation password
     * @param string    `employee_role`           (required) - Role of the employee, i.e: 'cashier', 'manager', 'supervisor'
     * @param array     `retailer_ids`            (optional) - List of Retailer IDs
     * @param string    `cs_verification_numbers` (optional) - Unique verification number
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateMallEmployee()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $employee = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postupdateemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postupdateemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postupdateemployee.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_employee')) {
                Event::fire('orbit.employee.postupdateemployee.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.update_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.employee.postupdateemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');
            $loginId = OrbitInput::post('username');
            $birthdate = OrbitInput::post('birthdate');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');
            $position = OrbitInput::post('position');
            $employeeId = OrbitInput::post('employee_id_char');
            $employeeRole = OrbitInput::post('employee_role');
            $retailerIds = OrbitInput::post('retailer_ids', []);
            $status = OrbitInput::post('status');
            $myRetailerIds = OrbitInput::post('current_mall');
            $csVerificationNumbers = OrbitInput::post('cs_verification_numbers');

            $errorMessage = [
                'orbit.empty.employee.role'         => Lang::get('validation.orbit.empty.employee.role', array(
                    'role' => $employeeRole
                )),
                'orbit.exists.employeeid_but_me'    => 'The employee ID is not available.'
            ];

            $validator = Validator::make(
                array(
                    'current_mall'            => $myRetailerIds,
                    'user_id'                 => $userId,
                    'date_of_birth'           => $birthdate,
                    'password'                => $password,
                    'password_confirmation'   => $password2,
                    'employee_id_char'        => $employeeId,
                    'employee_role'           => $employeeRole,
                    'username'                => $loginId,
                    'retailer_ids'            => $retailerIds,
                    'cs_verification_numbers' => $csVerificationNumbers,
                    'status'                  => $status
                ),
                array(
                    'current_mall'            => 'required|orbit.empty.mall',
                    'user_id'                 => 'required|orbit.empty.user',
                    'date_of_birth'           => 'date_format:Y-m-d',
                    'password'                => 'min:6|confirmed',
                    'employee_role'           => 'orbit.empty.employee.role',
                    'username'                => 'orbit.exists.username.mall_but_me',
                    'employee_id_char'        => 'orbit.exists.employeeid_but_me:' . $myRetailerIds . ',' . $userId,
                    'retailer_ids'            => 'array|min:1|orbit.empty.retailer',
                    'cs_verification_numbers' => 'alpha_num|orbit.exist.verification.numbers_but_me:' . $myRetailerIds . ',' . $userId,
                    'status'                  => 'orbit.empty.user_status',
                ),
                array(
                    'orbit.empty.employee.role'               => $errorMessage['orbit.empty.employee.role'],
                    'orbit.exists.employeeid_but_me'          => $errorMessage['orbit.exists.employeeid_but_me'],
                    'orbit.exist.verification.numbers_but_me' => 'The verification number already used by other',
                    'alpha_num' => 'The verification number must letter and number.',
                )
            );

            Event::fire('orbit.employee.postupdateemployee.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                // WTHell Laravel message wont work!! I need to subtitute manually
                $errorMessage = str_replace('username', 'Email Login', $errorMessage);
                $errorMessage = str_replace('employee id char', 'Employee ID', $errorMessage);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postupdateemployee.after.validation', array($this, $validator));

            $updatedUser = App::make('orbit.empty.user');

            OrbitInput::post('password', function($password) use ($updatedUser) {
                if (! empty(trim($password))) {
                    $updatedUser->user_password = Hash::make($password);
                }
            });

            OrbitInput::post('status', function($status) use ($updatedUser) {
                $updatedUser->status = $status;
            });

            OrbitInput::post('employee_role', function($_role) use ($updatedUser) {
                $role = App::make('orbit.empty.employee.role');
                $updatedUser->user_role_id = $role->role_id;
            });

            $updatedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postupdateemployee.before.save', array($this, $updatedUser));

            $updatedUser->save();
            $updatedUser->apikey;

            // Get the relation
            $employee = $updatedUser->employee;
            $userDetail = $updatedUser->userDetail;
            $userVerificationNumber = $updatedUser->userVerificationNumber;

            OrbitInput::post('position', function($_position) use ($employee) {
                $employee->position = $_position;
            });

            OrbitInput::post('employee_id_char', function($empId) use ($employee) {
                $employee->employee_id_char = $empId;
            });

            $employee->status = $updatedUser->status;
            $employee->touch();
            $employee->save();

            OrbitInput::post('birthdate', function($_birthdate) use ($userDetail) {
                $userDetail->birthdate = $_birthdate;
            });

            $userDetail->save();

            // User verification numbers
            OrbitInput::post('cs_verification_numbers', function($_csVerificationNumbers) use ($userVerificationNumber, $userId, $myRetailerIds) {
                // Validation create, modify, or deleted verification number
                // If sent empty string, data will be deleted
                if ($_csVerificationNumbers === '') {
                    // if any record, will be deleted
                    if (!empty($userVerificationNumber)) {
                        $userVerificationNumber->delete();

                        //Delete link to CS
                        $promotionEmployee = CouponEmployee::where('user_id', $userId);
                        $promotionEmployee->delete();
                    }
                } else {
                    // Updated data verification number
                    // Check exist data, if any data will be updated
                    if (empty($userVerificationNumber)) {
                        $userVerificationNumber = new UserVerificationNumber();
                    }
                    $userVerificationNumber->user_id = $userId;
                    $userVerificationNumber->verification_number = $_csVerificationNumbers;
                    $userVerificationNumber->merchant_id = $myRetailerIds;
                    $userVerificationNumber->touch();
                    $userVerificationNumber->save();
                }
            });

            // @Todo: Remove this hardcode
            $retailerIds = array_merge($retailerIds, (array)$myRetailerIds);

            if ($retailerIds) {
                $employee->retailers()->sync($retailerIds);
            }

            Event::fire('orbit.employee.postupdateemployee.after.save', array($this, $updatedUser));
            $this->response->data = $updatedUser;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Employee updated: %s', $updatedUser->username);
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee OK')
                    ->setObject($employee)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.employee.postupdateemployee.after.commit', array($this, $updatedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postupdateemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postupdateemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postupdateemployee.query.error', array($this, $e));

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
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.employee.postupdateemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_employee')
                    ->setActivityNameLong('Update Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Existing Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`               (required) - User ID of the employee
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteEmployee()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $employee = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postdeleteemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postdeleteemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postdeleteemployee.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_employee')) {
                Event::fire('orbit.employee.postdeleteemployee.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.delete_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.employee.postdeleteemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');

            $validator = Validator::make(
                array(
                    'user_id'               => $userId,
                ),
                array(
                    'user_id'               => 'required|orbit.empty.user'
                )
            );

            Event::fire('orbit.employee.postdeleteemployee.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postdeleteemployee.after.validation', array($this, $validator));

            $deletedUser = App::make('orbit.empty.user');
            $deletedUser->status = 'deleted';
            $deletedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postdeleteemployee.before.save', array($this, $deletedUser));

            $deletedUser->save();

            // Get the relation
            $employee = $deletedUser->employee;
            $employee->status = $deletedUser->status;
            $employee->save();

            Event::fire('orbit.employee.postdeleteemployee.after.save', array($this, $deletedUser));
            $this->response->data = $deletedUser;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Employee Deleted: %s', $deletedUser->username);
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee OK')
                    ->setObject($employee)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.employee.postdeleteemployee.after.commit', array($this, $deletedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postdeleteemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postdeleteemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postdeleteemployee.query.error', array($this, $e));

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
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.employee.postdeleteemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Existing Mall Employee
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer     `user_id`               (required) - User ID of the employee
     * @param password    `password`              (required) - The mall master password
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMallEmployee()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $employee = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.employee.postdeletemallemployee.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.employee.postdeletemallemployee.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.employee.postdeletemallemployee.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_employee')) {
                Event::fire('orbit.employee.postdeletemallemployee.authz.notallowed', array($this, $user));

                $lang = Lang::get('validation.orbit.actionlist.delete_employee');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $lang));

                ACL::throwAccessForbidden($message);
            }
*/
            $role = $user->role;
            $validRoles = ['super admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.employee.postdeletemallemployee.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $userId = OrbitInput::post('user_id');
            $password = OrbitInput::post('password');
            $mall_id = OrbitInput::post('current_mall');;

            $validator = Validator::make(
                array(
                    'current_mall'   => $mall_id,
                    'user_id'        => $userId,
                    'password'       => $password,
                ),
                array(
                    'current_mall'  => 'required|orbit.empty.mall',
                    'user_id'       => 'required|orbit.empty.user',
                    'password'      => 'required|orbit.masterpassword.delete:' . $mall_id,
                )
            );

            Event::fire('orbit.employee.postdeletemallemployee.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.employee.postdeletemallemployee.after.validation', array($this, $validator));

            $deletedUser = App::make('orbit.empty.user');
            $deletedUser->status = 'deleted';
            $deletedUser->modified_by = $this->api->user->user_id;

            Event::fire('orbit.employee.postdeletemallemployee.before.save', array($this, $deletedUser));

            $deletedUser->save();

            // Get the relation
            $employee = $deletedUser->employee;
            $employee->status = $deletedUser->status;
            $employee->save();

            Event::fire('orbit.employee.postdeletemallemployee.after.save', array($this, $deletedUser));
            $this->response->data = $deletedUser;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Employee Deleted: %s', $deletedUser->username);
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee OK')
                    ->setObject($employee)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.employee.postdeletemallemployee.after.commit', array($this, $deletedUser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.employee.postdeletemallemployee.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.employee.postdeletemallemployee.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 400;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.employee.postdeletemallemployee.query.error', array($this, $e));

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
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.employee.postdeletemallemployee.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_employee')
                    ->setActivityNameLong('Delete Employee Failed')
                    ->setObject($employee)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Search employees
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `sort_by`               (optional) - column order by
     * @param string    `sort_mode`             (optional) - asc or desc
     * @param array     `user_ids`              (optional)
     * @param array     `role_ids`              (optional)
     * @param array     `retailer_ids`          (optional)
     * @param array     `merchant_ids`          (optional)
     * @param array     `usernames`             (optional)
     * @param array     `firstnames`            (optional)
     * @param array     `lastname`              (optional)
     * @param array     `statuses`              (optional)
     * @param array     `employee_id_chars`     (optional)
     * @param string    `username_like`         (optional)
     * @param string    `firstname_like`        (optional)
     * @param string    `lastname_like`         (optional)
     * @param array     `employee_id_char_like` (optional)
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @param array     `with`                  (optional) - default to ['employee.retailers', 'userDetail']
     * @return Illuminate\Support\Facades\Response
     */

    public function getSearchEmployee()
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

/*
            if (! ACL::create($user)->isAllowed('view_user')) {
                Event::fire('orbit.user.getsearchuser.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.user.getsearchuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $role_ids = OrbitInput::post('role_ids');
            $validator = Validator::make(
                array(
                    'sort_by'   => $sort_by,
                    'role_ids'  => $role_ids,
                    'with'      => OrbitInput::get('with')
                ),
                array(
                    'sort_by'   => 'in:username,firstname,lastname,registered_date,updated_at,employee_id_char,position',
                    'role_ids'  => 'array|orbit.employee.role.limited',
                    'with'      => 'array|min:1'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.employee_sortby'),
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
            $maxRecord = (int) Config::get('orbit.pagination.employee.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.employee.per_page');
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

            // Builder object
            $joined = FALSE;
            $defaultWith = array('employee.retailers');

            // Include Relationship
            $with = $defaultWith;
            OrbitInput::get('with', function ($_with) use (&$with) {
                $with = array_merge($with, $_with);
            });
            $users = User::with($with)->excludeDeleted('users');

            // Filter user by Ids
            OrbitInput::get('user_ids', function ($userIds) use ($users) {
                $users->whereIn('users.user_id', $userIds);
            });

            // Filter user by Retailer Ids
            OrbitInput::get('retailer_ids', function ($retailerIds) use ($listOfMerchantIds, $joined) {
                // $joined = TRUE;
                // $users->employeeRetailerIds($retailerIds);
                $listOfRetailerIds = (array)$retailerIds;
            });

            // Filter user by Merchant Ids
            OrbitInput::get('merchant_ids', function ($merchantIds) use ($listOfMerchantIds, $joined) {
                // $joined = TRUE;
                // $users->employeeMerchantIds($retailerIds);
                $listOfMerchantIds = (array)$merchantIds;
            });

            // @To do: Repalce this stupid hacks
            if (! $user->isSuperAdmin()) {
                $joined = TRUE;
                $listOfRetailerIds = $user->getMyRetailerIds();

                if (empty($listOfRetailerIds)) {
                    $listOfRetailerIds = [-1];
                }

                $users->employeeRetailerIds($listOfRetailerIds);
            } else {
                if (! empty($listOfRetailerIds)) {
                    $users->employeeRetailerIds($listOfRetailerIds);
                }
            }

            // @To do: Replace this stupid hacks
            if (! $user->isSuperAdmin()) {
                $joined = TRUE;
                $listOfMerchantIds = $user->getMyMerchantIds();

                if (empty($listOfMerchantIds)) {
                    $listOfMerchantIds = [-1];
                }

                $users->employeeMerchantIds($listOfMerchantIds);
            } else {
                if (! empty($listOfMerchantIds)) {
                    $users->employeeMerchantIds($listOfMerchantIds);
                }
            }

            // Filter user by username
            OrbitInput::get('usernames', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstnames', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
            });

            // Filter user by their employee_id_char
            OrbitInput::get('employee_id_char', function ($idChars) use ($users, $joined) {
                $joined = TRUE;
                $users->employeeIdChars($idChars);
            });

           // Filter user by their employee_id_char pattern
            OrbitInput::get('employee_id_char_like', function ($idCharLike) use ($users, $joined) {
                $joined = TRUE;
                $users->employeeIdCharLike($idCharLike);
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

            // Filter user by their status
            OrbitInput::get('statuses', function ($status) use ($users) {
                $users->whereIn('users.status', $status);
            });

            // Filter user by their role id
            OrbitInput::get('role_id', function ($roleId) use ($users) {
                $users->whereIn('users.user_role_id', $roleId);
            });

            if (empty(OrbitInput::get('role_id'))) {
                $invalidRoles = ['super admin', 'administrator', 'consumer', 'customer', 'merchant owner', 'retailer owner', 'guest'];
                $roles = Role::whereIn('role_name', $invalidRoles)->get();

                $ids = array();
                foreach ($roles as $role) {
                    $ids[] = $role->role_id;
                }
                $users->whereNotIn('users.user_role_id', $ids);
            }

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

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy, $users, $joined) {
                if ($_sortBy === 'employee_id_char' || $_sortBy === 'position') {
                    if ($joined === FALSE) {
                        $users->prepareEmployeeRetailer();
                    }
                }

                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'users.created_at',
                    'updated_at'        => 'users.updated_at',
                    'username'          => 'users.username',
                    'employee_id_char'  => 'employees.employee_id_char',
                    'lastname'          => 'users.user_lastname',
                    'firstname'         => 'users.user_firstname',
                    'position'          => 'employees.position'
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

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.getsearchuser.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Mall Employees
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `sort_by`               (optional) - column order by: username,firstname,lastname,registered_date,employee_id_char,position
     * @param string    `sort_mode`             (optional) - asc or desc
     * @param array     `user_ids`              (optional)
     * @param array     `role_names`            (optional)
     * @param array     `retailer_ids`          (optional)
     * @param array     `merchant_ids`          (optional)
     * @param array     `usernames`             (optional)
     * @param array     `firstnames`            (optional)
     * @param array     `lastname`              (optional)
     * @param array     `statuses`              (optional)
     * @param array     `employee_id_chars`     (optional)
     * @param string    `username_like`         (optional)
     * @param string    `firstname_like`        (optional)
     * @param string    `lastname_like`         (optional)
     * @param array     `employee_id_char_like` (optional)
     * @param array     `cs_coupon_redeem_mall` (optional) - cs was have verification number for coupon redeem
     * @param integer   `take`                  (optional) - limit
     * @param integer   `skip`                  (optional) - limit offset
     * @param array     `with`                  (optional) -
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchMallEmployee()
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

/*
            if (! ACL::create($user)->isAllowed('view_user')) {
                Event::fire('orbit.user.getsearchuser.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->employeeViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.user.getsearchuser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $role_ids = OrbitInput::post('role_ids');
            $cs_coupon_redeem_mall = OrbitInput::get('cs_coupon_redeem_mall');


            $listOfRetailerIds = OrbitInput::get('merchant_id', OrbitInput::get('mall_id'));

            $validator = Validator::make(
                array(
                    'merchant_id' => $listOfRetailerIds,
                    'sort_by'   => $sort_by,
                    'role_ids'  => $role_ids,
                    'with'      => OrbitInput::get('with')
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.mall',
                    'sort_by'   => 'in:username,firstname,lastname,registered_date,updated_at,employee_id_char,position,role_name',
                    'role_ids'  => 'array|orbit.employee.role.limited',
                    'with'      => 'array|min:1'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.employee_sortby'),
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
            $maxRecord = (int) Config::get('orbit.pagination.employee.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.employee.per_page');
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

            // Builder object
            $joined = FALSE;

            $users = Employee::excludeDeleted('employees')->joinUserRole()
                             ->select('employees.*', 'users.username',
                                     'users.username as login_id', 'users.user_email',
                                     'users.status as user_status',
                                     'users.user_firstname', 'users.user_lastname',
                                     'roles.role_name')
                             ->groupBy('employees.user_id');

            // Include Relationship
            $defaultWith = array();
            $with = $defaultWith;
            OrbitInput::get('with', function ($_with) use (&$with, $users) {
                $with = array_merge($with, $_with);
                $users->with($with);
            });

            // Filter user by Ids
            OrbitInput::get('user_ids', function ($userIds) use ($users) {
                $users->whereIn('users.user_id', $userIds);
            });

            // Filter user by Retailer Ids
            $listOfRetailerIds = (array)$listOfRetailerIds;
            OrbitInput::get('retailer_ids', function ($retailerIds) use ($listOfRetailerIds, $joined) {
                // $joined = TRUE;
                // $users->employeeRetailerIds($retailerIds);
                $listOfRetailerIds = (array)$retailerIds;
            });

            // Filter user by Merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($listOfMerchantIds, $joined, $users) {
                // $joined = TRUE;
                // $users->employeeMerchantIds($retailerIds);
                $listOfMerchantIds = (array)$merchantIds;
                $users->whereHas('retailers', function ($q) use($merchantIds) {
                    $q->whereIn('employee_retailer.retailer_id', $merchantIds);
                });
            });

            // Filter user by username
            OrbitInput::get('usernames', function ($username) use ($users) {
                $users->whereIn('users.username', $username);
            });

            // Filter user by matching username pattern
            OrbitInput::get('username_like', function ($username) use ($users) {
                $users->where('users.username', 'like', "%$username%");
            });

            // Filter user by their firstname
            OrbitInput::get('firstnames', function ($firstname) use ($users) {
                $users->whereIn('users.user_firstname', $firstname);
            });

            // Filter user by their employee_id_char
            OrbitInput::get('employee_id_char', function ($idChars) use ($users, $joined) {
                $users->whereIn('employess.employee_id_char', $idChars);
            });

           // Filter user by their employee_id_char pattern
            OrbitInput::get('employee_id_char_like', function ($idCharLike) use ($users, $joined) {
                $users->whereIn('employess.employee_id_char', 'like', "%$idCharLike%");
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

            // Filter user by their status
            OrbitInput::get('statuses', function ($status) use ($users) {
                $status = (array)$status;
                $users->whereIn('users.status', $status);
            });

            // Filter user by their role id
            OrbitInput::get('role_id', function ($roleId) use ($users) {
                $roleId = (array)$roleId;
                $users->whereIn('users.user_role_id', $roleId);
            });

            // Filter user by their role name
            if (! is_null(OrbitInput::get('role_names', NULL))) {
                OrbitInput::get('role_names', function ($data) use ($users) {
                    $data = (array)$data;
                    foreach ($data as $employeeRoleName) {
                        if (! in_array(strtolower($employeeRoleName), ['mall admin', 'mall customer service'])) {
                            $errorMessage = 'Employee role_name argument is not valid.';
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    $users->whereIn('roles.role_name', $data);
                });
            } else {
                $users->whereIn('roles.role_name', ['mall admin', 'mall customer service']);
            }

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

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy, $users, $joined) {
                if ($_sortBy === 'employee_id_char' || $_sortBy === 'position') {
                    if ($joined === FALSE) {
                        $users->prepareEmployeeRetailer();
                    }
                }

                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'users.created_at',
                    'create_at'         => 'users.created_at',
                    'updated_at'        => 'employees.updated_at',
                    'username'          => 'users.username',
                    'login_id'          => 'users.username',
                    'employee_id_char'  => 'employees.employee_id_char',
                    'lastname'          => 'users.user_lastname',
                    'firstname'         => 'users.user_firstname',
                    'position'          => 'employees.position',
                    'email'             => 'users.email',
                    'role_name'         => 'roles.role_name',
                    'status'            => 'users.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            // If sortby not active means we should add active as second argument
            // of sorting

            if ($cs_coupon_redeem_mall === '' || $cs_coupon_redeem_mall === null) {
                if ($sortBy !== 'users.status') {
                    $users->orderBy('users.status', 'asc');
                }
            }

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
                $this->response->message = 'xx'; // Lang::get('statuses.orbit.nodata.user');
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

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.user.getsearchuser.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check username, it should not exists
        Validator::extend('orbit.exists.username', function ($attribute, $value, $parameters) {
            $merchantowner = $this->api->user;
            $retailer_ids = $merchantowner->getMyRetailerIds();
            $merchant_ids = implode(', ', $merchantowner->getMyMerchantIds());

            if (! empty($retailer_ids) && ! empty($merchant_ids)) {
                $user = User::whereHas('employee',  function($q) use($retailer_ids, $merchant_ids) {
                                $q->whereHas('retailers', function($q) use($retailer_ids, $merchant_ids) {
                                    $q->whereIn('employee_retailer.retailer_id', $retailer_ids);
                                    $prefix = DB::getTablePrefix();
                                    $q->whereRaw("(SELECT COUNT(*) from {$prefix}merchants m, {$prefix}merchants r
                                        WHERE m.merchant_id = r.parent_id AND m.merchant_id not in (?)) >= 1",
                                        array($merchant_ids)
                                    );
                                });
                            })
                            ->excludeDeleted()
                            ->where('username', $value)
                            ->first();
            } else {
                return FALSE;
            }

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.username', $user);

            return TRUE;
        });

        // Check username, it should not exists
        Validator::extend('orbit.exists.username.mall', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where(function ($q) use ($value) {
                            $q->where('user_email', '=', $value)
                              ->orWhere('username', '=', $value);
                          })
                        ->whereIn('user_role_id', function ($q) {
                                $q->select('role_id')
                                  ->from('roles')
                                  ->whereNotIn('role_name', ['Consumer','Guest']);
                          })
                        ->first();

            if (! empty($user)) {
                OrbitShopAPI::throwInvalidArgument('The email address has already been taken.');
            }

            App::instance('orbit.validation.mallemployee', $user);

            return TRUE;
        });

        // Check username, it should not exists
        Validator::extend('orbit.exists.username.mall_but_me', function ($attribute, $value, $parameters) {
            $userId = OrbitInput::post('user_id');
            $user = Employee::joinUser()
                            ->where('users.username', $value)
                            ->where('users.user_id', '!=', $userId)
                            ->first();

            if (! empty($user)) {
                OrbitShopAPI::throwInvalidArgument('The email address has already been taken.');
            }

            App::instance('orbit.validation.mallemployee', $user);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
            $user = User::with('employee')
                        ->excludeDeleted()
                        ->where('user_id', $value)
                        ->first();

            if (empty($user) || empty($user->employee)) {
                return FALSE;
            }

            App::instance('orbit.empty.user', $user);

            return TRUE;
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

        // Check the existance of the Status
        Validator::extend('orbit.empty.user_status', function ($attribute, $value, $parameters) {
            $statuses = array('active', 'pending', 'blocked', 'deleted', 'inactive');
            if (! in_array($value, $statuses)) {
                return FALSE;
            }

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

        Validator::extend('orbit.empty.employee.role', function ($attribute, $value, $parameters) {
            $invalidRoles = array('super admin', 'administrator', 'consumer', 'customer', 'merchant owner', 'retailer owner', 'mall owner');
            if (in_array(strtolower($value), $invalidRoles)) {
                return FALSE;
            }

            $role = Role::where('role_name', $value)->first();

            if (empty($role)) {
                return FALSE;
            }

            App::instance('orbit.empty.employee.role', $role);

            return TRUE;
        });

        Validator::extend('orbit.employee.role.limited', function ($attribute, $value, $parameters) {
            $invalidRoles = ['super admin', 'administrator', 'consumer', 'customer', 'merchant owner', 'retailer owner', 'guest'];
            $roles = Role::whereIn('role_name', $invalidRoles)->get();

            if ($roles) {
                foreach ($roles as $role) {
                    foreach ($value as $roleId) {
                        if ((string)$roleId === (string)$role->role_id) {
                            // This role Id is not allowed
                            return FALSE;
                        }
                    }
                }
            }

            App::instance('orbit.employee.role.limited', $value);

            return TRUE;
        });

        Validator::extend('orbit.exists.employeeid', function ($attribute, $value, $parameters) {
            $mall_id = $parameters[0];

            $employee = Employee::excludeDeleted()
                                ->join('employee_retailer', 'employee_retailer.employee_id', '=', 'employees.employee_id')
                                ->where('employee_id_char', $value)
                                ->where('employee_retailer.retailer_id', $mall_id)
                                ->first();

            if (! empty($employee)) {
                App::instance('orbit.exists.employeeid', $employee);
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.exists.employeeid_but_me', function ($attribute, $value, $parameters) {
            $mall_id = $parameters[0];
            $user_id = $parameters[1];

            $employee = Employee::excludeDeleted()
                                ->join('employee_retailer', 'employee_retailer.employee_id', '=', 'employees.employee_id')
                                ->where('employee_id_char', $value)
                                ->where('employee_retailer.retailer_id', $mall_id)
                                ->where('user_id', '!=', $user_id)
                                ->first();

            if (! empty($employee)) {
                App::instance('orbit.exists.employeeid', $employee);
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.empty.retailer', function ($attribute, $retailerIds, $parameters) {
            if (! is_array($retailerIds)) {
                return FALSE;
            }

            $number = count($retailerIds);
            $user = $this->api->user;
            $realNumber = Mall::allowedForUser($user)
                                  ->excludeDeleted()
                                  ->whereIn('merchant_id', $retailerIds)
                                  ->count();

            if ((string)$realNumber !== (string)$number) {
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

        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->whereIn('merchant_id', (array)$value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.exist.verification.numbers', function ($attribute, $value, $parameters) {
            $merchant_id = $parameters[0];

            $csVerificationNumber = UserVerificationNumber::
                        where('verification_number', $value)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            // Check the tenants which has verification number posted
            $tenantVerificationNumber = Tenant::excludeDeleted()
                    ->where('object_type', 'tenant')
                    ->where('masterbox_number', $value)
                    ->where('parent_id', $merchant_id)
                    ->first();

            if (! empty($csVerificationNumber) || ! empty($tenantVerificationNumber)) {
                return FALSE;
            }

            App::instance('orbit.exist.verification.numbers', $csVerificationNumber);

            return TRUE;
        });

        Validator::extend('orbit.exist.verification.numbers_but_me', function ($attribute, $value, $parameters) {
            // dd('dfawdawdawd');
            $merchant_id = $parameters[0];
            $user_id = $parameters[1];

            $csVerificationNumber = UserVerificationNumber::
                        where('verification_number', $value)
                        ->where('merchant_id', $merchant_id)
                        ->where('user_id', '!=', $user_id)
                        ->first();

            // Check the tenants which has verification number posted
            $tenantVerificationNumber = Tenant::excludeDeleted()
                    ->where('object_type', 'tenant')
                    ->where('masterbox_number', $value)
                    ->where('parent_id', $merchant_id)
                    ->first();

            if (! empty($csVerificationNumber) || ! empty($tenantVerificationNumber)) {
                return FALSE;
            }

            App::instance('orbit.exist.verification.numbers', $csVerificationNumber);

            return TRUE;
        });
    }


}
