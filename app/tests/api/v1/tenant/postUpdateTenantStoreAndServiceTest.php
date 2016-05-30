<?php
/**
 * Unit test for API TenantAPIController::postUpdateTenant with new type tenant (store and service)
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateTenantStoreAndServiceTest extends TestCase
{

    private $apiUrl = '/api/v1/tenant/update';

    public function setUp()
    {
        parent::setUp();

        // Create user super admin
        $this->user_super_admin = Factory::create('user_super_admin');
        $this->userdetail_user_super_admin = Factory::create('UserDetail', ['user_id' => $this->user_super_admin->user_id]);
        $this->apikey_user_super_admin = Factory::create('apikey_super_admin', ['user_id' => $this->user_super_admin->user_id]);

        // Create user mall owner
        $role = Factory::create('role_mall_owner');
        $this->user_mall_owner_1 = Factory::create('User', ['user_role_id' => $role->role_id]);
        $this->apikey_user_mall_owner_1 = Factory::create('Apikey', ['user_id' => $this->user_mall_owner_1->user_id]);

        // Create mall
        $this->mall_1 = Factory::create('Mall', ['user_id' => $this->user_mall_owner_1->user_id]);

        // Create master language
        $this->enLang = Factory::create('Language', ['name' => 'en']);
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);

        // Create merchant language
        $this->mall_en_lang = Factory::create('MerchantLanguage', ['language_id' => $this->enLang->language_id, 'merchant_id' => $this->mall_1->merchant_id]);
        $this->mall_id_lang = Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $this->mall_1->merchant_id]);
        $this->mall_zh_lang = Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $this->mall_1->merchant_id]);
    }

    public function makeRequestAdd($api_key, $api_secret_key, $data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = '/api/v1/tenant/new' . '?' . http_build_query($_GET);

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


    public function makeRequestUpdate($api_key, $api_secret_key, $data)
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


    public function testOkUpdateTenantService()
    {
        $faker = Faker::create();

        // Insert data
        $data['merchant_id'] = $this->mall_1->merchant_id;
        $data['current_mall'] = $this->mall_1->merchant_id;
        $data['id_language_default'] = $this->mall_en_lang->language_id;
        $data['floor'] = 'LG';
        $data['unit'] = '10';
        $data['external_object_id'] = '0';
        $data['status'] = 'active';
        $data['name'] = $faker->company;
        $data['object_type'] = 'service';
        $data['keywords'][] = 'tenant';
        $data['keywords'][] = 'service';
        $data['keywords'][] = 'phone';
        $data['translations'] = '{"' .$this->mall_id_lang->language_id . '":{"description":"Tenant service Indonesia"},"' .$this->mall_en_lang->language_id . '":{"description":"Tenant service English"},"' .$this->mall_zh_lang->language_id . '":{"description":"Tenant service China"}}';

        $response = $this->makeRequestAdd($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $retailer_id = $response->data->merchant_id;

        // Update data
        $dataUpdate['merchant_id'] = $this->mall_1->merchant_id;
        $dataUpdate['current_mall'] = $this->mall_1->merchant_id;
        $dataUpdate['retailer_id'] = $retailer_id;
        $dataUpdate['floor'] = 'L1';
        $dataUpdate['unit'] = '11';
        $dataUpdate['external_object_id'] = '0';
        $dataUpdate['status'] = 'active';
        $dataUpdate['object_type'] = 'service';
        $dataUpdate['keywords'][] = 'tenant_update';
        $dataUpdate['keywords'][] = 'service_update';
        $dataUpdate['keywords'][] = 'phone_update';
        $dataUpdate['translations'] = '{"' .$this->mall_id_lang->language_id . '":{"description":"Tenant service Indonesia update"},"' .$this->mall_en_lang->language_id . '":{"description":"Tenant service English update"},"' .$this->mall_zh_lang->language_id . '":{"description":"Tenant service China update"}}';

        $response = $this->makeRequestUpdate($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $dataUpdate);

        $tenant = TenantStoreAndService::find($retailer_id);

        // Check all updated
        $fields = array(
            'floor',
            'unit',
            'external_object_id',
            'status',
            'object_type',
        );

        foreach ($fields as $key => $field) {
            $this->assertSame($dataUpdate[$field], $tenant->$field);
        }

        // Check keyword
        foreach ($response->data->keywords as $key => $val) {
            $this->assertSame($val->keyword, $dataUpdate['keywords'][$key]);
        }
    }

    public function testOkUpdateTenantStore()
    {
        $faker = Faker::create();

        // Insert data
        $data['merchant_id'] = $this->mall_1->merchant_id;
        $data['current_mall'] = $this->mall_1->merchant_id;
        $data['id_language_default'] = $this->mall_en_lang->language_id;
        $data['floor'] = 'LG';
        $data['unit'] = '10';
        $data['external_object_id'] = '0';
        $data['status'] = 'active';
        $data['url'] = 'gotomalls.com';
        $data['phone'] = '+123456789';
        $data['masterbox_number'] = '1111155555';
        $data['name'] = $faker->company;
        $data['object_type'] = 'tenant';
        $data['keywords'][] = 'tenant';
        $data['keywords'][] = 'tenant';
        $data['keywords'][] = 'phone';
        $data['translations'] = '{"' .$this->mall_id_lang->language_id . '":{"description":"Tenant tenant Indonesia"},"' .$this->mall_en_lang->language_id . '":{"description":"Tenant tenant English"},"' .$this->mall_zh_lang->language_id . '":{"description":"Tenant tenant China"}}';

        $response = $this->makeRequestAdd($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $retailer_id = $response->data->merchant_id;

        // Update data
        $dataUpdate['merchant_id'] = $this->mall_1->merchant_id;
        $dataUpdate['current_mall'] = $this->mall_1->merchant_id;
        $dataUpdate['retailer_id'] = $retailer_id;
        $dataUpdate['floor'] = 'L1';
        $dataUpdate['unit'] = '11';
        $dataUpdate['external_object_id'] = '0';
        $dataUpdate['status'] = 'active';
        $dataUpdate['url'] = 'gotomallsok.com';
        $dataUpdate['phone'] = '+123456666';
        $dataUpdate['masterbox_number'] = '1111155555666';
        $dataUpdate['object_type'] = 'tenant';
        $dataUpdate['keywords'][] = 'tenant_update';
        $dataUpdate['keywords'][] = 'store_update';
        $dataUpdate['keywords'][] = 'phone_update';
        $dataUpdate['translations'] = '{"' .$this->mall_id_lang->language_id . '":{"description":"Tenant store Indonesia update"},"' .$this->mall_en_lang->language_id . '":{"description":"Tenant store English update"},"' .$this->mall_zh_lang->language_id . '":{"description":"Tenant store China update"}}';

        $response = $this->makeRequestUpdate($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $dataUpdate);

        $tenant = TenantStoreAndService::find($retailer_id);

        // Check all updated
        $fields = array(
            'floor',
            'unit',
            'external_object_id',
            'status',
            'object_type',
            'url',
            'phone',
            'masterbox_number',
        );

        foreach ($fields as $key => $field) {
            $this->assertSame($dataUpdate[$field], $tenant->$field);
        }

        // Check keyword
        foreach ($response->data->keywords as $key => $val) {
            $this->assertSame($val->keyword, $dataUpdate['keywords'][$key]);
        }
    }

}