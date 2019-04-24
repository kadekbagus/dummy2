<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateUserMallCSCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-mall-cs:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for creating user for mall cs from json file.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Read the json file.
     */
    protected function readJSON($file)
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $json = file_get_contents($file);
        return $this->readJSONString($json);
    }

    /**
     * Read JSON from string
     *
     * @return string|mixed
     */
    protected function readJSONString($json)
    {
        $conf = @json_decode($json, TRUE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error parsing JSON: %s', json_last_error_msg()) );
        }

        return $conf;
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {
            $fileName = $this->option('json-file');
            $data = '';

            if ($fileName === 'stdin') {
                $json = file_get_contents('php://stdin');
                $data = $this->readJSONString($json);
            } else {
                $data = $this->readJSON($fileName);
            }

            $email = trim($data['email']);
            $firstName = trim($data['first_name']);
            $lastName = trim($data['last_name']);
            $password = trim($data['password']);
            $roleName = 'Mall Customer Service';
            $mallId = trim($data['mall_id']);
            $csVerificationNumbers = trim($data['cs_verification_numbers']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'                   => $email,
                    'firstname'               => $firstName,
                    'lastname'                => $lastName,
                    'password'                => $password,
                    'mall_id'                 => $mallId,
                    'cs_verification_numbers' => $csVerificationNumbers,
                ),
                array(
                    'email'                   => 'email|orbit.exist.email',
                    'firstname'               => 'required',
                    'lastname'                => 'required',
                    'password'                => 'required|min:6',
                    'mall_id'                 => 'required|orbit.empty.mall',
                    'cs_verification_numbers' => 'alpha_num|orbit.exist.verification.numbers:' . $mallId ,
                ),
                array(
                    'orbit.exist.email' => 'Email is already exist',
                    'orbit.empty.mall' => 'Mall is not found',
                    'orbit.exist.verification.numbers' => 'The verification number already used by other',
                    'alpha_num' => 'The verification number must letter and number.',
                )
            );

            // Begin database transaction
            DB::beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();

                throw new Exception($errorMessage, 1);
            }

            $role = Role::where('role_name', $roleName)->first();
            if (! is_object($role)) {
                throw new Exception("Role is not found.", 1);
            }

            $newUser = new User();
            $newUser->username = $email;
            $newUser->user_email = $email;
            $newUser->user_password = Hash::make($password);
            $newUser->status = 'active';
            $newUser->user_role_id = $role->role_id;
            $newUser->modified_by = '1';
            $newUser->user_firstname = $firstName;
            $newUser->user_lastname = $lastName;

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
            $userdetail->user_id = $newUser->user_id;
            $userdetail = $newUser->userdetail()->save($userdetail);

            $newUser->setRelation('userDetail', $userdetail);

            $newEmployee = new Employee();
            $newEmployee->user_id = $newUser->user_id;
            $newEmployee->status = 'active';
            $newEmployee = $newUser->employee()->save($newEmployee);

            $newUser->setRelation('employee', $newEmployee);

            // User verification numbers
            // check if the role mall admin or mall customer service should have verification number
            $newUserVerificationNumber = new UserVerificationNumber();
            $newUserVerificationNumber->user_id = $newUser->user_id;
            $newUserVerificationNumber->verification_number = $csVerificationNumbers;
            $newUserVerificationNumber->merchant_id = $mallId;
            $newUserVerificationNumber->save();
            $newUser->setRelation('userVerificationNumber', $newUserVerificationNumber);

            // @Todo: Remove this hardcode
            $mallIds = [$mallId];
            $newEmployee->retailers()->sync($mallIds);

            // Commit the changes
            DB::commit();

            $this->info( sprintf('User with email %s successfully created as a %s.', $email, $roleName) );

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    protected function registerCustomValidation()
    {
        // Check the existance of user email
        Validator::extend('orbit.exist.email', function ($attribute, $value, $parameters) {
            $checkEmail = User::excludeDeleted()->where('user_email', $value)->first();

            if (! empty($checkEmail)) {
                return FALSE;
            }
            return TRUE;
        });

        // Check the existance of role id
        Validator::extend('orbit.exist.role_id', function ($attribute, $value, $parameters) {
            $checkRole = Role::where('role_name', $value)->first();
            if (empty($checkRole)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of role id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $checkMall = Mall::excludeDeleted()->where('merchant_id', $value)->first();

            if (! is_object($checkMall)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.exist.verification.numbers', function ($attribute, $value, $parameters) {
            $mallId = $parameters[0];

            $csVerificationNumber = UserVerificationNumber::where('verification_number', $value)
                        ->where('merchant_id', $mallId)
                        ->first();

            // Check the tenants which has verification number posted
            $tenantVerificationNumber = Tenant::excludeDeleted()
                    ->where('object_type', 'tenant')
                    ->where('masterbox_number', $value)
                    ->where('parent_id', $mallId)
                    ->first();

            if (! empty($csVerificationNumber) || ! empty($tenantVerificationNumber)) {
                return FALSE;
            }

            App::instance('orbit.exist.verification.numbers', $csVerificationNumber);

            return TRUE;
        });
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
        );
    }

}
