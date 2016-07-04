<?php
/**
 * Unit test for MallGeolocAPIController::getSearchMallKeyword(). Call to this
 * API does not need authentication.
 * Priority : name, city, country, position, address_line, description
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 **/
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Elasticsearch\ClientBuilder;
use Faker\Factory as Faker;

class getSearchMallKeywordEsTest extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-nearby-es';
    private $userLocation = [-6.16978, 106.82105]; // Location default in monas

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

        $malls = array(
            array(
                "name" => "Matahari Department Store",
                "city" => "Jakarta",
                "country" => "Indonesia",
                "description" => "description",
                "address_line" => "Jl. Pluit Indah Raya, Pluit, Penjaringan, Kota Jkt Utara",
                "position" => array("lon" => 106.78821, "lat" => -6.11589),
            ),
            array(
                "name" => "Grand Indonesia",
                "city" => "Jakarta",
                "country" => "Indonesia",
                "description" => "description",
                "address_line" => "Jalan M.H. Thamrin No. 1, Menteng, Jakarta Pusat",
                "position" => array("lon" => 106.82170, "lat" => -6.18794),
            ),
            array(
                "name" => "Plaza Senayan",
                "city" => "Jakarta",
                "country" => "Indonesia",
                "description" => "description",
                "address_line" => "Jl. Asia Afrika No.8, Jakarta Pusat",
                "position" => array("lon" => 106.80067, "lat" => -6.21668),
            ),
            array(
                "name" => "Matahari Department Store",
                "city" => "Denpasar",
                "country" => "Indonesia",
                "description" => "description",
                "address_line" => "Jl. Raya Buluh Indah No.138, Pemecutan Kaja, Denpasar Utara",
                "position" => array("lon" => 115.19638, "lat" => -8.63748),
            ),
            array(
                "name" => "Seminyak Village",
                "city" => "Badung",
                "country" => "Indonesia",
                "description" => "description",
                "address_line" => "Jl. Kayu Aya Seminyak Kuta Utara",
                "position" => array("lon" => 115.15605, "lat" => -8.68267),
            ),
            array(
                "name" => "Istana Plaza",
                "city" => "Bandung",
                "country" => "Indonesia",
                "description" => "description",
                "address_line" => "Jl. Merdeka, Citarum, Sumur Bandung",
                "position" => array("lon" => 107.58201, "lat" => -6.89480),
            ),
            array(
                "name" => "VivoCity",
                "city" => "Bukit Merah",
                "country" => "Singapore",
                "description" => "VivoCity is the largest shopping plaza mall in Singapore. Located in the HarbourFront precinct of Bukit Merah, it was designed by the Japanese architect Toyo Ito. Its name is derived from the word vivacity",
                "address_line" => "1 Harbourfront Walk",
                "position" => array("lon" => 103.82257, "lat" => 1.26707)
            ),
        );

        // $this->position = $position;

        foreach ($malls as $key => $val) {
            $params['body'][] = [
                'index' => [
                    '_index' => 'malltest',
                    '_type' => 'basictest',
                ]
            ];

            $params['body'][] = [
                // 'name' => Faker::create()->sentence(3),
                'name' => $val['name'],
                'city' => $val['city'],
                'country' => $val['country'],
                'description' => $val['description'],
                'address_line' => $val['address_line'],
                'position' => ["lon" => $val['position']['lon'], "lat" => $val['position']['lat']]
            ];
        }

        $responses = $client->bulk($params);

        $client->indices()->refresh($indexParams);

        $_GET = [];
    }

    public function testOK_searching_mall_by_name()
    {
        // Search with Keyword "Bandung"
        $_GET['latitude'] = $this->userLocation[0];
        $_GET['longitude'] = $this->userLocation[1];
        $_GET['keyword_search'] = 'Bandung';
        $_GET['distance'] = -1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);

        // Search with keyword "Plaza"
        $_GET['latitude'] = $this->userLocation[0];
        $_GET['longitude'] = $this->userLocation[1];
        $_GET['keyword_search'] = 'Plaza';
        $_GET['distance'] = -1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(3, (int)$response->data->total_records);

        // Search with keyword "Jakarta"
        $_GET['latitude'] = $this->userLocation[0];
        $_GET['longitude'] = $this->userLocation[1];
        $_GET['keyword_search'] = 'Jakarta';
        $_GET['distance'] = -1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(3, (int)$response->data->total_records);

        // Search with keyword "Jakarta"
        $_GET['latitude'] = $this->userLocation[0];
        $_GET['longitude'] = $this->userLocation[1];
        $_GET['keyword_search'] = 'Matahari Bali';
        $_GET['distance'] = -1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }


    public function testOK_searching_no_data()
    {
        // Search with Keyword "Bandung"
        $_GET['latitude'] = $this->userLocation[0];
        $_GET['longitude'] = $this->userLocation[1];
        $_GET['keyword_search'] = 'asdfghqwertysdf';
        $_GET['distance'] = -1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(0, (int)$response->data->total_records);
    }

}