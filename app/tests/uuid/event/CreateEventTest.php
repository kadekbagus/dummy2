<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Event API
 */
class CreateEventTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    /** @var Retailer */
    private $retailer;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->retailer = Factory::create('Retailer', ['is_mall' => 'yes']);
        Factory::create('Role', ['role_name' => 'assistant']);
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge([], [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);
        $_POST = $data;
        $url = '/api/v1/event/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCreateEvent()
    {
        $retailer = Factory::create('Retailer', ['is_mall' => 'no']);
        $response = $this->makeRequest([
            'merchant_id' => $this->retailer->merchant_id,
            'event_name' => 'test event',
            'event_type' => 'informative',
            'status' => 'active',
            'link_object_type' => 'retailer',
            'retailer_ids' => [$retailer->merchant_id],
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $event = EventModel::find($response->data->event_id);
        $this->assertNotNull($event);
    }


}
