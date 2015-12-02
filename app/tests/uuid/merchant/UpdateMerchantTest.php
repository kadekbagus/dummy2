<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Merchant API
 */
class UpdateMerchantTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    private $country;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->country = Factory::create('Country');
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
        $url = '/api/v1/merchant/update?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testUpdateMerchant()
    {
        $merchant = Factory::create('Merchant');
        $response = $this->makeRequest([
            'merchant_id' => $merchant->merchant_id,
            'email' => 'not@example.com',
            'country' => $this->country->country_id,
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $merchant = Merchant::find($response->data->merchant_id);
        $this->assertNotNull($merchant);
        $this->assertSame((string)$this->country->country_id, (string)$merchant->country_id);
    }


}
