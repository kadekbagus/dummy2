<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ImportIP2Location extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'import:ip2location';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import IP2Location (.CSV) to Mysql Database';

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
        try {
            DB::beginTransaction();
            $filename = $this->option('path');
            $confirm = $this->option('yes');
            $table = empty($this->option('table_name')) ? Config::get('orbit.ip_database.ip2location.table') : $this->option('table_name');
            $connection = empty($this->option('connection_id')) ? Config::get('orbit.ip_database.ip2location.connection_id') : $this->option('connection_id');

            $type = strtolower($this->option('type'));

            if (! $confirm) {
                $question = "Are you sure want to import IP2Location? [y|n]";
                if (! $this->confirm($question, false)) {
                    $confirm = false;
                    return;
                }
            }

            if (empty($table)) {
                $prefix = DB::connection($connection)->getTablePrefix();
                $table = strtolower(date("F_Y"));
            }

            if (!file_exists($filename)) {
                throw new Exception("file {$filename} does not exists");
            }

            switch ($type) {
                case "country":
                    $fields = array("ip_from", "ip_to", "country_code", "country_name");
                    break;
                case "region-city":
                    $fields = array("ip_from", "ip_to", "country_code", "country_name", "region_name", "city_name");
                    break;
                case "latitude-longitude":
                    $fields = array("ip_from", "ip_to", "country_code", "country_name", "region_name", "city_name", "latitude", "longitude");
                    break;
                case "zipcode":
                    $fields = array("ip_from", "ip_to", "country_code", "country_name", "region_name", "city_name", "latitude", "longitude", "zip_code");
                    break;
                case "timezone":
                    $fields = array("ip_from", "ip_to", "country_code", "country_name", "region_name", "city_name", "latitude", "longitude", "zip_code", "time_zone");
                    break;
                default:
                    throw new Exception("invalid database type", 1);
            }

            $countTable = DB::connection($connection)->table($table)->count();
            $prefix = DB::connection($connection)->getTablePrefix();

            if ($countTable > 0) {
                throw new Exception("table {$prefix}{$table} is not empty");
            }

            $f = @fopen($filename, "r");

            if (!is_resource($f)) {
                throw new Exception("cannot open {$filename} for reading");
            }

            $nrecs = 1;
            $allrows = array();
            $numInsert = 10;
            $numberInsert = 0;

            while ($r = fgetcsv($f)) {
                $num = 0;
                $rows = array();
                $max = count($r);

                foreach ($r as $val) {
                    $index = $fields[$num];
                    if ($index != 'ip_from' || $index != 'ip_to') {
                    	$val = empty($val) ? null : $val;
                    }

                    $rows[$index] = $val;
                    $num += 1;
                }

                // print_r($rows); die();

                $allrows[] = $rows;
                if ($nrecs % $numInsert === 0) {
                    DB::connection($connection)->table($table)->insert($allrows);
                    $allrows = array();
                    $numberInsert = $nrecs;
                }

                echo "\r Please wait, inserting {$nrecs} records ...";
                $nrecs++;
            }

            if ($numberInsert < $nrecs-1) {
                DB::connection($connection)->table($table)->insert($allrows);
            }

            fclose($f);
            $this->finalize_import($connection);

            $this->info('Import IP2Location data success!');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function finalize_import($connection) {
        DB::connection($connection)->commit();
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
            array('path', null, InputOption::VALUE_REQUIRED, 'IP2Location file path (csv file)'),
            array('type', null, InputOption::VALUE_REQUIRED, 'IP2Location type <country|city|location|isp|full>'),
            array('table_name', null, InputOption::VALUE_REQUIRED, 'Table name in your database, default table_name is [prefix]_ip2location_[month]_[year], ex orb_july_2016'),
            array('connection_id', null, InputOption::VALUE_REQUIRED, 'Connection id to your mysql db'),
            array('yes', null, InputOption::VALUE_NONE, 'Confirmation to import dbip'),
        );
    }

}