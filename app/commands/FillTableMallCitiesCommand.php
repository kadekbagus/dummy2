<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableMallCitiesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'mall:fill-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table mall_cities.';

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
        $status = $this->option('mall-status');
        $dryRun = $this->option('dry-run');
        $take = 50;
        $skip = 0;

        if (empty($status)) {
            $status = 'active';
        }

        if ($dryRun) {
            $this->info('[DRY RUN MODE - Not Insert on DB] ');
        }

        if (! $dryRun) {
            $this->info(sprintf('Deleting all cities...'));
            $mall_city = DB::table('mall_cities')->delete();
        }

        do {
            // get country from mall
            $malls = Mall::select('merchant_id', 'country_id', 'city', 'city_id')
                        ->where('object_type', 'mall')
                        ->where('status', $status)
                        ->groupby('city', 'country_id')
                        ->take($take)
                        ->skip($skip)
                        ->get();

            $skip = $take + $skip;

            foreach ($malls as $key => $mall) {
                // first check to handle a first data without country_id
                $mall_city = MallCity::where('city', $mall->city)
                                    ->where('country_id', '')
                                    ->first();

                if (! empty($mall_city)) {
                    $mall_city->country_id = $mall->country_id;

                    if (! $dryRun) {
                        $mall_city->save();
                    }

                    $this->info(sprintf("Update city %s with country_id %s", $mall->city, $mall->country_id));
                } else {
                    // second check to handle data with country_id
                    $mall_city = MallCity::where('city', $mall->city)
                                        ->where('country_id', $mall->country_id)
                                        ->first();

                    if (empty($mall_city)) {
                        $new_mall_city = new MallCity();
                        $new_mall_city->city = $mall->city;
                        $new_mall_city->country_id = $mall->country_id;

                        if (! $dryRun) {
                            $new_mall_city->save();
                        }

                        $this->info(sprintf("Insert city %s with country_id %s", $mall->city, $mall->country_id));
                    } else {
                        $this->info(sprintf("City %s with country_id %s, already exist", $mall->city, $mall->country_id));
                    }
                }
            }
        } while (count($malls) > 0);
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to mall_cities.', null),
            array('mall-status', null, InputOption::VALUE_OPTIONAL, 'Status to be injected.', NULL),
        );
    }

}
