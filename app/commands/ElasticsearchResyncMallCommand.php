<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\ElasticSearch\ESMallUpdateQueue;

class ElasticsearchResyncMallCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'elasticsearch:resync-mall';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Resync mall data from MySQL to Elasticsearch based on mall id';

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
		$job = new FakeJob();
        $data = [
            'mall_id' => $this->option('mall-id')
        ];
        try {
            $esQueue = new ESMallUpdateQueue();
            $response = $esQueue->fire($job, $data);

            if ($response['status'] === 'fail') {
                throw new Exception($response['message'], 1);
            }

            $this->info(sprintf('Mall id "%s" has been successfully synced to Elasticsearch server', $data['mall_id']));
        } catch (Exception $e) {
            $this->error(sprintf('Failed to sync mall id "%s", message: %s', $data['mall_id'], $e->getMessage()));
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
            array('mall-id', null, InputOption::VALUE_REQUIRED, 'The mall id from MySQL source.', null),
        );
	}

}
