<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: User API
 */
class CreateUserTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    private $role;
    private $retailer;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->role = Factory::create('Role', ['role_name' => 'retailer owner']);
        $this->retailer = Factory::create('retailer_mall');
        Config::set('orbit.shop.id', $this->retailer->merchant_id);
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
        $url = '/api/v1/user/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCreateUser()
    {
        $response = $this->makeRequest([
            'email' => 'hello@example.com',
            'firstname' => 'Hello',
            'lastname' => 'User',
            'password' => 'notverysecure',
            'password_confirmation' => 'notverysecure',
            'role_id' => $this->role->role_id,
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $user = User::find($response->data->user_id);
        $this->assertNotNull($user);
        $this->assertSame((string)$this->role->role_id, (string)$response->data->user_role_id);
    }


}
