<?php
/**
 * Unit test for API TenantAPIController::postNewTenant with new type tenant (store and service)
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewTenantStoreAndServiceTest extends TestCase
{

    private $apiUrl = '/api/v1/tenant/new';

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

    public function makeRequest($api_key, $api_secret_key, $data)
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

    public function testFailTryCreateNewsWithoutFillTheField()
    {
        $faker = Faker::create();

        //User mall owner 1 try to create new tenant without fill the name
        $data = [];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The name field is required", $response->message);
        $this->assertSame(null, $response->data);

        //User mall owner 1 try to create new tenant without fill the external object id
        $data = ['name' => $faker->company];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The external object id field is required", $response->message);
        $this->assertSame(null, $response->data);

        //User mall owner 1 try to create new tenant without fill the object_type (tenant or service)
        $data = [
            'name' => $faker->company,
            'external_object_id' => 0,
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The object type field is required", $response->message);
        $this->assertSame(null, $response->data);

        //User mall owner 1 try to create new tenant without fill the id_language_default
        $data = [
            'name' => $faker->company,
            'external_object_id' => 0,
            'object_type' => 'tenant',
            'id_language_default' => 11222,
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The language default you specified is not found", $response->message);
        $this->assertSame(null, $response->data);
    }

    public function testFailValidationObjectType()
    {
        $faker = Faker::create();

        //User mall owner 1 try to create new tenant with false object_type, object_type must be 'tenant' or 'service'
        $data = [
            'name' => $faker->company,
            'external_object_id' => 0,
            'object_type' => 'mall',
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Tenant type you specified is not found : the valid values are: tenant, service", $response->message);
        $this->assertSame(null, $response->data);
    }

    public function testOkValidationObjectType()
    {
        $faker = Faker::create();

        // User mall owner 1 create tenant with tenant type tenant (store)
        $tenantStoreName = $faker->company;
        $data = [
            'name' => $tenantStoreName,
            'external_object_id' => 0,
            'object_type' => 'tenant',
            'id_language_default' => $this->mall_en_lang->language_id,
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame('tenant', $response->data->object_type);
        $this->assertSame($tenantStoreName, $response->data->name);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);

        // User mall oener 1 create tenant with tenant type tenant service
        $tenantServiceName = $faker->company;
        $data = [
            'name' => $tenantServiceName,
            'external_object_id' => 0,
            'object_type' => 'service',
            'id_language_default' => $this->mall_en_lang->language_id,
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame('service', $response->data->object_type);
        $this->assertSame($tenantServiceName, $response->data->name);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("Request OK", $response->message);
    }

    public function testValidationSomeFieldForTenantService()
    {
        $faker = Faker::create();

        // For tenant service cannot save the some field like : verification number, url, phone. So we must validate the field.

        // Validation url
        $tenantServiceName = $faker->company;
        $data = [
            'name' => $tenantServiceName,
            'external_object_id' => 0,
            'object_type' => 'service',
            'id_language_default' => $this->mall_en_lang->language_id,
            'url' => 'google.com',
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("There is any value for tenant only, cannot save as a service", $response->message);


        // Validation phone
        $tenantServiceName = $faker->company;
        $data = [
            'name' => $tenantServiceName,
            'external_object_id' => 0,
            'object_type' => 'service',
            'id_language_default' => $this->mall_en_lang->language_id,
            'phone' => '+8165845474',
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("There is any value for tenant only, cannot save as a service", $response->message);


        // Validation masterbox_number (verification number)
        $tenantServiceName = $faker->company;
        $data = [
            'name' => $tenantServiceName,
            'external_object_id' => 0,
            'object_type' => 'service',
            'id_language_default' => $this->mall_en_lang->language_id,
            'masterbox_number' => '87878787',
        ];
        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("There is any value for tenant only, cannot save as a service", $response->message);
    }

    public function testFieldsStoredTenantService()
    {
        $faker = Faker::create();

        $data['merchant_id'] = $this->mall_1->merchant_id;
        $data['current_mall'] = $this->mall_1->merchant_id;
        $data['id_language_default'] = $this->mall_en_lang->language_id;

        $data['floor'] = 'LG';
        $data['unit'] = '10';
        $data['external_object_id'] = '0';
        $data['status'] = 'active';
        $data['name'] = $faker->company;
        $data['object_type'] = 'service';
        // $data['category_ids'][] = $faker->;
        // $data['category_ids'][] = $faker->;
        // $data['category_ids'][] = $faker->;
        $data['keywords'][] = 'tenant';
        $data['keywords'][] = 'service';
        $data['keywords'][] = 'phone';
        $data['translations'] = '{"' .$this->mall_id_lang->language_id . '":{"description":"Tenant service Indonesia"},"' .$this->mall_en_lang->language_id . '":{"description":"Tenant service English"},"' .$this->mall_zh_lang->language_id . '":{"description":"Tenant service China"}}';

        $count_before = TenantStoreAndService::excludeDeleted()->count();

        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = TenantStoreAndService::excludeDeleted()->count();
        $this->assertSame($count_before + 1, $count_after);

        // Check value tenant
        $tenant = TenantStoreAndService::find($response->data->merchant_id);

        $fields = array(
            'floor',
            'unit',
            'external_object_id',
            'status',
            'name',
            'object_type',
        );

        foreach ($fields as $key => $field) {
            $this->assertSame($data[$field], $tenant->$field);
        }

        // Check keyword
        foreach ($response->data->keywords as $key => $val) {
            $this->assertSame($val->keyword, $data['keywords'][$key]);
        }
    }

    public function testFieldsStoredTenantStore()
    {
        $faker = Faker::create();

        $data['merchant_id'] = $this->mall_1->merchant_id;
        $data['current_mall'] = $this->mall_1->merchant_id;
        $data['id_language_default'] = $this->mall_en_lang->language_id;

        $data['floor'] = 'LG';
        $data['unit'] = '10';
        $data['external_object_id'] = '0';
        $data['status'] = 'active';
        $data['name'] = $faker->company;
        $data['object_type'] = 'tenant';
        $data['phone'] = '+85794076666';
        $data['url'] = 'gotomalls.com';
        $data['masterbox_number'] = '1111155555';
        // $data['category_ids'][] = $faker->;
        // $data['category_ids'][] = $faker->;
        // $data['category_ids'][] = $faker->;
        $data['keywords'][] = 'tenant';
        $data['keywords'][] = 'store';
        $data['keywords'][] = 'phone';
        $data['translations'] = '{"' .$this->mall_id_lang->language_id . '":{"description":"Tenant tenant Indonesia"},"' .$this->mall_en_lang->language_id . '":{"description":"Tenant tenant English"},"' .$this->mall_zh_lang->language_id . '":{"description":"Tenant tenant China"}}';

        $count_before = TenantStoreAndService::excludeDeleted()->count();

        $response = $this->makeRequest($this->apikey_user_mall_owner_1->api_key, $this->apikey_user_mall_owner_1->api_secret_key, $data);

        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertResponseStatus(200);

        $count_after = TenantStoreAndService::excludeDeleted()->count();
        $this->assertSame($count_before + 1, $count_after);

        // Check value tenant
        $tenant = TenantStoreAndService::find($response->data->merchant_id);

        $fields = array(
            'floor',
            'unit',
            'external_object_id',
            'status',
            'name',
            'object_type',
            'phone',
            'url',
            'masterbox_number',
        );

        foreach ($fields as $key => $field) {
            $this->assertSame($data[$field], $tenant->$field);
        }

        // Check keyword
        foreach ($response->data->keywords as $key => $val) {
            $this->assertSame($val->keyword, $data['keywords'][$key]);
        }
    }

    public function testOkDuplicateTenantname()
    {
    }

}