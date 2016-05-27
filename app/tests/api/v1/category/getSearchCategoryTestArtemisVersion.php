<?php
/**
 * PHP Unit Test for Category API Controller getSearchCategory
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getSearchCategoryTestArtemisVersion extends TestCase
{
    private $apiUrlNew = '/api/v1/category/new';
    private $apiUrlList = '/api/v1/category/list';

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

    public function setDefaultCategory()
    {
        /*
        * category health
        */
        $data = [
                'category_name'    => 'health',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->idLang->language_id . '":{"category_name":"sehat","description":"ini adalah toko kesehatan"}}'
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

    public function testGetListCategoryWithoutFilter()
    {
        $this->setDefaultCategory();

        /*
        * test get list category without filtering
        */
        $filter = [];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(4, $response_list->data->total_records);

        /*
        * test get exclude deleted list category without filtering
        */
        $data = [
                'category_name'    => 'hobbies',
                'status'           => 'deleted',
                'default_language' => 'en'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);

        $data = [
                'category_name'    => 'lifestyle',
                'status'           => 'active',
                'default_language' => 'en'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(5, $response_list->data->total_records);
    }

    public function testSortBy()
    {
        $this->setDefaultCategory();

        $filter = ['sort_by' => 'category_name'];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(4, $response_list->data->total_records);

        /*
        * test sort by check first category
        */
        $this->assertSame('book store', $response_list->data->records[0]->category_name);

        /*
        * test sort by check last category
        */
        $this->assertSame('restaurant', $response_list->data->records[3]->category_name);

        $filter = ['sortby' => 'category_name', 'sortmode' => 'desc'];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(4, $response_list->data->total_records);

        /*
        * test sort by and sort mode check first category
        */
        $this->assertSame('restaurant', $response_list->data->records[0]->category_name);

        /*
        * test sort by and sort mode check last category
        */
        $this->assertSame('book store', $response_list->data->records[3]->category_name);

        /*
        * test sort by translation language id (indonesia)
        */
        $filter = [
                    'sortby'      => 'translation_category_name',
                    'with'        => ['translations'],
                    'language_id' => $this->idLang->language_id
                  ];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(2, $response_list->data->total_records);
        /*
        * test sort by and sort mode check first category
        */
        $this->assertSame('perhiasan', $response_list->data->records[0]->translations[2]->category_name);

        /*
        * test sort by and sort mode check last category
        */
        $this->assertSame('sehat', $response_list->data->records[1]->translations[1]->category_name);

        /*
        * test sort by translation language id (english)
        */
        $filter = [
                    'sortby'      => 'translation_category_name',
                    'with'        => ['translations'],
                    'language_id' => $this->enLang->language_id
                  ];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(4, $response_list->data->total_records);
        /*
        * test sort by and sort mode check first category
        */
        $this->assertSame('health', $response_list->data->records[1]->translations[0]->category_name);

        /*
        * test sort by and sort mode check last category
        */
        $this->assertSame('jewellery', $response_list->data->records[2]->translations[0]->category_name);
    }

    public function testTakeDataCategory()
    {
        for ($i=0; $i < 100; $i++) { 
            Factory::create('Category');
        }

        /*
        * test get list category take 60
        */
        $filter = ['take' => 60];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(100, $response_list->data->total_records);
        $this->assertSame(60, $response_list->data->returned_records);
    }

    public function testFilterCategoryName()
    {
        $this->setDefaultCategory();

        /*
        * test get category restaurant
        */
        $filter = ['category_name' => ['restaurant']];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame('restaurant', $response_list->data->records[0]->category_name);

        /*
        * test get category restaurant
        */
        $filter = ['category_name_like' => 'staura'];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame('restaurant', $response_list->data->records[0]->category_name);

        /*
        * test get translation category restaurant
        */
        $filter = [
                    'translation_category_name_like' => 'estor',
                    'with'                           => ['translations'],
                    'language_id'                    => $this->zhLang->language_id
                    ];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        // dd($response_list->data->records[0]);
        $this->assertSame('restoran zh', $response_list->data->records[0]->translations[1]->category_name);
    }

    public function testFilterLanguage()
    {
        $this->setDefaultCategory();
        /*
        * test get category with indonesian translation
        */
        $filter = [
                    'with' => ['translations'],
                    'language_id' => $this->idLang->language_id
                    ];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(2, $response_list->data->total_records);

        /*
        * category hobbies with empty string translation
        */
        $data = [
                'category_name'    => 'hobbies',
                'status'           => 'active',
                'default_language' => 'en',
                'translations'     => '{"' . $this->zhLang->language_id . '":{"category_name":"","description":""},"' . $this->idLang->language_id . '":{"category_name":"hobi","description":"ini adalah toko peralatan hobi"}}'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);

        /*
        * test get category with china translation without empty category name translation
        */
        $filter = [
                    'with' => ['translations'],
                    'language_id' => $this->zhLang->language_id
                    ];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(2, $response_list->data->total_records);

    }

    public function testFilterLimited()
    {
        $this->setDefaultCategory();
        /*
        * test filter limited for list link to tenant category
        */
        $filter = [
                    'limited' => 'yes'
                  ];

        $response_list = $this->setRequestGetListCategory($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_list->code);
        $this->assertSame(4, $response_list->data->total_records);
        foreach ($response_list->data->records[0] as $key => $value) {
            $this->assertSame(true, in_array($key, ['category_id', 'category_name']));
            $this->assertSame(false, in_array($key, ['category_order', 'category_level']));
        }
    }
}