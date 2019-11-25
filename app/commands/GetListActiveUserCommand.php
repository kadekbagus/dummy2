<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetListActiveUserCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'user:list-active';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'List of all active users.';

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
        $now = date('Y-m-d H:i:s', strtotime($this->option('end-date')));    // no TZ calculation
        $row = 0;
        $fields = $this->option('fields');
        $separator = $this->option('separator');
        $raw = $this->option('raw-query');
        $prefix = DB::getTablePrefix();

        do {
            $users = DB::select("SELECT {$fields}
                FROM {$prefix}users
                WHERE {$prefix}users.status='active'
                {$raw}
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($users as $user) {
                $values = get_object_vars($user);
                printf("%s\n", implode($separator, $values));
            }
        } while (! empty($users));
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
            array('fields', null, InputOption::VALUE_OPTIONAL, 'List of fields separated by comma.', '*'),
            array('separator', null, InputOption::VALUE_OPTIONAL, 'Separator used when printing value.', ','),
            array('raw-query', null, InputOption::VALUE_OPTIONAL, 'Raw query to be injected.', NULL),
            array('end-date', null, InputOption::VALUE_OPTIONAL, 'Specify the end_date in PHP strtotime() format.', 'now'),
        );
	}

}
