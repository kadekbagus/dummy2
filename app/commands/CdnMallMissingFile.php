<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnMallMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:mall-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find mall id not have cdn file';

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
        $take = 50;
        $skip = 0;
        $moreThanDate = $this->option('more-than');

        if (DateTime::createFromFormat('Y-m-d H:i:s', $moreThanDate) == false) {
           throw new Exception('Format date is invalid, format date must be Y-m-d H:i:s ie (2017-12-20 16:55:28)');
        }

        do {
            $mall = Mall::select('merchant_id')
                                ->leftJoin('media', 'media.object_id', '=', 'merchant_id')
                                ->where('object_name', 'mall')
                                ->whereNotNull('path')
                                ->where(function ($q) {
                                     $q->whereNull('cdn_url');
                                     $q->orWhere('cdn_url', '=', '');
                                  })
                                ->where('merchants.created_at', '>=', $moreThanDate)
                                ->where('merchants.status', 'active')
                                ->groupBy('merchant_id')
                                ->skip($skip)
                                ->take($take)
                                ->get();

            $skip = $take + $skip;

            foreach ($mall as $key => $val) {
                printf("%s,%s\n", $val->merchant_id, 'mall');
            }

        } while (count($mall) > 0);

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
