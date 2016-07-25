<?php
/**
 * PHP Unit Test for Category API Controller postUpdateCategory
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateCategoryTest extends TestCase
{
    private $apiUrlNew = '/api/v1/category/new';
    private $apiUrlList = '/api/v1/category/list';
    private $apiUrlUpdate = '/api/v1/category/update';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        $this->enLang = Factory::create('Language', ['name' => 'en']);
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostNewCategory($api_key, $api_secret_key, $new_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($new_data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlNew . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        // clear all;
        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function setRequestGetListCategory($api_key, $api_secret_key, $filter)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($filter as $field => $value) {
            $_GET[$field] = $value;
        }

        $url = $this->apiUrlList . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        // clear all;
        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function setRequestPostUpdateCategory($api_key, $api_secret_key, $update_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($update_data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlUpdate . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        // clear all;
        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function setDefaultCategory()
    {
        /*
        * category health
        */
        $data = [
                'category_name'    => 'health',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"kesehatan","description":"ini adalah toko kesehatan"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->category_health = $response->data;

        /*
        * category book store
        */
        $data = [
                'category_name'    => 'book store',
                'status'           => 'active',
                'default_language' => 'en'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->category_book_store = $response->data;

        /*
        * category restaurant
        */
        $data = [
                'category_name'    => 'restaurant',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->zhLang->language_id . '":{"category_name":"restoran zh","description":"restoran china enak"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->category_restaurant = $response->data;

        /*
        * category jewellery
        */
        $data = [
                'category_name'    => 'jewellery',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->zhLang->language_id . '":{"category_name":"perhiasan zh","description":"perhiasan china bagus"},"' . $this->idLang->language_id . '":{"category_name":"perhiasan","description":"ini adalah toko perhiasan"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->category_jewellery = $response->data;
    }

    public function testRequiredVariable()
    {
        $this->setDefaultCategory();
        $_GET = [];
        $_POST = [];

        /*
        * test category_id is required
        */
        $update_data = [];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category id field is required", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test default_language is required
        */
        $update_data = ['category_id' => $this->category_book_store->category_id];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The default language field is required", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test required variable success
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame($this->category_book_store->category_name, $response->data->category_name);
    }

    public function testSuccessPostUpdateCategory()
    {
        $this->setDefaultCategory();
        $_GET = [];
        $_POST = [];

        /*
        * exist category name but not me
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_name' => 'book store'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame($this->category_book_store->category_name, $response->data->category_name);

        /*
        * not exist category name
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_name' => 'library'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame('library', $response->data->category_name);

        /*
        * category level
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_level' => 2
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(2, $response->data->category_level);

        /*
        * category order
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_order' => 1
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(1, $response->data->category_order);

        /*
        * status
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'status' => 'inactive'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame('inactive', $response->data->status);

        /*
        * exist translation category name but not me
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);

        $data_translation = @json_decode($update_data['translations']);
        foreach ($data_translation as $translations_key => $translation_value) {
            $translations_key_id = 'translation_' . snake_case($translations_key);
            if (ctype_upper(substr($translations_key, 0, 1))) {
                $translations_key_id = 'translation__' . snake_case($translations_key);
            }
            $this->assertSame($translation_value->category_name, $response->data->$translations_key_id->category_name);
            $this->assertSame($translation_value->description, $response->data->$translations_key_id->description);
        }

        /*
        * not exist translation category name
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko majalah","description":"ini adalah toko buku"}}'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);

        $data_translation = @json_decode($update_data['translations']);
        foreach ($data_translation as $translations_key => $translation_value) {
            $translations_key_id = 'translation_' . snake_case($translations_key);
            if (ctype_upper(substr($translations_key, 0, 1))) {
                $translations_key_id = 'translation__' . snake_case($translations_key);
            }
            $this->assertSame($translation_value->category_name, $response->data->$translations_key_id->category_name);
            $this->assertSame($translation_value->description, $response->data->$translations_key_id->description);
        }
    }

    public function testFailedPostUpdateCategory()
    {
        $this->setDefaultCategory();
        $_GET = [];
        $_POST = [];

        /*
        * test failed category_id
        */
        $update_data = [
                            'category_id' => 'dfasd6514',
                            'default_language' => 'en',
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Category ID you specified is not found", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test exist category name
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_name' => 'restaurant'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category name has already been used", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test category level must numeric
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_level' => 'restaurant'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category level must be a number", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test category order must numeric
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'category_order' => 'restaurant'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category order must be a number", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test failed status
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'status' => 'off'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category status you specified is not found", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test failed default language
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'jp',
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The default language must english", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * exist translation category name
        */
        $update_data = [
                            'category_id' => $this->category_book_store->category_id,
                            'default_language' => 'en',
                            'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"perhiasan","description":"ini adalah toko buku"}}'
                        ];

        $response = $this->setRequestPostUpdateCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $update_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category name has already been used", $response->message);
        $this->assertSame(NULL, $response->data);
    }
}