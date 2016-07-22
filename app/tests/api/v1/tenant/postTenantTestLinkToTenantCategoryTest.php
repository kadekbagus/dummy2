<?php
/**
 * PHP Unit Test for test link to tenant category
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postTenantTestLinkToTenantCategoryTest extends TestCase
{
    private $apiUrlNewCategory  = '/api/v1/category/new';
    private $apiUrlNewTenant    = '/api/v1/tenant/new';
    private $apiUrlUpdateTenant = '/api/v1/tenant/update';

    public function setUp()
    {
        parent::setUp();

        $this->userMallOwner = $userMallOwner = Factory::create('user_mall_owner');
        $this->apiKeyAdmin = Factory::create('apikey_super_admin');
        $this->apiKeyMallOwner = Factory::create('apikey_mall_owner', ['user_id' => $userMallOwner->user_id]);

        $this->enLang = $enLang = Factory::create('Language', ['name' => 'en']);
        $this->idLang = $idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = $zhLang = Factory::create('Language', ['name' => 'zh']);

        $this->mall = $mall = Factory::create('Mall', ['user_id' => $userMallOwner->user_id]);

        $this->mall_en_lang = Factory::create('MerchantLanguage', ['language_id' => $enLang->language_id, 'merchant_id' => $mall->merchant_id]);
        $this->mall_id_lang = Factory::create('MerchantLanguage', ['language_id' => $idLang->language_id, 'merchant_id' => $mall->merchant_id]);
        $this->mall_zh_lang = Factory::create('MerchantLanguage', ['language_id' => $zhLang->language_id, 'merchant_id' => $mall->merchant_id]);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostNewCategory($api_key, $api_secret_key, $new_category)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($new_category as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlNewCategory . '?' . http_build_query($_GET);

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

    public function setRequestPostNewTenant($api_key, $api_secret_key, $new_tenant)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($new_tenant as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlNewTenant . '?' . http_build_query($_GET);

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

    public function setRequestPostUpdateTenant($api_key, $api_secret_key, $update_tenant)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($update_tenant as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlUpdateTenant . '?' . http_build_query($_GET);

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

        $response = $this->setRequestPostNewCategory($this->apiKeyAdmin->api_key, $this->apiKeyAdmin->api_secret_key, $data);
        $this->category_health = $response->data;

        /*
        * category book store
        */
        $data = [
                'category_name'    => 'book store',
                'status'           => 'active',
                'default_language' => 'en'
                ];

        $response = $this->setRequestPostNewCategory($this->apiKeyAdmin->api_key, $this->apiKeyAdmin->api_secret_key, $data);
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

        $response = $this->setRequestPostNewCategory($this->apiKeyAdmin->api_key, $this->apiKeyAdmin->api_secret_key, $data);
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

        $response = $this->setRequestPostNewCategory($this->apiKeyAdmin->api_key, $this->apiKeyAdmin->api_secret_key, $data);
        $this->category_jewellery = $response->data;

        $_GET = [];
        $_POST = [];
    }

    public function testPostNewTenantWithLinkToTenantCategorySuccess()
    {
        $this->setDefaultCategory();

        $data_tenant = [
            'parent_id'           => $this->mall->merchant_id,
            'name'                => 'tenant 1',
            'external_object_id'  => 1,
            'object_type'         => 'service',
            'id_language_default' => $this->mall_en_lang->language_id,
            'status'              => 'active',
            'category_ids'         => $this->category_health->category_id
        ];

        $response_new_tenant = $this->setRequestPostNewTenant($this->apiKeyMallOwner->api_key, $this->apiKeyMallOwner->api_secret_key, $data_tenant);
        $this->assertSame(0, $response_new_tenant->code);
        $this->assertSame('success', $response_new_tenant->status);
        $this->assertSame(1, count($response_new_tenant->data->categories));
    }

    public function testPostUpdateTenantWithLinkToTenantCategorySuccess()
    {
        $this->setDefaultCategory();

        $tenant = Factory::create('Tenant');

        $data_tenant = [
            'retailer_id'  => $tenant->merchant_id,
            'category_ids' => [$this->category_health->category_id]
        ];

        $response_update_tenant = $this->setRequestPostUpdateTenant($this->apiKeyMallOwner->api_key, $this->apiKeyMallOwner->api_secret_key, $data_tenant);
        $this->assertSame(0, $response_update_tenant->code);
        $this->assertSame('success', $response_update_tenant->status);
        $this->assertSame(1, count($response_update_tenant->data->categories));
    }
}