<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Activity API
 */
class SearchActivityTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    /** @var Retailer[] */
    private $retailers;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->retailers = [
            Factory::create('retailer_mall'),
            Factory::create('retailer_mall'),
        ];
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge($data, [
                'apikey' => $authData->api_key,
                'apitimestamp' => time(),
            ]);
        $_POST = [];
        $url = '/api/v1/activity/list?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testSearchActivity()
    {
        $activity_table = DB::getTablePrefix() . 'activities';
        $activity1_id = \Orbit\Database\ObjectID::make();
        $activity2_id = \Orbit\Database\ObjectID::make();

        // Insert dummy data
        DB::statement("INSERT INTO `{$activity_table}`
                (`activity_id`, `activity_name`, `location_id`, `group`, `response_status`)
                VALUES
                ('{$activity1_id}', 'x', '{$this->retailers[0]->merchant_id}', 'mobile-ci', 'OK'),
                ('{$activity2_id}', 'x', '{$this->retailers[1]->merchant_id}', 'mobile-ci', 'OK')"
        );

        $response = $this->makeRequest([]);
        $this->assertSame('success', $response->status);
        $this->assertSame(2, $response->data->returned_records);

        $response = $this->makeRequest(['merchant_ids' => [$this->retailers[0]->parent_id]]);
        $this->assertSame('success', $response->status);
        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame((string)$activity1_id, $response->data->records[0]->activity_id);

        $response = $this->makeRequest(['merchant_ids' => [$this->retailers[1]->parent_id]]);
        $this->assertSame('success', $response->status);
        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame((string)$activity2_id, $response->data->records[0]->activity_id);

        $response = $this->makeRequest(['merchant_ids' => [$this->retailers[1]->parent_id, $this->retailers[0]->parent_id]]);
        $this->assertSame('success', $response->status);
        $this->assertSame(2, $response->data->returned_records);
    }


}
