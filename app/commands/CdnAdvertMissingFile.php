<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnAdvertMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:advert-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find advert id not have cdn file';

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

        $advert = Advert::select('advert_id')
                            ->leftJoin('media', 'media.object_id', '=', 'advert_id')
                            ->where('object_name', 'advert')
                            ->whereNotNull('path')
                            ->whereNull('cdn_url')
                            ->where('adverts.created_at', '>=', $moreThanDate)
                            ->groupBy('advert_id')
                            ->excludeDeleted()
                            ->get();

        if (count($advert) > 0) {
            foreach ($advert as $key => $val) {
                $this->info($val->advert_id . ',advert');
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
