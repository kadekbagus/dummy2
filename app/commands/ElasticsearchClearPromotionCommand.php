<?php
/**
 * Command for clearing all promotions data from Elasticsearch index.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ElasticsearchClearPromotionCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:clear-promotion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all promotions data in Elasticsearch';

    /**
     * Total data has been deleted.
     *
     * @var int
     */
    protected $totalDeleted = 0;

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
        $confirm = $this->confirm('Are you sure want to clear all promotions? [yes|no]', FALSE);
        if (! $confirm) {
            $this->info('Aborted');
            return;
        }

        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        $client = ESBuilder::create()->build();
        $params = [
            'search_type' => 'scan',    // use search_type=scan
            'scroll' => '15s',          // how long between scroll requests. should be small!
            'size' => 25,               // how many results *per shard* you want back
            'index' => $esPrefix . $esConfig['indices']['promotions']['index'],
            'type' => $esConfig['indices']['promotions']['type'],
            'body' => [
                'query' => [
                    'match_all' => []
                ]
            ]
        ];

        try {
            $docs = $client->search($params); // Search using Scroll method
            $scrollId = $docs['_scroll_id']; // The response will contain no results, just a _scroll_id

            // the document id will be provided inside loop
            $deleteParams = [
                'index' => $esPrefix . $esConfig['indices']['promotions']['index'],
                'type' => $esConfig['indices']['promotions']['type'],
            ];

            while (TRUE) {
                // Execute a Scroll request
                $response = $client->scroll([
                        "scroll_id" => $scrollId,  //...using our previously obtained _scroll_id
                        "scroll" => "15s"           // and the same timeout window
                    ]
                );

                if (count($response['hits']['hits']) > 0) {
                    $this->deleteDocuments($client, $deleteParams, $response['hits']['hits']);

                    // refresh the value of scroll id
                    $scrollId = $response['_scroll_id'];
                } else {
                    // No result, scroll cursor is empty.
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->info(sprintf('Total deleted promotion(s): %s', $this->totalDeleted));
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent to Elasticsearch.', null),
        );
    }

    /**
     * Delete each ES document.
     *
     * @param array $params
     * @param array $documents
     * @return void
     */
    protected function deleteDocuments($client, $params, $documents)
    {
        foreach ($documents as $doc) {
            $params['id'] = $doc['_id'];
            $this->delete($client, $params);

            $message = sprintf('%sPromotion ID: %s has been deleted.', $this->stdoutPrefix, $doc['_id']);
            $this->info($message);
        }
    }

    /**
     * Delete the document individually
     *
     * @param boolean $dryRun
     */
    protected function delete($client, $params)
    {
        if ($this->option('dry-run')) {
            $this->stdoutPrefix = '[DRY RUN] ';

            $this->totalDeleted++;
            return TRUE;
        }

        $client->delete($params);
        $this->totalDeleted++;

        return TRUE;
    }
}
