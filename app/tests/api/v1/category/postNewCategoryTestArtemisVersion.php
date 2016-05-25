<?php
/**
 * PHP Unit Test for Category API Controller postNewCategory
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewCategoryTestArtemisVersion extends TestCase
{
    private $apiUrl = '/api/v1/category/new';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        $this->enLang = Factory::create('Language', ['name' => 'en']);
    }

    public function setRequestPostNewCategory($api_key, $api_secret_key, $data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function testRequiredVariable()
    {
        /*
        * test category_name is required
        */
        $data = [];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category name field is required", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test status is required
        */
        $data = ['category_name' => 'book store'];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The status field is required", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test id_language_default is required
        */
        $data = [
                'category_name' => 'book store',
                'status'        => 'active'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The default language field is required", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test required variable success
        */
        $data = [
                'category_name'    => 'book store',
                'status'           => 'active',
                'default_language' => 'en'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame("book store", $response->data->category_name);
    }

    public function testResponseSuccessPostNewCategory()
    {

    }

    public function testResponseFailedPostNewCategory()
    {

    }

    public function testPostNewCategorySuccess()
    {

    }
}