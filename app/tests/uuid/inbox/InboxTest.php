<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Inbox API
 */
class InboxTest extends TestCase
{
    /** @var Apikey */
    private $authData;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
    }

    private function makePollRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge([], [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);
        $_POST = $data;
        $url = '/api/v1/alert/poll?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    private function makeReadRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge([], [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);
        $_POST = $data;
        $url = '/api/v1/alert/read?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testPollInbox()
    {
        $alert = new Inbox();
        $alert->user_id = $this->authData->user_id;
        $alert->from_id = $this->authData->user_id;
        $alert->from_name = 'xxx';
        $alert->inbox_type = 'alert';
        $alert->created_at = new DateTime();
        $alert->is_read = 'N';
        $alert->status = 'active';
        $alert->save();

        $response = $this->makePollRequest([]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertCount(1, $response->data->records);
    }

    public function testMarkInboxRead()
    {
        $alert = new Inbox();
        $alert->user_id = $this->authData->user_id;
        $alert->from_id = $this->authData->user_id;
        $alert->from_name = 'xxx';
        $alert->inbox_type = 'alert';
        $alert->created_at = new DateTime();
        $alert->is_read = 'N';
        $alert->status = 'active';
        $alert->save();

        $response = $this->makePollRequest([]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertCount(1, $response->data->records);

        $response = $this->makeReadRequest(['inbox_id' => $alert->inbox_id]);
        $this->assertSame('success', $response->status);

        $response = $this->makePollRequest([]);
        $this->assertSame('No new alert', $response->message);
        $this->assertSame('success', $response->status);
    }


}
