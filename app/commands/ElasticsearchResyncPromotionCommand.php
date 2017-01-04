<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ElasticsearchResyncPromotionCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'elasticsearch:resync-promotion';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Resync promotion data from MySQL to Elasticsearch based on news id';

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
            $input = ! empty($this->argument('id')) ? $this->argument('id') : file_get_contents("php://stdin");

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $job = new FakeJob();
            $data = [
                'news_id' => $input
            ];
            try {
                $esQueue = new ESPromotionUpdateQueue();
                $response = $esQueue->fire($job, $data);

                if ($response['status'] === 'fail') {
                    throw new Exception($response['message'], 1);
                }

                $this->info(sprintf('News ID: "%s" has been successfully synced to Elasticsearch server', $data['news_id']));
            } catch (Exception $e) {
                $this->error(sprintf('Failed to sync News ID "%s", message: %s', $data['news_id'], $e->getMessage()));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
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
			array('id', null, InputOption::VALUE_OPTIONAL, 'News ID.', null)
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array();
	}

}
