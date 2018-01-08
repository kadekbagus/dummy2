<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateUserMerchantReviewCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-merchant-review:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for import user merchant review from json file.';

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
            $first_name = trim($data['first_name']);
            $last_name = trim($data['last_name']);
            $password = trim($data['password']);
            $role = trim($data['role']);
            $object_type = trim($data['object_type']);
            $merchant_or_store_id = trim($data['merchant_or_store_id']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'                => $email,
                    'first_name'           => $first_name,
                    'last_name'            => $last_name,
                    'password'             => $password,
                    'role'                 => $role,
                    'object_type'          => $object_type,
                    'merchant_or_store_id' => $merchant_or_store_id,
                ),
                array(
                    'email'                => 'required|orbit.exist.email',
                    'first_name'           => 'required',
                    'last_name'            => 'required',
                    'password'             => 'required|min:6',
                    'role'                 => 'required|orbit.exist.role_id',
                    'object_type'          => 'required|in:merchant,store,gtm,mall',
                    'merchant_or_store_id' => 'required|orbit.exist.merchant_or_store_id:' . $object_type . ',' . $merchant_or_store_id . ',' . $role,
                ),
                array(
                    'orbit.exist.email'                     => 'Email already exist',
                    'orbit.exist.role_id'                   => 'Role name is invalid',
                    'orbit.exist.merchant_or_store_id'      => 'Base merchant or store id name is invalid',
                    'orbit.exist.user_merchant_transaction' => 'User merchant review with this merchant already exist',
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
            $newuser->user_firstname = $first_name;
            $newuser->user_lastname = $last_name;
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

            $usermerchantreview = new UserMerchantReview();
            $usermerchantreview->merchant_id = $merchant_or_store_id;
            $usermerchantreview->user_id = $newuser->user_id;
            $usermerchantreview->object_type = $object_type;
            $usermerchantreview->save();

            $newuser->setRelation('apikey', $apikey);
            $newuser->apikey = $apikey;
            $newuser->setHidden(array('user_password'));


            DB::commit();

            $this->info( sprintf('User Merchant Review with email %s successfully created as a %s.', $data['email'], $data['role']) );

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
        Validator::extend('orbit.exist.merchant_or_store_id', function ($attribute, $value, $parameters) {
            $object_type = $parameters[0];
            $merchant_or_store_id = $parameters[1];
            $role_name = $parameters[2];

            if ($merchant_or_store_id == 0 && $role_name == 'Master Review Admin') {
                return TRUE;
            }

            if ($object_type === 'merchant') {
                $checkMerchantOrStore = BaseMerchant::where('base_merchant_id', $value)->first();
            } else if ($object_type === 'store') {
                $checkMerchantOrStore = BaseStore::where('base_store_id', $value)->first();
            } else if ($object_type === 'mall') {
                $checkMerchantOrStore = Mall::where('merchant_id', $value)->first();
            }

            if (empty($checkMerchantOrStore)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of user merchant transaction
        Validator::extend('orbit.exist.user_merchant_transaction', function ($attribute, $value, $parameters) {
            $user_id = $parameters[0];
            $base_merchant_id = $parameters[1];

            $checkUserMerchantTransaction = BaseMerchant::where('merchant_id', $base_merchant_id)
                                                ->where('user_id', $user_id)
                                                ->where('object_type', 'merchant')
                                                ->first();
            if (empty($checkUserMerchantTransaction)) {
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
