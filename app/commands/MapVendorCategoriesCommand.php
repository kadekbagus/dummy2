<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MapVendorCategoriesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'vendor:map-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for map vendor and gtm categories from json file.';

    protected $valid_vendor_category = NULL;
    protected $valid_gtm_category = NULL;

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
            $validator_value = [];
            $validator_check = [];

            if ($fileName === 'stdin') {
                $json = file_get_contents('php://stdin');
                $data = $this->readJSONString($json);
            } else {
                $data = $this->readJSON($fileName);
            }

            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->info('[DRY RUN MODE - Not Insert on DB] ');
            }

            $vendor_type = trim($data['vendor_type']);
            $vendor_category = trim($data['vendor_category']);
            $gtm_categories = $data['gtm_categories'];

            $validator_value = [
                'vendor_type'        => $vendor_type,
                'vendor_category' => $vendor_category,
                'gtm_categories'    => $gtm_categories,
            ];

            $validator_check = [
                'vendor_type'        => 'required|in:grab',
                'vendor_category' => 'required|orbit.empty.vendor_category:' . $vendor_type,
                'gtm_categories'    => 'required|array',
            ];

            $this->registerCustomValidation();

            $validator = Validator::make(
                $validator_value,
                $validator_check
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            $valid_vendor_category = $this->valid_vendor_category;

            foreach ($gtm_categories as $key => $gtm_category) {
                $validator_value = [
                    'gtm_category'    => $gtm_category,
                ];

                $validator_check = [
                    'gtm_category'    => 'orbit.empty.gtm_category',
                ];

                $this->registerCustomValidation();

                $validator = Validator::make(
                    $validator_value,
                    $validator_check
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    throw new Exception($errorMessage);
                }

                $valid_gtm_category = $this->valid_gtm_category;

                $vendor_gtm_category = VendorGTMCategory::where('vendor_type', $vendor_type)
                                        ->where('vendor_category_id', $valid_vendor_category->grab_category_id)
                                        ->where('gtm_category_id', $valid_gtm_category->category_id)
                                        ->first();

                if (empty($vendor_gtm_category)) {
                    // create
                    $newvendor_category = new VendorGTMCategory();
                    $newvendor_category->vendor_type = $vendor_type;
                    $newvendor_category->vendor_category_id = $valid_vendor_category->grab_category_id;
                    $newvendor_category->gtm_category_id = $valid_gtm_category->category_id;

                    if (! $dryRun) {
                        $newvendor_category->save();
                    }

                    $this->info( sprintf('Mapping Vendor Category name: %s, Vendor type: %s, to GTM Category %s', $vendor_category, $vendor_type, $gtm_category) );
                } else {
                    $this->info( sprintf('Already exists Vendor Category name: %s, Vendor type: %s, to GTM Category %s', $vendor_category, $vendor_type, $gtm_category) );
                }
            }

            $this->info("Done");
        } catch (Exception $e) {
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }


    protected function registerCustomValidation()
    {
        // Check the existance of grab category
        Validator::extend('orbit.empty.vendor_category', function ($attribute, $value, $parameters) {
            $vendor = $parameters[0];

            if ($vendor === 'grab') {
                $check_category = GrabCategory::where('category_name', $value)->first();
            }

            if (empty($check_category)) {
                return FALSE;
            }

            $this->valid_vendor_category = $check_category;
            return TRUE;
        });

        // Check the existance of gtm category
        Validator::extend('orbit.empty.gtm_category', function ($attribute, $value, $parameters) {
            $check_category = Category::where('status', 'active')
                ->where('category_name', $value)
                ->where('merchant_id', '0')
                ->first();

            if (empty($check_category)) {
                return FALSE;
            }

            $this->valid_gtm_category = $check_category;
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to db_ip_cities.', null),
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
        );
    }

}
