<?php
/**
 * PHP Unit Test for Category Controller getSearchCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSearchCategoryTest extends TestCase
{
    private $baseUrl = '/api/v1/family/search/';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();
        $this->authData   = Factory::create('Apikey', ['user_id' => 'factory:user_super_admin']);
        $this->categories = Factory::times(6)->create('Category');
    }

    public function tearDown()
    {
        DB::rollback();
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testError_get_without_auth_data()
    {
        $data          = new stdclass();
        $data->code    = Status::CLIENT_ID_NOT_FOUND;
        $data->status  = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data    = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', $this->baseUrl)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testError_get_with_invalid_auth_data()
    {
        $data          = new stdclass();
        $data->code    = Status::INVALID_SIGNATURE;
        $data->status  = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data    = NULL;

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature('invalid', 'sha256');

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testError_get_category_as_guest()
    {
        $apiKey = Factory::create('Apikey', ['user_id' => 'factory:user_guest']);

        $_GET['apikey']       = $apiKey->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($apiKey->api_secret_key, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should be failed
        $this->assertResponseStatus(403);

        // Should be error access denied
        $this->assertSame(Status::ACCESS_DENIED, $response->code);
        $this->assertSame('You do not have permission to view category.', $response->message);
    }

    public function testOK_get_without_additional_parameters()
    {
        // Set the client API Keys
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
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
        $categories = Category::orderBy('category_name', 'asc')->get();

        $this->assertSame($categories[0]->category_name, $response->data->records[0]->category_name);
        $this->assertSame($categories[3]->category_name, $response->data->records[3]->category_name);
        $this->assertSame($categories[5]->category_name, $response->data->records[5]->category_name);
    }

    public function testOK_get_with_custom_shorter()
    {
        // Set the client API Keys
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['sortby']      = 'registered_date';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
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
        $categories = Category::orderBy('created_at', 'asc')->get();

        $this->assertSame($categories[0]->category_name, $response->data->records[0]->category_name);
        $this->assertSame($categories[3]->category_name, $response->data->records[3]->category_name);
        $this->assertSame($categories[5]->category_name, $response->data->records[5]->category_name);
    }

    public function testError_get_search_with_unknown_sorter()
    {
        // Set the client API Keys
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['sortby']      = 'unknown_field';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/The sort by argument you specified is not valid/i', $response->message);
    }

    public function testOK_get_search_by_category_name()
    {
        $searchable = Factory::create('Category', array('category_name' => 'Unique Searchable'));

        // Set the client API Keys
        $_GET['apikey']        = $this->authData->api_key;
        $_GET['apitimestamp']  = time();
        $_GET['category_name'] = ['Unique Searchable'];

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should Be OK
        $this->assertResponseOk();
        // Should Be No Error
        $this->assertSame(Status::OK, (int)$response->code);
        // Should Return Correct Number of Records
        // Failed Bugs See CategoryApiController@getSearchCategory on line 744, 896
        // 744: Builder#whereIn accept only array and parameter from Request was string,
        // 896: Zero Exceptions code should be a unknown error.
        $this->assertSame(1, count($response->data->records));
        $this->assertSame($searchable->category_id, $response->data->records[0]->category_id);

    }

    public function testOK_get_search_by_category_name_like()
    {
        $searchable = Factory::create('Category', array('category_name' => 'Unique Searchable'));

        // Set the client API Keys
        $_GET['apikey']        = $this->authData->api_key;
        $_GET['apitimestamp']  = time();
        $_GET['category_name_like'] = 'Unique';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should Be OK
        $this->assertResponseOk();
        // Should Be No Error
        $this->assertSame(Status::OK, (int)$response->code);
        // should return correct number fo result
        $this->assertSame(1, count($response->data->records));
        // should  return correct data
        $this->assertSame($searchable->category_id, $response->data->records[0]->category_id);
    }

    public function testOK_get_search_with_custom_pagination()
    {
        // Set the client API Keys
        $_GET['apikey']        = $this->authData->api_key;
        $_GET['apitimestamp']  = time();
        $_GET['take']          = '1';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'GET';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        // Should Be OK
        $this->assertResponseOk();
        // Should Be No Error
        $this->assertSame(Status::OK, (int)$response->code);
        // should return correct number fo result
        $this->assertSame(1, count($response->data->records));
    }
}
