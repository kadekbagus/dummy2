<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateBankCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'bank:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for create bank via json file.';

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

            $bank_name = trim($data['bank_name']);
            $description = trim($data['description']);
            $status = trim($data['status']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'bank_name'      => $bank_name,
                    'description' => $description,
                    'status' => $status,
                ),
                array(
                    'bank_name'      => 'required|orbit.exist.bank_name',
                    'description' => 'required',
                    'status' => 'required|in:active,inactive',
                ),
                array(
                    'orbit.exist.bank_name'   => 'Email already exist',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            // Insert user
            DB::beginTransaction();

            $newuser = new Bank();
            $newuser->bank_name = $bank_name;
            $newuser->description = $description;
            $newuser->status = $status;
            $newuser->save();

            DB::commit();

            $this->info( sprintf('Bank with bank_name %s successfully created.', $data['bank_name']) );

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    protected function registerCustomValidation()
    {
        // Check the existance of user bank_name
        Validator::extend('orbit.exist.bank_name', function ($attribute, $value, $parameters) {
            $checkEmail = Bank::excludeDeleted()->where('bank_name', $value)->first();

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
