<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateUserBPPCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-bpp:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for import user bpp from json file.';

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
            $name = trim($data['name']);
            $password = trim($data['password']);
            $status = trim($data['status']);
            $user_type = trim($data['user_type']);
            $base_merchant_id = trim($data['base_merchant_id']);
            $merchant_ids = isset($data['merchant_ids']) ? ($data['merchant_ids']) : null;
            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'              => $email,
                    'name'               => $name,
                    'password'           => $password,
                    'base_merchant_id'   => $base_merchant_id,
                ),
                array(
                    'email'              => 'required|orbit.exist.email',
                    'name'               => 'required',
                    'password'           => 'required|min:6',
                    'base_merchant_id'   => 'required',
                ),
                array(
                    'orbit.exist.email'  => 'Email already exist',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }
            // Insert user
            DB::beginTransaction();

            $newUser = new BppUser();
            $newUser->name = $name;
            $newUser->email = $email;
            $newUser->password = Hash::make($password);
            $newUser->status = $status;
            $newUser->user_type = $user_type;
            $newUser->base_merchant_id = ($user_type === 'gtm_admin') ? NULL : $base_merchant_id;
            $newUser->merchant_id = is_array($merchant_ids) ? $merchant_ids[0] : $merchant_ids;
            $newUser->save();

            // link to merchant/store
            if ($user_type === 'store') {
                foreach($merchant_ids as $key => $value) {
                    $newUserMerchant = new BppUserMerchant();
                    $newUserMerchant->bpp_user_id = $newUser->bpp_user_id;
                    $newUserMerchant->merchant_id = $value;
                    $newUserMerchant->save();
                }
            }

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newUser);
            $apikey->api_secret_key = Apikey::genSecretKey($newUser);
            $apikey->status = 'active';
            $apikey->user_id = $newUser->bpp_user_id;
            $apikey = $newUser->apikey()->save($apikey);

            $newUser->setRelation('apikey', $apikey);
            $newUser->apikey = $apikey;
            $newUser->setHidden(array('password'));
            $data['role'] = 'BPP user';

            DB::commit();

            $this->info( sprintf('User with email %s successfully created as a %s.', $data['email'], $data['role']) );
            ($user_type === 'store') ? $this->info( sprintf('And linked to %s stores', count($merchant_ids)) ) : null;

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    protected function registerCustomValidation()
    {
        // Check the existance of user email
        Validator::extend('orbit.exist.email', function ($attribute, $value, $parameters) {
            $checkEmail = BppUser::excludeDeleted()->where('email', $value)->first();

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
