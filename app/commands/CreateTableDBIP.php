<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateTableDBIP extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'dbip:create-table';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create table to store db ip';

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
            $confirm = $this->option('yes');
            $table = empty($this->option('table_name')) ? Config::get('orbit.dbip.table') : $this->option('table_name');
            $connection = empty($this->option('connection_id')) ? Config::get('orbit.dbip.connection_id') : $this->option('connection_id');
            $prefix = DB::connection($connection)->getTablePrefix();
            $type = strtolower($this->option('type'));

            if (! $confirm) {
                $question = "Are you sure want to create table with {$type} type? [y|n]";
                if (! $this->confirm($question, false)) {
                    $confirm = false;
                    return;
                }
            }

            if ($type === 'country') {
            	DB::connection($connection)->unprepared("
            		CREATE TABLE `{$table}` (
					  `addr_type` enum('ipv4','ipv6') NOT NULL,
					  `ip_start` varbinary(16) NOT NULL,
					  `ip_end` varbinary(16) NOT NULL,
					  `country` char(2) NOT NULL,
					  PRIMARY KEY (`ip_start`)
					);
            	");
            } elseif ($type === 'city') {
            	DB::connection($connection)->unprepared("
            		CREATE TABLE `{$table}` (
					  `addr_type` enum('ipv4','ipv6') NOT NULL,
					  `ip_start` varbinary(16) NOT NULL,
					  `ip_end` varbinary(16) NOT NULL,
					  `country` char(2) NOT NULL,
					  `stateprov` varchar(80) NOT NULL,
					  `city` varchar(80) NOT NULL,
					  PRIMARY KEY (`ip_start`)
					);
            	");
            } elseif ($type === 'location') {
            	DB::connection($connection)->unprepared("
            		CREATE TABLE `{$table}` (
					  `addr_type` enum('ipv4','ipv6') NOT NULL,
					  `ip_start` varbinary(16) NOT NULL,
					  `ip_end` varbinary(16) NOT NULL,
					  `country` char(2) NOT NULL,
					  `stateprov` varchar(80) NOT NULL,
					  `city` varchar(80) NOT NULL,
					  `latitude` float NOT NULL,
					  `longitude` float NOT NULL,
					  `timezone_offset` float NOT NULL,
					  `timezone_name` varchar(64) NOT NULL,
					  PRIMARY KEY (`ip_start`)
					);
            	");
            } elseif ($type === 'isp') {
            	DB::connection($connection)->unprepared("
            		CREATE TABLE `{$table}` (
					  `addr_type` enum('ipv4','ipv6') NOT NULL,
					  `ip_start` varbinary(16) NOT NULL,
					  `ip_end` varbinary(16) NOT NULL,
					  `country` char(2) NOT NULL,
					  `isp_name` varchar(128) NOT NULL,
					  `connection_type` enum('dialup','isdn','cable','dsl','fttx','wireless') DEFAULT NULL,
					  `organization_name` varchar(128) NOT NULL,
					  PRIMARY KEY (`ip_start`)
					);
            	");
            } elseif ($type === 'full') {
            	DB::connection($connection)->unprepared("
            		CREATE TABLE `{$table}` (
					  `addr_type` enum('ipv4','ipv6') NOT NULL,
					  `ip_start` varbinary(16) NOT NULL,
					  `ip_end` varbinary(16) NOT NULL,
					  `country` char(2) NOT NULL,
					  `stateprov` varchar(80) NOT NULL,
					  `city` varchar(80) NOT NULL,
					  `latitude` float NOT NULL,
					  `longitude` float NOT NULL,
					  `timezone_offset` float NOT NULL,
					  `timezone_name` varchar(64) NOT NULL,
					  `isp_name` varchar(128) NOT NULL,
					  `connection_type` enum('dialup','isdn','cable','dsl','fttx','wireless') DEFAULT NULL,
					  `organization_name` varchar(128) NOT NULL,
					  PRIMARY KEY (`ip_start`)
					);
            	");
            } else {
            	throw new Exception('please choose type between country, city, location, isp, full');
            }
            $this->info('Create new table success!');

		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
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
            array('type', null, InputOption::VALUE_REQUIRED, 'DB IP type <country|city|location|isp|full>'),
            array('table_name', null, InputOption::VALUE_REQUIRED, 'Table name in your database'),
            array('connection_id', null, InputOption::VALUE_REQUIRED, 'Connection id to your mysql db'),
            array('yes', null, InputOption::VALUE_NONE, 'Confirmation to import dbip'),
        );
	}

}
