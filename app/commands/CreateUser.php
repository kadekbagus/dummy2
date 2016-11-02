<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateUser extends Command {

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

            $fileName = $this->option('file');
            $basefile = basename($fileName);

            $data = $this->readJSON($fileName);
            $data['email'] = trim($data['email']);
            $data['first_name'] = trim($data['first_name']);
            $data['last_name'] = trim($data['last_name']);
            $data['password'] = trim($data['password']);
            $data['role'] = trim($data['role']);

            // Validation
            $roleAllow = ['Merchant Database Admin'];

            $checkRole = Role::where('role_name', $data['role'])->first();
            if (empty($checkRole)) {
                throw new Exception('Role is invalid');
            }

            $checkUserEmail = User::where('user_email', $data['email'])->first();
            if (! empty($checkUserEmail)) {
                throw new Exception('User email already exist');
            }

            if (strlen($data['password']) < 6) {
                throw new Exception('Password must be least 6 character');
            }

            // Insert user
            DB::beginTransaction();

            $newuser = new User();
            $newuser->username = $data['email'];
            $newuser->user_email = $data['email'];
            $newuser->status = 'active';
            $newuser->user_role_id = $checkRole->role_id;
            $newuser->user_password = Hash::make($data['password']);
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
            array('file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
		);
	}

}
