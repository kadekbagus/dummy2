<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnPartnerMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:partner-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find partner id not have cdn file.';

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

        $partner = Partner::select('partner_id')
                            ->leftJoin('media', 'media.object_id', '=', 'partner_id')
                            ->where('object_name', 'partner')
                            ->whereNotNull('path')
                            ->whereNull('cdn_url')
                            ->where('partners.created_at', '>=', $moreThanDate)
                            ->groupBy('partner_id')
                            ->excludeDeleted()
                            ->get();

        if (count($partner) > 0) {
            foreach ($partner as $key => $val) {
                $this->info($val->partner_id);
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
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
