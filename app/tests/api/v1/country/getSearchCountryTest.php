<?php
/**
 * PHP Unit Test for Category Controller getSearchCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSearchCountryTest extends TestCase
{
    private $baseUrl = '/api/v1/country/list';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('apikey_super_admin');
        $this->countries = Factory::times(6)->create('Country');
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testOK_get_search_country_with_custom_pagination()
    {
        // Set the client API Keys
        $_GET['apikey'] = $this->authData->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['take'] = 2;
        $_GET['skip'] = 2;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should Be OK
        $this->assertResponseOk();
        // Should Be No Error
        $this->assertSame(Status::OK, (int)$response->code);
        // Should Return Correct Number of Records
        $this->assertSame(2, count($response->data->records));

        // should shorted by name
        $countries = Country::orderBy('name', 'asc')->get();

        $this->assertSame($countries[2]->name, $response->data->records[0]->name);
        $this->assertSame($countries[3]->name, $response->data->records[1]->name);
    }

    public function testOK_get_search_country_as_guest()
    {
        $apiKey = Factory::create('Apikey', ['user_id' => 'factory:user_guest']);

        $_GET['apikey'] = $apiKey->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($apiKey->api_secret_key, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should be ok
        $this->assertResponseOk();

        // Should be error access denied
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should return correct number of country
        $this->assertSame(6, count($response->data->records));
    }

    public function testOK_get_search_without_parameter()
    {
        // Set the client API Keys
        $_GET['apikey'] = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should Be OK
        $this->assertResponseOk();
        // Should Be No Error
        $this->assertSame(Status::OK, (int)$response->code);
        // Should Return Correct Number of Records
        $this->assertSame(6, count($response->data->records));

        // should shorted by name
        $countries = Country::orderBy('name', 'asc')->get();

        $this->assertSame($countries[0]->name, $response->data->records[0]->name);
        $this->assertSame($countries[3]->name, $response->data->records[3]->name);
        $this->assertSame($countries[5]->name, $response->data->records[5]->name);
    }

    public function testOK_get_search_with_names_parameter()
    {
        Factory::create('Country', ['name' => 'Unique Searchable']);

        // Set the client API Keys
        $_GET['apikey'] = $this->authData->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['names'] = ['Unique Searchable'];

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should Be OK
        $this->assertResponseOk();
        // Should Be No Error
        $this->assertSame(Status::OK, (int)$response->code);
        // Should Return Correct Number of Records
        $this->assertSame(1, count($response->data->records));

        // should return correct content
        $this->assertSame('Unique Searchable', $response->data->records[0]->name);
    }
}
