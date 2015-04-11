<?php
/**
 * PHP Unit Test for Category Controller postNewCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postNewCategoryTest extends TestCase
{
    private $baseUrl = '/api/v1/family/new/';

    public function setUp()
    {
        parent::setUp();

        $this->authData   = Factory::create('Apikey', ['user_id' => 'factory:user_super_admin']);
        $this->category   = Factory::create('Category');
    }

    public function testError_post_category_without_auth_data()
    {
        $data          = new stdclass();
        $data->code    = Status::CLIENT_ID_NOT_FOUND;
        $data->status  = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data    = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $this->baseUrl)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testError_post_new_category_with_invalid_auth_data()
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
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature('invalid', 'sha256');

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testError_post_new_category_without_required_parameter()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        // Should be failed
        $this->assertResponseStatus(403);
        $this->assertNotSame(Status::OK, $response->code);
        $this->assertRegExp('/is required/', $response->message);
    }

    public function testError_post_new_category_with_invalid_merchant()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id'] = '99999999999999999999';
        $_POST['category_name'] = 'Unique Submited';
        $_POST['category_level'] = '1';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be failed
        $this->assertResponseStatus(403);

        // should say merchant not found
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/not found/i', $response->message);
    }

    public function testOK_post_new_category_with_valid_data()
    {
        $merchant = Factory::create('Merchant');

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id']    = $merchant->merchant_id;
        $_POST['category_name']  = 'Unique Submited';
        $_POST['category_level'] = '1';
        $_POST['status']         = 'active';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be failed
        $this->assertResponseOk();

        // should say merchant not found
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }
}
