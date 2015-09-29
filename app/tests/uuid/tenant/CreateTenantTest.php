<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Tenant API
 */
class CreateTenantTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    private $country;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->country = Factory::create('Country');
        Factory::create('Role', ['role_name' => 'retailer owner']);
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
        $url = '/api/v1/tenant/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCreateTenant()
    {
        $merchant = Factory::create('Merchant');
        $response = $this->makeRequest([
            'email' => 'hello@example.com',
            'name' => 'Hello Tenant',
            'status' => 'active',
            'parent_id' => $merchant->merchant_id,
            'country' => $this->country->country_id,
            'external_object_id' => 'ext.obj',
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $tenant = Retailer::find($response->data->merchant_id);
        $this->assertNotNull($tenant);
        $this->assertSame((string)$this->country->country_id, (string)$response->data->country_id);
    }


}
