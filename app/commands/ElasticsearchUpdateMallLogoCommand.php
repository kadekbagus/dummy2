<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Elasticsearch\ClientBuilder as ESBuilder;

class ElasticsearchUpdateMallLogoCommand extends Command
{

    protected $poster = NULL;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:update-mall-logo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update logo field in mall index';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct($poster = 'default')
    {
        parent::__construct();
        if ($poster === 'default') {
            $this->poster = ESBuilder::create()
                                     ->setHosts(Config::get('orbit.elasticsearch.hosts'))
                                     ->build();
        } else {
            $this->poster = $poster;
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $prefix = DB::getTablePrefix();
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
        $malls = Mall::excludeDeleted()->get();

        foreach ($malls as $mall) {
            try {
                // check exist elasticsearch index
                $params_search = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                    'body' => [
                        'query' => [
                            'match' => [
                                '_id' => $mall->merchant_id
                            ]
                        ]
                    ]
                ];

                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                    'id' => $mall->merchant_id,
                    'body' => []
                ];

                $response_search = $this->poster->search($params_search);
                if ($response_search['hits']['total'] > 0) {
                    if (! isset($mall->mediaLogoOrig[0])) {
                        $params['body']['doc']['logo_url'] = '';
                    } else {
                        $params['body']['doc']['logo_url'] = $mall->mediaLogoOrig[0]->path;
                    }

                    $response = $this->poster->update($params);

                    $this->info('Updating logo of mall ' . $mall->name . '... OK');
                } else {
                    throw new Exception(sprintf("Mall %s not found in Elasticsearch.\n", $mall->name));
                }
            } catch (Exception $e) {
                $this->error('Updating logo of mall ' . $mall->name . '... FAILED.' . "\n  Message: {$e->getMessage()}");
            }
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
        );
    }
}