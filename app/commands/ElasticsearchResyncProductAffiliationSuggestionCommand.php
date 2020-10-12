<?php

use Illuminate\Console\Command;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESProductAffiliationSuggestionUpdateQueue;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to sync product affiliation suggestion to es server.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ElasticsearchResyncProductAffiliationSuggestionCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:resync-product-affiliation-suggestion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync product affiliation suggestion to ES';

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
                'product_id' => $input
            ];

            $response = $this->syncData($job, $data);

            if ($response['status'] === 'fail') {
                throw new Exception($response['message'], 1);
            }

            $this->info(sprintf('%Product ID "%s" has been successfully synced to Elasticsearch server (Product suggestion index)', $this->stdoutPrefix, $data['product_id']));
        } catch (Exception $e) {
            $this->error(sprintf('%sFailed to sync Product ID "%s", message: %s', $this->stdoutPrefix, $data['product_id'], $e->getMessage()));
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
            array('id', null, InputOption::VALUE_OPTIONAL, 'Product ID to sync.', null),
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

        $esQueue = new ESProductAffiliationSuggestionUpdateQueue();
        $response = $esQueue->fire($job, $data);

        return $response;
    }

}