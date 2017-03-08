<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\ElasticSearch\ESMallSuggestionUpdateQueue;

class ElasticsearchResyncMallSuggestionCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:resync-mall-suggestion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync mall data from MySQL to Elasticsearch based on mall id';

    /**
     * Prefix for message list.
     *
     * @var string
     */
    protected $stdoutPrefix = '';

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
            $input = ! empty($this->option('id')) ? $this->option('id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $job = new FakeJob();
            $data = [
                'mall_id' => $input
            ];

            $response = $this->syncData($job, $data);

            if ($response['status'] === 'fail') {
                throw new Exception($response['message'], 1);
            }

            $this->info(sprintf('%sMall id "%s" has been successfully synced to Elasticsearch server (Mall suggestion index)', $this->stdoutPrefix, $data['mall_id']));
        } catch (Exception $e) {
            $this->error(sprintf('%sFailed to sync mall id "%s", message: %s', $this->stdoutPrefix, $data['mall_id'], $e->getMessage()));
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
            array('id', null, InputOption::VALUE_OPTIONAL, 'Mall id to sync.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent to Elasticsearch.', null),
        );
    }

    /**
     * Fake response
     *
     * @param boolean $dryRun
     */
    protected function syncData($job, $data)
    {
        if ($this->option('dry-run')) {
            $this->stdoutPrefix = '[DRY RUN] ';

            return [
                'status' => 'ok',
                'message' => 'Dry run mode'
            ];
        }

        $esQueue = new ESMallSuggestionUpdateQueue();
        $response = $esQueue->fire($job, $data);

        return $response;
    }

}
