<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Country API
 */
class SearchCountryTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    /** @var Country[] */
    private $countries;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->countries = [
            Factory::create('Country'),
            Factory::create('Country'),
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
        $url = '/api/v1/country/list?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testSearchCountry()
    {
        $response = $this->makeRequest([]);
        $this->assertSame('success', $response->status);
        $this->assertSame(2, $response->data->returned_records);

        $response = $this->makeRequest(['country_ids' => [$this->countries[0]->country_id]]);
        $this->assertSame('success', $response->status);
        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame((string)$this->countries[0]->country_id, $response->data->records[0]->country_id);

        $response = $this->makeRequest(['country_ids' => [$this->countries[1]->country_id]]);
        $this->assertSame('success', $response->status);
        $this->assertSame(1, $response->data->returned_records);
        $this->assertSame((string)$this->countries[1]->country_id, $response->data->records[0]->country_id);

        $response = $this->makeRequest(['country_ids' => [$this->countries[0]->country_id, $this->countries[1]->country_id]]);
        $this->assertSame('success', $response->status);
        $this->assertSame(2, $response->data->returned_records);
    }


}
