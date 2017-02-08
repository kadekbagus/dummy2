<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableDBIPCitiesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dbip:fill-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table db_ip_cities.';

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

        if ($dryRun) {
            $this->info('[DRY RUN MODE - Not Insert on DB] ');
        }

        // get city from DB IP
        $db_ip = DB::connection(Config::get('orbit.dbip.connection_id'))
                            ->table(Config::get('orbit.dbip.table'))
                            ->select('country', 'city')
                            ->groupby('city', 'country')
                            ->get();

        foreach ($db_ip as $key => $_db_ip) {
            $db_ip_city = DBIPCity::where('country', $_db_ip->country)
                                ->where('city', $_db_ip->city)
                                ->first();

            if (empty($db_ip_city)) {
                $new_db_ip_city = new DBIPCity();
                $new_db_ip_city->country = $_db_ip->country;
                $new_db_ip_city->city = $_db_ip->city;

                if (! $dryRun) {
                    $new_db_ip_city->save();
                }

                $this->info(sprintf("Insert city %s on country %s", $_db_ip->city, $_db_ip->country));
            } else {
                $this->info(sprintf("City %s on country %s, already exist", $_db_ip->city, $_db_ip->country));
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to db_ip_cities.', null),
        );
    }

}
