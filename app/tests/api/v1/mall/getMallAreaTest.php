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

class getMallAreaTest extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-area';

    public function setUp()
    {
        parent::setUp();
        Config::set('orbit.is_demo', FALSE);
        Config::set('orbit.elasticsearch.indices.malldata.index', 'malltest');
        Config::set('orbit.elasticsearch.indices.malldata.type', 'basictest');
    
        $_GET = [];
    }

    public function create_index($position)
    {
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
                'status' => $pos['status'],
                'position' => ["lon" => $pos['lon'], "lat" => $pos['lat']]
            ];
        }

        $responses = $client->bulk($params);

        $client->indices()->refresh($indexParams);
    }

    public function testOK_No_Mall_In_Area()
    {
        // Create mall in antartica
        $position = [ 
            array("lon" => 25.120362, "lat" => -76.336863, "status" => 'active')
        ];
        $this->create_index($position);

        //area in antartica sea
        $_GET['area'] = '-61.93644640661632 57.95057812426762, -39.97304109648114 57.95057812426762, -39.97304109648114 91.34901562426762, -61.93644640661632 91.34901562426762, -61.93644640661632 57.95057812426762';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(0, (int)$response->data->total_records);
    }

    public function testOK_Found_One_Mall_In_Area()
    {
        // Create mall in antartica
        $position = [ 
            array("lon" => 14.146998, "lat" => -72.270103, "status" => 'active')
        ];
        $this->create_index($position);

        $_GET['area'] = '-75.18442825097793 8.698869139892622, -69.85292893887913 8.698869139892622, -69.85292893887913 25.398087889892622, -75.18442825097793 25.398087889892622, -75.18442825097793 8.698869139892622';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Area()
    {
        // Create 2 malls in antartica
        $position = [ 
            array("lon" => 25.120362, "lat" => -76.336863, "status" => 'active'),
            array("lon" => 14.146998, "lat" => -72.270103, "status" => 'active')
        ];
        $this->create_index($position);

        $_GET['area'] = '-81.85111601628252 -12.252058594482378, -62.111671481762606 -12.252058594482378, -62.111671481762606 54.54481640551762, -81.85111601628252 54.54481640551762, -81.85111601628252 -12.252058594482378';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Area_Cross_International_Date_Line()
    {
        // Create 2 malls in antartica
        $position = [ 
            array("lon" => 25.120362, "lat" => -76.336863, "status" => 'active'),
            array("lon" => 14.146998, "lat" => -72.270103, "status" => 'active')
        ];
        $this->create_index($position);

        $_GET['area'] = '-84.78803812147538 -49.64951953198238, 73.06899542190443 -49.64951953198238, 73.06899542190443 -142.46201953198238, -84.78803812147538 -142.46201953198238, -84.78803812147538 -49.64951953198238';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }

    public function testOK_Found_One_Mall_In_Area_With_Production_Host_Name()
    {
        // Create 2 malls in antartica
        $position = [ 
            array("lon" => 25.120362, "lat" => -76.336863, "status" => 'inactive'),
            array("lon" => 14.146998, "lat" => -72.270103, "status" => 'active')
        ];
        $this->create_index($position);

        $_GET['area'] = '-81.85111601628252 -12.252058594482378, -62.111671481762606 -12.252058594482378, -62.111671481762606 54.54481640551762, -81.85111601628252 54.54481640551762, -81.85111601628252 -12.252058594482378';
        $_GET['hostname'] = 'example3.com';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Area_With_Demo_Host_Name()
    {
        // Create 2 malls in antartica
        $position = [ 
            array("lon" => 25.120362, "lat" => -76.336863, "status" => 'inactive'),
            array("lon" => 14.146998, "lat" => -72.270103, "status" => 'active')
        ];
        $this->create_index($position);

        $_GET['area'] = '-81.85111601628252 -12.252058594482378, -62.111671481762606 -12.252058594482378, -62.111671481762606 54.54481640551762, -81.85111601628252 54.54481640551762, -81.85111601628252 -12.252058594482378';
        
        Config::set('orbit.is_demo', TRUE);

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }
}