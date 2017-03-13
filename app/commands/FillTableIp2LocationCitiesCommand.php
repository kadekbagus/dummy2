<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableIp2LocationCitiesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ip2location:fill-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table ip2location_cities.';

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
        $dryRun = $this->option('dry-run');
        $take = 50;
        $skip = 0;

        if ($dryRun) {
            $this->info('[DRY RUN MODE - Not Insert on DB] ');
        }

        do {
            // get city from DB IP
            $ip2location = DB::connection(Config::get('orbit.vendor_ip_database.ip2location.connection_id'))
                                ->table(Config::get('orbit.vendor_ip_database.ip2location.table'))
                                ->select('country_name', 'city_name')
                                ->groupby('country_name', 'city_name')
                                ->take($take)
                                ->skip($skip)
                                ->get();

            $skip = $take + $skip;

            foreach ($ip2location as $key => $_ip2location) {
                $ip2location_city = Ip2Location::where('country', $_ip2location->country_name)
                                    ->where('city', $_ip2location->city_name)
                                    ->first();

                if (empty($ip2location_city)) {
                    $new_ip2location_city = new Ip2Location();
                    $new_ip2location_city->country_name = $_ip2location->country_name;
                    $new_ip2location_city->city_name = $_ip2location->city_name;

                    if (! $dryRun) {
                        $new_ip2location_city->save();
                    }

                    $this->info(sprintf("Insert city %s on country %s", $_ip2location->city_name, $_ip2location->country_name));
                } else {
                    $this->info(sprintf("City %s on country %s, already exist", $_ip2location->city_name, $_ip2location->country_name));
                }
            }
        } while (! empty($ip2location));
        $this->info("Done");
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to ip2location_cities.', null),
        );
    }

}
