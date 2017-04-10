<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableGrabCitiesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'grab:fill-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table grab_cities.';

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

        // grab data cities
        $grab_cities_array = [
            "20,Padang,Indonesia",
            "10,Jakarta,Indonesia",
            "36,Makassar,Indonesia",
            "28,Bandung,Indonesia",
            "18,Surabaya,Indonesia",
            "35,Medan,Indonesia",
            "34,Yogyakarta,Indonesia",
            "26,Bali,Indonesia",
            "40,Semarang,Indonesia",
            "11,Kuching,Malaysia",
            "1,Klang Valley,Malaysia",
            "19,Kota Kinabalu,Malaysia",
            "13,Penang,Malaysia",
            "2,Johor Bahru,Malaysia",
            "3,Melaka,Malaysia",
            "16,Kuala Terengganu,Malaysia",
            "8,Davao,Philippines",
            "21,Iloilo,Philippines",
            "7,Cebu,Philippines",
            "23,Bacolod,Philippines",
            "29,Cagayan de Oro,Philippines",
            "25,Baguio,Philippines",
            "4,Metro Manila,Philippines",
            "37,Pampanga,Philippines",
            "6,Singapore,Singapore",
            "38,Khon Kaen,Thailand",
            "5,Bangkok,Thailand",
            "22,Chiangrai,Thailand",
            "17,Pattaya,Thailand",
            "39,Ubon Ratchathani,Thailand",
            "27,Phuket,Thailand",
            "30,Chiang Mai,Thailand",
            "9,Ho Chi Minh,Vietnam",
            "14,Hanoi,Vietnam",
            "24,Da Nang,Vietnam",
        ];

        foreach ($grab_cities_array as $idx => $data) {
            $explode_data = explode(',', $data);

            $grab_city_external_id = $explode_data[0];
            $grab_city_name = $explode_data[1];
            $grab_country = $explode_data[2];

            $country = Country::where('name', $grab_country)->first();

            if (! empty($country)) {
                $grab_city = GrabCity::where('grab_city_external_id', $grab_city_external_id)
                                ->where('country_id', $country->country_id)
                                ->first();

                if (empty($grab_city)) {
                    $newgrab_city = new GrabCity();
                    $newgrab_city->grab_city_external_id = $grab_city_external_id;
                    $newgrab_city->grab_city_name = $grab_city_name;
                    $newgrab_city->country_id = $country->country_id;

                    if (! $dryRun) {
                        $newgrab_city->save();
                    }

                    $this->info(sprintf("Insert city %s on country %s", $newgrab_city->grab_city_name, $grab_country));
                } else {
                    $this->info(sprintf("City %s on country %s, already exist", $grab_city->grab_city_name, $grab_country));
                }
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to grab_cities.', null),
        );
    }

}
