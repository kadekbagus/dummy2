<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateUserCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for import user from json file. ';

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

        $conf = @json_decode(file_get_contents($file), true);
        $basefile = $basefile = basename($file);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error JSON %s: %s', $basefile, json_last_error_msg()) );
        }

        return $conf;
    }

    /**
     * Read the json file.
     */
    protected function validate()
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $conf = @json_decode(file_get_contents($file), true);
        $basefile = $basefile = basename($file);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error JSON %s: %s', $basefile, json_last_error_msg()) );
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
            $basefile = basename($fileName);

            $data = $this->readJSON($fileName);
            $email = trim($data['email']);
            $first_name = trim($data['first_name']);
            $last_name = trim($data['last_name']);
            $password = trim($data['password']);
            $role = trim($data['role']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'      => $email,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'password'   => $password,
                    'role'       => $role,
                ),
                array(
                    'email'      => 'required|orbit.exist.email',
                    'first_name' => 'required',
                    'last_name'  => 'required',
                    'password'   => 'required|min:6',
                    'role'       => 'required|orbit.exist.role_id',
                ),
                array(
                    'orbit.exist.email'   => 'Email already exist',
                    'orbit.exist.role_id' => 'Role name is invalid',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            $checkRole = Role::where('role_name', $role)->first();

            // Insert user
            DB::beginTransaction();

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->status = 'active';
            $newuser->user_role_id = $checkRole->role_id;
            $newuser->user_password = Hash::make($password);
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

            DB::commit();

            $this->info( sprintf('User with email %s successfully created as a %s.', $data['email'], $data['role']) );

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }


    protected function registerCustomValidation()
    {
        // Check the existance of user email
        Validator::extend('orbit.exist.email', function ($attribute, $value, $parameters) {
            $checkEmail = User::where('user_email', $value)->first();

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
