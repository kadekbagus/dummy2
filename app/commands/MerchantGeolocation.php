<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MerchantGeolocation extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'merchant:geolocation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for updating position and area of the mall.';

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
        $merchantId = $this->option('merchant_id');
        $merchantGeoFence = MerchantGeofence::where('merchant_id', '=', $merchantId)->first();

        if (empty($merchantGeoFence)) {
            $this->error('Merchant or mall is not found.');
        }

        $latitude = $this->option('latitude');
        $longitude = $this->option('longitude');
        $area =  $this->option('area');

        $prefix = DB::getTablePrefix();

        DB::statement('UPDATE '.$prefix.'merchant_geofences SET position=point('.$latitude.', '.$longitude.'), area=geomfromtext("POLYGON('.$area.'))") where merchant_id="'.$merchantId.'" limit 1;');
        $this->info("Success, Data Updated!");        
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
            array('merchant_id', null, InputOption::VALUE_REQUIRED, 'Mall or Merchant ID.'),
            array('latitude', null, InputOption::VALUE_REQUIRED, 'Latitude.'),
            array('longitude', null, InputOption::VALUE_REQUIRED, 'Longitude.'),
            array('area', null, InputOption::VALUE_REQUIRED, 'Longitude.'),
        );

    }

}
