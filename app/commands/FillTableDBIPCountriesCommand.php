<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableDBIPCountriesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dbip:fill-countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table db_ip_countries.';

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
            // get country from DB IP
            $db_ip = DB::connection(Config::get('orbit.dbip.connection_id'))
                                ->table(Config::get('orbit.dbip.table'))
                                ->select('country')
                                ->groupby('country')
                                ->take($take)
                                ->skip($skip)
                                ->get();

            $skip = $take + $skip;

            foreach ($db_ip as $key => $_db_ip) {
                $db_ip_country = DBIPCountry::where('country', $_db_ip->country)
                                    ->first();

                if (empty($db_ip_country)) {
                    $new_db_ip_country = new DBIPCountry();
                    $new_db_ip_country->country = $_db_ip->country;

                    if (! $dryRun) {
                        $new_db_ip_country->save();
                    }

                    $this->info(sprintf("Insert country %s", $_db_ip->country));
                } else {
                    $this->info(sprintf("Country %s, already exist", $_db_ip->country));
                }
            }
        } while (! empty($db_ip));
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to db_ip_countries.', null),
        );
    }

}
