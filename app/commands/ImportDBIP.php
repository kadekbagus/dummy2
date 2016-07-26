<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ImportDBIP extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dbip:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import DB IP to Mysql Database';

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
            $table = empty($this->option('table_name')) ? Config::get('orbit.dbip.table') : $this->option('table_name');
            $connection = empty($this->option('connection_id')) ? Config::get('orbit.dbip.connection_id') : $this->option('connection_id');

            $type = strtolower($this->option('type'));
            
            if (! $confirm) {
                $question = "Are you sure want to import DB IP? [y|n]";
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
                    $fields = array("ip_start", "ip_end", "country");
                    break;
                case "city":
                    $fields = array("ip_start", "ip_end", "country", "stateprov", "city");
                    break;
                case "location":
                    $fields = array("ip_start", "ip_end", "country", "stateprov", "city", "latitude", "longitude", "timezone_offset", "timezone_name");
                    break;
                case "isp":
                    $fields = array("ip_start", "ip_end", "country", "isp_name", "connection_type", "organization_name");
                    break;
                case "full":
                    $fields = array("ip_start", "ip_end", "country", "stateprov", "city", "latitude", "longitude", "timezone_offset", "timezone_name", "isp_name", "connection_type", "organization_name");
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

                foreach ($r as $k => $v) {
                    switch ($fields[$k]) {
                        case "connection_type":
                        $r[$k] = $v ?: null;
                        break;
                        case "latitude":
                        case "longitude":
                        case "timezone_offset":
                        $r[$k] = (float)$v;
                        break;
                        case "isp_name":
                        case "organization_name":
                        $r[$k] = substr($v, 0, 128);
                        break;
                        case "city":
                        case "stateprov":
                        $r[$k] = substr($v, 0, 80);
                        break;
                        case "timezone_name":
                        $r[$k] = substr($v, 0, 64);
                        break;
                        default:
                        $r[$k] = stripslashes($v);
                    }
                }
                
                $r[] = $this->addr_type($r[0]);
                $r[0] = inet_pton($r[0]);
                $r[1] = inet_pton($r[1]);

                $num = 0;
                $rows = array();
                $max = count($r);

                foreach ($r as $val) {
                    $index = 'addr_type';
                    if ($num != $max-1) {
                        $index = $fields[$num];
                    }

                    $rows[$index] = $val;
                    $num += 1;
                }

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

            $this->info('Import DB IP data success!');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    static private function addr_type($addr) {
        if (ip2long($addr) !== false) {
            return "ipv4";
        } else if (preg_match('/^[0-9a-fA-F:]+$/', $addr) && @inet_pton($addr)) {
            return "ipv6";
        } else {
            throw new Exception("unknown address type for {$addr}");
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
            array('path', null, InputOption::VALUE_REQUIRED, 'DB IP file path (csv file)'),
            array('type', null, InputOption::VALUE_REQUIRED, 'DB IP type <country|city|location|isp|full>'),
            array('table_name', null, InputOption::VALUE_REQUIRED, 'Table name in your database, default table_name is [prefix]_[month]_[year], ex orb_july_2016'),
            array('connection_id', null, InputOption::VALUE_REQUIRED, 'Connection id to your mysql db'),
            array('yes', null, InputOption::VALUE_NONE, 'Confirmation to import dbip'),
        );
    }

}
