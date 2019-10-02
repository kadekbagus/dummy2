<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateUserRGPCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'user-rgp:create';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command for import user from json file.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	protected function readJSON($file)
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $json = file_get_contents($file);
        return $this->readJSONString($json);
    }

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
            $username = trim($data['username']);
            $password = trim($data['password']);
            $name = trim($data['name']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'      => $email,
                    'username' 	 => $username,
                    'password'   => $password,
                ),
                array(
                    'email'      => 'required|orbit.exist.email',
                    'username'   => 'required',
                    'password'   => 'required|min:6',
                ),
                array(
                    'orbit.exist.email'   => 'Email already exist',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            // Insert user
            DB::beginTransaction();

            $newuser = new RgpUser();
            $newuser->email = $email;
            $newuser->username = $username;
            $newuser->name = $name;
            $newuser->status = 'active';
            $newuser->password = Hash::make($password);
            $newuser->save();

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newuser);
            $apikey->api_secret_key = Apikey::genSecretKey($newuser);
            $apikey->status = 'active';
            $apikey->user_id = $newuser->rgp_user_id;
            $apikey = $newuser->apikey()->save($apikey);

            $newuser->setRelation('apikey', $apikey);
            $newuser->apikey = $apikey;
            $newuser->setHidden(array('password'));
            $data['role'] = 'RGP user';

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
            $checkEmail = RgpUser::excludeDeleted()->where('email', $value)->first();

            if (! empty($checkEmail)) {
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
