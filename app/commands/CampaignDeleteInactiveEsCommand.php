<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Elasticsearch\ClientBuilder;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Carbon\Carbon as Carbon;
use Orbit\FakeJob;


class CampaignDeleteInactiveEsCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'campaign:campaign-delete-inactive-es';

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
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        //Es config
        $host = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                ->setHosts($host['hosts']) // Set the hosts
                ->build();

        //Get now time, time must be 2017-01-09T15:30:00Z
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');

        // @todo: Need to think about the timezone
        $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
        $dateTime = explode(' ', $dateTime);
        $nowDateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

        //Check only campaign suggestion index
        $campaignTypes = array('news', 'promotion', 'coupon');

        $fakeJob = new FakeJob();

        $totalDeleted = 0;
        foreach ($campaignTypes as $campaignType) {

            // Get all coupon inactive
            $jsonQuery['filter']['range']['end_date']['lt'] = $nowDateTimeEs;
            $esParam = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.' . $campaignType . '_suggestions.index'),
                'body' => json_encode($jsonQuery)
            ];

            $response = $client->search($esParam);

            if ($response['hits']['total'] === 0) {
                continue;
            }

            foreach ($response['hits']['hits'] as $campaign) {

                    // Delete id there is any news inactive
                    $params = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.' . $campaignType . '_suggestions.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.' . $campaignType . '_suggestions.type'),
                        'id' => $campaign['_id']
                    ];

                    $response = $client->delete($params);

                    // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                    ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

                    $this->info("Delete es document index = " . $campaignType . "_suggestions, type = " . $campaignType . ", campaign_id = " . $campaign['_id'] . " successful at time " . date('l j \of F Y h:i:s A'));
                    $totalDeleted++;
            }
        }

        $this->info(sprintf('Total deleted: %s', $totalDeleted));
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
        return array();
    }

}
