<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableMallCountriesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'mall:fill-countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table mall_countries.';

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
            $this->info(sprintf('Deleting all countries...'));
            $mall_countries = DB::table('mall_countries')->delete();
        }

        do {
            // get country from mall
            $malls = Mall::select('merchant_id', DB::raw('ctr.name as country'), DB::raw('ctr.country_id'))
                        ->join('countries as ctr', DB::raw('ctr.country_id'), '=', 'merchants.country_id')
                        ->where('object_type', 'mall')
                        ->where('status', $status)
                        ->groupby('country')
                        ->take($take)
                        ->skip($skip)
                        ->get();

            $skip = $take + $skip;

            foreach ($malls as $key => $mall) {
                $mall_country = MallCountry::where('country_id', $mall->country_id)
                                    ->first();

                if (empty($mall_country)) {
                    $new_mall_country = new MallCountry();
                    $new_mall_country->country_id = $mall->country_id;
                    $new_mall_country->country = $mall->country;

                    if (! $dryRun) {
                        $new_mall_country->save();
                    }

                    $this->info(sprintf("Insert country %s with country_id %s", $mall->country, $mall->country_id));
                } else {
                    $this->info(sprintf("Country %s with country_id %s, already exist", $mall->country, $mall->country_id));
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to mall_countries.', null),
            array('mall-status', null, InputOption::VALUE_OPTIONAL, 'Status to be injected.', NULL),
        );
    }

}
