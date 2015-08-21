<?php
use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * @property MacAddress[] macAddresses
 * @property Apikey authData
 * @property User[] users
 */
class postBatchUserChangesTest extends TestCase
{
    public function setUp() {
        parent::setUp();
        // this needs MAC addresses with an associated email
        $this->macAddresses = Factory::times(5)->create('MacAddress', ['status' => 'active']);
        // and the email should be associated with a user with consumer role
        $role = Factory::create('Role', [
            'role_name' => 'Consumer',
            'role_order' => 1
        ]);
        $this->users = [];
        foreach ($this->macAddresses as $address) {
            $this->users[] = Factory::create('User', [
                'user_email' => $address->user_email,
                'user_role_id' => $role->role_id
            ]);
        }

        $this->authData = Factory::create('apikey_super_admin');
    }

    private function makeRequest($in = null, $out = null)
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];
        $_POST = [];
        if ($in !== null) {
            $_POST['in_macs'] = $in;
        }
        if ($out !== null) {
            $_POST['out_macs'] = $out;
        }
        $url = '/api/v1/captive-portal/network/batch-enter-leave?' . http_build_query($_GET);
        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    private function assertSingleResponseOk($response)
    {
        $this->assertSame(200, $response[0]);
        $json = json_decode($response[1]);
        $this->assertStringEndsWith('OK', $json->message);
        $this->assertSame('success', $json->status);
    }

    private function assertSingleResponseFail($response, $code, $expected_message_regexp)
    {
        $this->assertSame($code, $response[0]);
        $json = json_decode($response[1]);
        $this->assertSame('error', $json->status);
        $this->assertRegExp($expected_message_regexp, $json->message);
    }

    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    private function assertJsonResponseMatchesRegExp(
        $expected_code,
        $expected_status,
        $expected_message_regexp,
        $response
    ) {
        $this->assertRegExp($expected_message_regexp, $response->message);
        $this->assertSame($expected_status, $response->status);
        $this->assertSame($expected_code, (int)$response->code);
    }

    public function testBatchSendSingleString()
    {
        $response = $this->makeRequest($this->macAddresses[0]->mac_address, $this->macAddresses[1]->mac_address);
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(1, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(1, $count);
    }

    public function testBatchSendMultipleString()
    {
        $response = $this->makeRequest(
            [$this->macAddresses[0]->mac_address, $this->macAddresses[1]->mac_address],
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address]
            );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(2, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testMacNotFoundIn()
    {
        $deleted_mac = Factory::create('MacAddress', ['status' => 'deleted']);
        $response = $this->makeRequest(
            [$deleted_mac->mac_address],
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address]
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseFail($in_response, 200, '/not found/i');
            $count++;
        }
        $this->assertSame(1, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testMacNotFoundOut()
    {
        $deleted_mac = Factory::create('MacAddress', ['status' => 'deleted']);
        $response = $this->makeRequest(
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address],
            [$deleted_mac->mac_address]
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $deleted_mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(2, $count);
        $count = 0;
        foreach ($response->data->out as $deleted_mac => $out_response) {
            $this->assertSingleResponseFail($out_response, 200, '/not found/i');
            $count++;
        }
        $this->assertSame(1, $count);
    }

    public function testInvalidMacIn()
    {
        $response = $this->makeRequest(
            ['xx:yy:zz:ww:aa:bb'],
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address]
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseFail($in_response, 200, '/not valid/i');
            $count++;
        }
        $this->assertSame(1, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testInvalidMacOut()
    {
        $response = $this->makeRequest(
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address],
            ['xx:yy:zz:ww:aa:bb']
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $deleted_mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(2, $count);
        $count = 0;
        foreach ($response->data->out as $deleted_mac => $out_response) {
            $this->assertSingleResponseFail($out_response, 200, '/not valid/i');
            $count++;
        }
        $this->assertSame(1, $count);
    }
}
