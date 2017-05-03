<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateVendorGTMCitiesTable extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'update:vendor-gtm-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update vendor_gtm_cities table based on vendor type';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {
            $vendor = $this->option('vendor');
            if (empty($vendor)) {
                throw new Exception("Vendor is required.", 1);
            }

            $prefix = DB::getTablePrefix();
            $missingCities = '';

            switch ($vendor) {
                case 'ip2location':
                    $mallCities = MallCity::select('ip2location_cities.city as vendor_city', 'countries.code as country_code', 'mall_cities.city as gtm_city', 'mall_cities.country_id')
                                          ->join('ip2location_cities', 'ip2location_cities.city', '=', 'mall_cities.city')
                                          ->join('countries', 'mall_cities.country_id', '=', 'countries.country_id')
                                          ->groupBy('mall_cities.city');

                    $missingCities = DB::table(DB::raw("({$mallCities->toSql()}) as sub"))
                                       ->mergeBindings($mallCities->getQuery())
                                       ->whereNotExists(function($query) use ($prefix) {
                                            $query->select('vendor_gtm_cities.vendor_gtm_city_id')
                                                  ->from('vendor_gtm_cities')
                                                  ->whereRaw("{$prefix}vendor_gtm_cities.vendor_type = 'ip2location'")
                                                  ->whereRaw("{$prefix}vendor_gtm_cities.vendor_city = sub.vendor_city")
                                                  ->whereRaw("{$prefix}vendor_gtm_cities.gtm_city = sub.gtm_city");
                                        })
                                       ->get();

                    foreach ($missingCities as $city) {
                        $mapping = new VendorGTMCity();
                        $mapping->vendor_type = 'ip2location';
                        $mapping->vendor_city = $city->vendor_city;
                        $mapping->gtm_city = $city->gtm_city;
                        $mapping->country_id = $city->country_id;
                        $mapping->vendor_country = $city->country_code;
                        $mapping->save();

                        $message = sprintf('*** Insert vendor_gtm_city, ip2location city `%s`, gtm city `%s` ***',
                                    $city->vendor_city,
                                    $city->gtm_city);

                        \Log::info($message);
                        $this->info($message);
                    }

                    break;
            }

            $message = 'Update vendor_gtm_cities table success.';
            if (!is_object($missingCities)) {
                $message = 'Data already up-to-date.';
            }

            $this->info($message);
        } catch (Exception $e) {
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
            array('vendor', null, InputOption::VALUE_REQUIRED, 'vendor ex: ip2location, dbip, etc.')
        );
    }

}