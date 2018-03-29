<?php
/**
 * Delete inactive ES documents used for auto complete.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 * @author Rio Astamal <rio@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;

class CampaignDeleteInactiveEsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'campaign:campaign-delete-inactive-es';

    /**
     * ES client object
     *
     * @var Elasticsearch\ClientBuilder
     */
    protected $client = NULL;

    /**
     * ES Config
     *
     * @var array
     */
    protected $esConfig = [];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete not active campaign in suggest index elasticsearch';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->esConfig = Config::get('orbit.elasticsearch');
        $this->client = ClientBuilder::create() // Instantiate a new ClientBuilder
                ->setHosts($this->esConfig['hosts']) // Set the hosts
                ->build();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $esPrefix = $this->esConfig['indices_prefix'];

        $dt = new DateTime('now', new DateTimezone('Asia/Jakarta'));
        $nowDateTimeEs = $dt->format('Y-m-d\TH:i:s\Z');

        $campaignTypes = array(
                                'coupon_suggestions',
                                'news_suggestions',
                                'promotion_suggestions',
                                'coupon_mall_level_suggestions',
                                'news_mall_level_suggestions',
                                'promotion_mall_level_suggestions',
                            );
        foreach ($campaignTypes as $type) {
            $index = $esPrefix . $this->esConfig['indices'][$type]['index'];
            $this->deleteInactionSuggestionDocuments(['datetime' => $nowDateTimeEs, 'index' => $index, 'index_type' => 'basic']);
        }
    }

    /**
     * Delete inactive promotion suggestion
     *
     * @param array $params
     * @return void
     */
    protected function deleteInactionSuggestionDocuments($params)
    {
        $take = 100;
        $skip = 0;

        $jsonQuery = [];
        // Get all suggestions for promotion which ended
        $jsonQuery['filter']['range']['end_date']['lt'] = $params['datetime'];
        $jsonQuery['_source'] = ['name'];
        $dryRun = $this->option('dry-run', FALSE);

        $counter = 1;
        while (true) {
            $jsonQuery['from'] = $skip;
            $jsonQuery['size'] = $take;

            $esParam = [
                'index' => $params['index'],
                'body' => json_encode($jsonQuery)
            ];

            $response = $this->client->search($esParam);

            // As soon as we do not find anymore document
            // exit the loop
            if (empty($response['hits']['hits'])) {
                break;
            }

            // No need to update the skip since we are deleting which
            // cause the number of document changes in every loop
            if ($dryRun) {
                $skip = $take + $skip;
            }

            foreach ($response['hits']['hits'] as $suggestion) {
                $response = $this->deleteInactionSuggestionDocument([
                    'index' => $params['index'],
                    'type' => $params['index_type'],
                    'doc_id' => $suggestion['_id'],
                    'index_type' => $params['index_type'],
                    'dry_run' => $dryRun
                ]);

                $status = $response['code'] === 0 ? 'OK' : 'FAILED';
                printf("%s%s - %d; DocumentId: %s; Status: %s; Index: %s; Name: %s; Message: %s\n",
                    $dryRun ? '[DRY RUN] ' : '',
                    date('Y-m-d H:i:s'),
                    $counter++,
                    $suggestion['_id'],
                    $status,
                    $params['index'] . '_suggestions',
                    $suggestion['_source']['name'],
                    preg_replace('/\s+/', ' ', $response['message']));
            }

            // Sleep for half a second
            usleep(500000);
        }
    }

    protected function deleteInactionSuggestionDocument($params)
    {
        // Delete id there is any news inactive
        $deleteParams = [
            'index' => $params['index'],
            'type' => $params['index_type'],
            'id' => $params['doc_id']
        ];

        try {
            if (! $params['dry_run']) {
                $response = $this->client->delete($deleteParams);

                // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);
            }

            $code = 0;
            $message = 'Sucessfully deleted';
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
        }

        return ['code' => $code, 'message' => $message];
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry run mode, do not delete the data.'),
        );
    }

}
