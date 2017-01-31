<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MapVendorCitiesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'vendor:map-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for map vendor and gtm cities from json file.';

    protected $valid_gtm_country = NULL;

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
            $validation_data = [];
            $validation_error = [];

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

            $gtm_country = trim($data['country']);
            $vendor_city = trim($data['vendor_city']);
            $gtm_cities = $data['gtm_cities'];

            $validation_data = [
                'gtm_country' => $gtm_country,
                'vendor_city' => $vendor_city,
                'gtm_cities'  => $gtm_cities,
            ];

            $validation_error = [
                'gtm_country' => 'required|orbit.empty.gtm_country',
                'vendor_city' => 'required|orbit.empty.vendor_city',
                'gtm_cities'  => 'required|array',
            ];

            foreach ($gtm_cities as $key => $gtm_city) {
                $validation_data['gtm_city_' . $gtm_city] = $gtm_city;
                $validation_error['gtm_city_' . $gtm_city] = 'orbit.empty.gtm_city';
            }

            $this->registerCustomValidation();

            $validator = Validator::make(
                $validation_data,
                $validation_error
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            $valid_gtm_country = $this->valid_gtm_country;

            foreach ($gtm_cities as $key => $gtm_city) {
                $check_vendor_city = VendorGTMCity::where('vendor_city', $vendor_city)
                                            ->where('gtm_city', $gtm_city)
                                            ->where('country_id', $valid_gtm_country->country_id)
                                            ->where('vendor_country', '')
                                            ->first();

                if (! empty($check_vendor_city)) {
                    $check_vendor_city->vendor_country = $valid_gtm_country->vendor_country;

                    if (! $dryRun) {
                        $check_vendor_city->save();
                    }

                    $this->info( sprintf('Update Country %s, Vendor City %s to GTM City %s with Vendor Country %s.', $gtm_country, $vendor_city, $gtm_city, $valid_gtm_country->vendor_country) );
                } else {
                    $check_vendor_city = VendorGTMCity::where('vendor_city', $vendor_city)
                                                ->where('gtm_city', $gtm_city)
                                                ->where('country_id', $valid_gtm_country->country_id)
                                                ->where('vendor_country', $valid_gtm_country->vendor_country)
                                                ->first();

                    if (empty($check_vendor_city)) {
                        $newvendorcity = new VendorGTMCity();
                        $newvendorcity->vendor_city = $vendor_city;
                        $newvendorcity->gtm_city = $gtm_city;
                        $newvendorcity->country_id = $valid_gtm_country->country_id;
                        $newvendorcity->vendor_country = $valid_gtm_country->vendor_country;

                        if (! $dryRun) {
                            $newvendorcity->save();
                        }
                        $this->info( sprintf('Mapping Country %s, Vendor City %s to GTM City %s with Vendor Country %s.', $gtm_country, $vendor_city, $gtm_city, $valid_gtm_country->vendor_country) );
                    } else {
                        $this->info( sprintf('Country %s, Vendor City %s to GTM City %s with Vendor Country %s, already exist.', $gtm_country, $vendor_city, $gtm_city, $valid_gtm_country->vendor_country) );
                    }
                }

            }
            $this->info("Done");
        } catch (Exception $e) {
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }


    protected function registerCustomValidation()
    {
        // Check the existance of mall country
        Validator::extend('orbit.empty.gtm_country', function ($attribute, $value, $parameters) {
            $check_country = MallCountry::join('vendor_gtm_countries as vgc', DB::raw("vgc.gtm_country"), '=', 'mall_countries.country')
                                ->where('country', $value)
                                ->first();

            if (empty($check_country)) {
                return FALSE;
            }

            $this->valid_gtm_country = $check_country;
            return TRUE;
        });

        // Check the existance of db_ip city
        Validator::extend('orbit.empty.vendor_city', function ($attribute, $value, $parameters) {
            $check_city = DBIPCity::where('city', $value)->first();

            if (empty($check_city)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of mall city
        Validator::extend('orbit.empty.gtm_city', function ($attribute, $value, $parameters) {
            $check_city = MallCity::where('city', $value)->first();

            if (empty($check_city)) {
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
