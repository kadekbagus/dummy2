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
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);
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
        $this->assertSame($data['category_name'], $response->data->category_name);
    }

    public function testResponseSuccessPostNewCategory()
    {
        /*
        * test response success
        */
        $data = [
                'category_name'    => 'book store',
                'category_level'   => 1,
                'category_order'   => 0,
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);

        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        foreach ($data as $field => $value) {
            switch (true) {
                case ($field === 'default_language'):
                    $translations_key_id = 'translation_' . snake_case($this->enLang->language_id);
                    if (ctype_upper(substr($this->enLang->language_id, 0, 1))) {
                        $translations_key_id = 'translation__' . snake_case($this->enLang->language_id);
                    }
                    $this->assertSame($data['category_name'], $response->data->$translations_key_id->category_name);
                    break;
                case ($field === 'translations'):
                    $data_translation = @json_decode($data['translations']);
                    foreach ($data_translation as $translations_key => $translation_value) {
                        $translations_key_id = 'translation_' . snake_case($translations_key);
                        if (ctype_upper(substr($translations_key, 0, 1))) {
                            $translations_key_id = 'translation__' . snake_case($translations_key);
                        }
                        $this->assertSame($translation_value->category_name, $response->data->$translations_key_id->category_name);
                        $this->assertSame($translation_value->description, $response->data->$translations_key_id->description);
                    }
                    break;
                default:
                    $this->assertSame((string)$data[$field], (string)$response->data->{$field});
                    break;
            }
        }
    }

    public function testResponseFailedPostNewCategory()
    {
        /*
        * test exist category name
        */
        $data = [
                'category_name'    => 'book store',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame($data['category_name'], $response->data->category_name);

        $data = [
                'category_name'    => 'book store',
                'status'           => 'active',
                'default_language' => 'en'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category name has already been used", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test category level must numeric
        */
        $data = [
                'category_name'    => 'book store 2',
                'category_level'   => 'a',
                'category_order'   => 0,
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category level must be a number", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test category order must numeric
        */
        $data = [
                'category_name'    => 'book store 2',
                'category_level'   => 0,
                'category_order'   => 'a',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category order must be a number", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test status failed
        */
        $data = [
                'category_name'    => 'book store 2',
                'category_level'   => 1,
                'category_order'   => 0,
                'status'           => 'off',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category status you specified is not found", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test default language failed
        */
        $data = [
                'category_name'    => 'book store 2',
                'category_level'   => 1,
                'category_order'   => 0,
                'status'           => 'active',
                'default_language' => 'jp',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The language default you specified is not found", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test translation category name failed because already exist
        */
        $data = [
                'category_name'    => 'book store 2',
                'category_level'   => 1,
                'category_order'   => 0,
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"toko buku","description":"ini adalah toko buku"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category name has already been used", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testPostNewCategorySuccess()
    {

    }
}