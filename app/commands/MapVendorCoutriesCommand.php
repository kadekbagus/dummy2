<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MapVendorCoutriesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'vendor:map-countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for map vendor and gtm country from json file.';

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

            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->info('[DRY RUN MODE - Not Insert on DB] ');
            }

            $vendor_country = trim($data['vendor_country']);
            $gtm_country = trim($data['gtm_country']);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'vendor_country'   => $vendor_country,
                    'gtm_country'       => $gtm_country,
                ),
                array(
                    'vendor_country'   => 'required|orbit.empty.vendor_country',
                    'gtm_country'       => 'required|orbit.empty.gtm_country',
                ),
                array(
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            $check_vendor_country = VendorGTMCountry::where('vendor_country', $vendor_country)
                                        ->where('gtm_country', $gtm_country)
                                        ->first();

            if (empty($check_vendor_country)) {
                $newvendorcountry = new VendorGTMCountry();
                $newvendorcountry->vendor_country = $vendor_country;
                $newvendorcountry->gtm_country = $gtm_country;

                if (! $dryRun) {
                    // Insert user
                    $newvendorcountry->save();
                }
                $this->info( sprintf('Mapping Vendor Country %s to GTM Country %s.', $vendor_country, $gtm_country) );
            } else {
                $this->info( sprintf('Vendor Country %s to GTM Country %s, already exist.', $vendor_country, $gtm_country) );
            }
            $this->info("Done");
        } catch (Exception $e) {
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }


    protected function registerCustomValidation()
    {
        // Check the existance of db_ip country
        Validator::extend('orbit.empty.vendor_country', function ($attribute, $value, $parameters) {
            $check_country = DBIPCountry::where('country', $value)->first();

            if (empty($check_country)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of mall country
        Validator::extend('orbit.empty.gtm_country', function ($attribute, $value, $parameters) {
            $check_country = MallCountry::where('country', $value)->first();

            if (empty($check_country)) {
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to db_ip_cities.', null),
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
        );
    }

}
