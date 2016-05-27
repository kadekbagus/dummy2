<?php
/**
 * PHP Unit Test for Category API Controller postDeleteCategory
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateCategoryTestArtemisVersion extends TestCase
{
    private $apiUrlNew = '/api/v1/category/new';
    private $apiUrlDelete = '/api/v1/category/delete';

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

    public function setRequestPostDeleteCategory($api_key, $api_secret_key, $delete_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($delete_data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlDelete . '?' . http_build_query($_GET);

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
        $delete_data = [];
        $response = $this->setRequestPostDeleteCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $delete_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The category id field is required", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test required variable success
        */
        $delete_data = ['category_id' => $this->category_book_store->category_id];
        $response = $this->setRequestPostDeleteCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $delete_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Category has been successfully deleted.", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testDeleteCategorySuccess()
    {
        $this->setDefaultCategory();
        $_GET = [];
        $_POST = [];

        /*
        * test delete success
        */
        $delete_data = ['category_id' => $this->category_restaurant->category_id];
        $response = $this->setRequestPostDeleteCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $delete_data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Category has been successfully deleted.", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test categories was soft delete
        */
        $get_delete_categories = Category::where('status', 'deleted')
                                        ->where('category_id', $this->category_restaurant->category_id)
                                        ->get();

        foreach ($get_delete_categories as $key => $value) {
            $this->assertSame($this->category_restaurant->category_id, $value->category_id);
            $this->assertSame($this->category_restaurant->category_name, $value->category_name);
            $this->assertSame('deleted', $value->status);
        }

        /*
        * test category translations was soft delete
        */
        $get_delete_category_translations = CategoryTranslation::where('status', 'deleted')
                                        ->where('category_id', $this->category_restaurant->category_id)
                                        ->get();

        foreach ($get_delete_category_translations as $key => $value) {
            $translation_key_id = 'translation_' . snake_case($value->merchant_language_id);
            if (ctype_upper(substr($value->merchant_language_id, 0,1))) {
                $translation_key_id = 'translation__' . snake_case($value->merchant_language_id);
            }
            $this->assertSame($this->category_restaurant->$translation_key_id->category_id, $value->category_id);
            $this->assertSame($this->category_restaurant->$translation_key_id->category_name, $value->category_name);
            $this->assertSame('deleted', $value->status);
        }
    }

    public function testDeleteCategoryFailed()
    {
        $this->setDefaultCategory();
        $_GET = [];
        $_POST = [];

        /*
        * test failed category id
        */
        $delete_data = ['category_id' => 'dsfdsf351456'];
        $response = $this->setRequestPostDeleteCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $delete_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Category ID you specified is not found", $response->message);
        $this->assertSame(NULL, $response->data);

        /*
        * test category has link to tenant
        */

        $tenant = Factory::create('Tenant');
        $category = Factory::create('Category');
        $category_merchant = Factory::create('CategoryMerchant', [
                'category_id' => $category->category_id,
                'merchant_id' => $tenant->merchant_id
            ]);

        $delete_data = ['category_id' => $category->category_id];
        $response = $this->setRequestPostDeleteCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $delete_data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame('Cannot delete a category with tenants', $response->message);
        $this->assertSame(NULL, $response->data);
    }
}