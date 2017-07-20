<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnStoreMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:store-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find store id not have cdn file.';

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
        $moreThanDate = $this->option('more-than');

        if (DateTime::createFromFormat('Y-m-d H:i:s', $moreThanDate) == false) {
           throw new Exception('Format date is invalid, format date must be Y-m-d H:i:s ie (2017-12-20 16:55:28)');
        }

        $store = Tenant::select('merchant_id')
                            ->leftJoin('media', 'media.object_id', '=', 'merchant_id')
                            ->where('object_name', 'retailer')
                            ->whereNotNull('path')
                            ->whereNull('cdn_url')
                            ->where('merchants.created_at', '>=', $moreThanDate)
                            ->groupBy('merchant_id')
                            ->excludeDeleted()
                            ->get();

        if (count($store) > 0) {
            foreach ($store as $key => $val) {
                $this->info($val->merchant_id  . ',store');
            }
        } else {
                $this->info('Data not found.');
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
            array('more-than', NULL, InputOption::VALUE_REQUIRED, 'More than equal date, format : Y-m-d H:i:s ie (2017-12-20 16:55:28)')
        );
    }

}
