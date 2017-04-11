<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreatePageCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'page:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for create spesific page with spesific language.';

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

            $country = trim($data['country']);
            $object_type = trim($data['object_type']);
            $language = trim($data['language']);
            $content = trim($data['content']);
            $status = trim($data['status']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'country'     => $country,
                    'object_type' => $object_type,
                    'language'    => $language,
                    'content'     => $content,
                    'status'      => $status,
                ),
                array(
                    'country'     => 'required|orbit.exist.country',
                    'language'    => 'required|orbit.exist.language',
                    'content'     => 'required',
                    'status'      => 'required|in:active,inactive,deleted',
                ),
                array(
                    'orbit.exist.country'     => 'Country is invalid',
                    'orbit.exist.language'    => 'language is invalid',
                    'orbit.exist.status'      => 'Status is invalid',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            //Get country id
            $getCountry = Country::where('name', $country)->first();

            //Check insert or update when exist
            $existPage = Page::where('object_type', $object_type)
                        ->where('country_id', $getCountry->country_id)
                        ->where('language', $language)
                        ->first();

            DB::beginTransaction();

            if (empty($existPage)) {
                $mode = 'Create';
                $newPage = new Page();
                $newPage->country_id = $getCountry->country_id;
                $newPage->object_type = $object_type;
                $newPage->language = $language;
                $newPage->content = $content;
                $newPage->status = $status;
                $newPage->save();
            } else {
                $mode = 'Update';
                $updatePage = Page::find($existPage->pages_id);
                $updatePage->country_id = $getCountry->country_id;
                $updatePage->object_type = $object_type;
                $updatePage->language = $language;
                $updatePage->content = $content;
                $updatePage->status = $status;
                $updatePage->save();
            }

            DB::commit();

            $this->info( sprintf('%s page with type %s with country = %s, language = %s, and status = %s successfully.', $mode, $object_type, $country, $language, $status) );

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    protected function registerCustomValidation()
    {
        // Check the existence of the news object type
        Validator::extend('orbit.exist.country', function ($attribute, $value, $parameters) {
            $valid = false;

            $existCountryId = Country::where('name', $value)->first();
            if (! empty($existCountryId)) {
                $valid = true;
            }

            return $valid;
        });

        // Check the existence of the news object type
        Validator::extend('orbit.exist.language', function ($attribute, $value, $parameters) {
            $valid = false;

            $existCountryId = Language::where('name', $value)->first();
            if (! empty($existCountryId)) {
                $valid = true;
            }

            return $valid;
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
