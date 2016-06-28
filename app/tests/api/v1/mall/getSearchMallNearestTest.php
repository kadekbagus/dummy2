<?php
/**
 * Unit test for MallGeolocAPIController::getSearchMallNearby(). Call to this
 * API does not need authentication.
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Elasticsearch\ClientBuilder;
use Faker\Factory as Faker;

class getSearchMallNearBy extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-nearest';
    private $myAntarticaLocation = [-66.287162, 110.527896];

    public function setUp()
    {
        parent::setUp();
        Config::set('orbit.is_demo', FALSE);
        Config::set('orbit.elasticsearch.indices.malldata.index', 'malltest');
        Config::set('orbit.elasticsearch.indices.malldata.type', 'basictest');
        $host = Config::get('orbit.elasticsearch');

        $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();
        $this->clients = $client;

        $indexParams['index']  = 'malltest';  
        $available = $client->indices()->exists($indexParams);

        if ($available) { // Delete index is exist
            $params = ['index' => 'malltest'];
            $response = $client->indices()->delete($params);
        }

        // Create new index, setting, and mapping
        $createParam = [
            'index' => 'malltest',
            'body' => [    
                'settings' => [
                    'refresh_interval' => '-1',
                    'number_of_shards' => 1
                ],
                'mappings' => [ 
                    'basictest' => [    
                        '_source' => [
                            'enabled' => true
                        ],
                        'properties' => [
                            'name' => [
                                'type' => 'string'
                            ],
                            'position' => [
                                'type' => 'geo_point'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $client->indices()->create($createParam);

        $position = [ 
            array("lon" => 110.529684, "lat" => -66.305388),
            array("lon" => 110.529677, "lat" => -66.326166), 
            array("lon" => 110.531458, "lat" => -66.360110)
        ];
        $this->position = $position;

        foreach ($position as $pos) {
            $params['body'][] = [
                'index' => [
                    '_index' => 'malltest',
                    '_type' => 'basictest',
                ]
            ];

            $params['body'][] = [
                'name' => Faker::create()->sentence(3),
                'position' => ["lon" => $pos['lon'], "lat" => $pos['lat']]
            ];
        }

        $responses = $client->bulk($params);

        $client->indices()->refresh($indexParams);
    
        $_GET = [];
    }

    public function testOK_found_one_mall_nearest()
    {
        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        $_GET['width_ratio'] = 3;
        $_GET['height_ratio'] = 2;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);
        
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame($this->position[0]['lat'], $response->data->centre_point->lat);
        $this->assertSame($this->position[0]['lon'], $response->data->centre_point->lon);
    }

    public function testOK_found_another_mall_in_nearest_mall_area()
    {
        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        $_GET['width_ratio'] = 3;
        $_GET['height_ratio'] = 2;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertGreaterThan(1, (int)$response->data->total_records);
    }
    
}