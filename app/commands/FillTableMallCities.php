<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableMallCities extends Command
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
    protected $description = 'Fill table mall cities.';

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

        if (empty($status)) {
            $status = 'active';
        }

        if ($dryRun) {
            $this->info('[DRY RUN MODE - Not Insert on DB] ');
        }

        // get country from mall
        $malls = Mall::select('merchant_id', 'city', 'city_id')
                    ->where('object_type', 'mall')
                    ->where('status', $status)
                    ->groupby('city')
                    ->get();

        foreach ($malls as $key => $mall) {
            $mall_city = MallCity::where('city', $mall->city)
                                ->first();

            if (empty($mall_city)) {
                $new_mall_city = new MallCity();
                $new_mall_city->city = $mall->city;

                if (! $dryRun) {
                    $new_mall_city->save();
                }

                $this->info(sprintf("Insert city %s", $mall->city));
            } else {
                $this->info(sprintf("City %s, already exist", $mall->city));
            }
        }
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to mall_countries.', null),
            array('mall-status', null, InputOption::VALUE_OPTIONAL, 'Status to be injected.', NULL),
        );
    }

}
