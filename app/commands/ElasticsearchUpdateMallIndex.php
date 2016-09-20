<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Elasticsearch\ClientBuilder as ESBuilder;

class ElasticsearchUpdateMallIndex extends Command {

    protected $poster = NULL;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elasticsearch:update-mall';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update document in mall index elasticsearch';

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
        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
        $prefix = DB::getTablePrefix();
        $malls = Mall::excludeDeleted()->get();

        foreach ($malls as $mall) {
            try {
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

                $response_search = $this->poster->search($params_search);
                if ($response_search['hits']['total'] > 0) {
                    $geofence = MerchantGeofence::getDefaultValueForAreaAndPosition($mall->merchant_id);
                    $params = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                        'id' => $mall->merchant_id,
                        'body' => [
                            'doc' => [
                                'name'            => $mall->name,
                                'description'     => $mall->description,
                                'address_line'    => trim(implode("\n", [$mall->address_line1, $mall->address_line2, $mall->address_line2])),
                                'city'            => $mall->city,
                                'country'         => $mall->Country->name,
                                'phone'           => $mall->phone,
                                'operating_hours' => $mall->operating_hours,
                                'object_type'     => $mall->object_type,
                                'logo_url'        => $mall->mediaLogoOrig[0]->path,
                                'status'          => $mall->status,
                                'ci_domain'       => $mall->ci_domain,
                                'position'        => [
                                    'lon' => $geofence->longitude,
                                    'lat' => $geofence->latitude
                                ],
                                'area' => [
                                    'type'        => 'polygon',
                                    'coordinates' => $geofence->area
                                ]
                            ]
                        ]
                    ];
                    // validation geofence
                    if (empty($geofence->area) || empty($geofence->latitude) || empty($geofence->longitude)) {
                        unset($params['body']['doc']['position']);
                        unset($params['body']['doc']['area']);
                    }

                    $response = $this->poster->update($params);

                    $this->info('Updating document mall index in mall ' . $mall->name . '... OK');
                } else {
                    $message = "Mall with merchant_id " . $mall->merchant_id . " and mall name " . $mall->name . " ... NOT FOUND.";
                    $this->error($message);
                }
            } catch (Exception $e) {
                $message = "Updating document mall index mall with merchant_id " . $mall->merchant_id . " and mall name " . $mall->name . " ... FAILED." . $e->getMessage();
                $this->error($message);
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