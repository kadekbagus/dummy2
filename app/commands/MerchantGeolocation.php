<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MerchantGeolocation extends Command 
{

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
        $fence = MerchantGeofence::where('merchant_id', '=', $merchantId)->find(1);

        if (empty($fence)) {

            $this->error('Merchant or mall is not found.');

        } else {

            $latitude = (double)$this->option('latitude');
            $longitude = (double)$this->option('longitude');
            $area = preg_replace('/[^0-9\s,\-\.]/', '',  $this->option('area'));

            $prefix = DB::getTablePrefix();

            $fence->position = DB::raw("POINT($latitude, $longitude)");
            $fence->area = DB::raw("geomfromtext(\"POLYGON(({$area}))\")");

            if (! $fence->save()) {
                $this->error('Update Failed!');
            }

            $this->info("Success, Data Updated!"); 

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
            array('merchant_id', null, InputOption::VALUE_REQUIRED, 'Mall or Merchant ID.'),
            array('latitude', null, InputOption::VALUE_REQUIRED, 'Latitude.'),
            array('longitude', null, InputOption::VALUE_REQUIRED, 'Longitude.'),
            array('area', null, InputOption::VALUE_REQUIRED, 'Longitude.'),
        );

    }

}
