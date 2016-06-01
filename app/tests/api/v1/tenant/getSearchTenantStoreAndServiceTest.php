<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\TenantAPIController::getTenantList() method.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 *
 *
 */

use \Tenant;
use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getSearchTenantStoreAndServiceTest extends TestCase
{
    protected $apiUrl = '/api/v1/tenant/search';

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

        $this->user_mall_owner_2 = Factory::create('User', ['user_role_id' => $role->role_id]);
        $this->apikey_user_mall_owner_2 = Factory::create('Apikey', ['user_id' => $this->user_mall_owner_2->user_id]);

        $this->mall_1 = Factory::create('Mall', ['user_id' => $this->user_mall_owner_1->user_id]);
        $this->mall_2 = Factory::create('Mall', ['user_id' => $this->user_mall_owner_2->user_id]);

        $this->socMed = new SocialMedia();
        $this->socMed->social_media_code = 'facebook';
        $this->socMed->social_media_main_url = 'facebook.com';
        $this->socMed->save();

        // Mall 1 : Create 4 tenant store and 3 tenant service, So total tenant in mall_1 is 7
        $this->total_tenant_store_mall_1 = 4;
        $this->total_tenant_service_mall_1 = 3;
        $this->total_tenant_stote_and_service_mall_1 = $this->total_tenant_store_mall_1 + $this->total_tenant_service_mall_1;

        $this->tenants_store_mall_1 = array();
        for($x = 0; $x < $this->total_tenant_store_mall_1; $x++) {
            $this->tenants_store_mall_1[] = $this->createTenantStore($this->mall_1->merchant_id);
        }
        $this->tenants_service_mall_1 = array();
        for($x = 0; $x < $this->total_tenant_service_mall_1; $x++) {
            $this->tenants_service_mall_1[] = $this->createTenantService($this->mall_1->merchant_id);
        }

        // Mall 2 : Create 3 tenant store and 2 tenant service, So total tenant in mall_2 is 5
        $this->total_tenant_store_mall_2 = 3;
        $this->total_tenant_service_mall_2 = 2;
        $this->total_tenant_stote_and_service_mall_2 = $this->total_tenant_store_mall_2 + $this->total_tenant_service_mall_2;

        $this->tenants_store_mall_2 = array();
        for($x = 0; $x < $this->total_tenant_store_mall_2; $x++) {
            $this->tenants_store_mall_2[] = $this->createTenantStore($this->mall_2->merchant_id);
        }

        $this->tenants_service_mall_2 = array();
        for($x = 0; $x < $this->total_tenant_service_mall_2; $x++) {
            $this->tenants_service_mall_2[] = $this->createTenantService($this->mall_2->merchant_id);
        }

        $_GET = [];
        $_POST = [];
    }

    private function createTenantStore($merchant_id)
    {
        $faker = Faker::create();
        $tenant = Factory::create('tenant_store', [
            'parent_id' => $merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);
        $category = Factory::create('Category', [
            'merchant_id' => $merchant_id
        ]);

        $tenantSocMed = new MerchantSocialMedia();
        $tenantSocMed->social_media_id = $this->socMed->social_media_id;
        $tenantSocMed->merchant_id = $tenant->merchant_id;
        $tenantSocMed->social_media_uri = $faker->userName;
        $tenantSocMed->save();

        $tenant->categories()->save($category);
        $tenant->merchantSocialMedia()->save($tenantSocMed);

        return $tenant;
    }

    private function createTenantService($merchant_id)
    {
        $faker = Faker::create();
        $tenant = Factory::create('tenant_service', [
            'parent_id' => $merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);
        $category = Factory::create('Category', [
            'merchant_id' => $merchant_id
        ]);

        $tenant->categories()->save($category);

        return $tenant;
    }

    public function testOKGetAllTenantListingUser1()
    {
        // Get search for user_mall_owner_1
        $_GET['apikey'] = $this->apikey_user_mall_owner_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['parent_id'] = $this->mall_1->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_mall_owner_1->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame((int) $this->total_tenant_stote_and_service_mall_1, (int) $response->data->returned_records);
    }

    public function testOKGetAllTenantListingUser2()
    {
        // Get search for user_mall_owner_1
        $_GET['apikey'] = $this->apikey_user_mall_owner_2->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;
        $_GET['parent_id'] = $this->mall_2->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_mall_owner_2->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame((int) $this->total_tenant_stote_and_service_mall_2, (int) $response->data->returned_records);
    }

    public function testOKGetListingTenantStoreOnly()
    {
        // Get search tenant store for user_mall_owner_1
        $_GET['apikey'] = $this->apikey_user_mall_owner_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['parent_id'] = $this->mall_1->merchant_id;
        $_GET['object_type'][] = 'tenant';

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_mall_owner_1->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame((int) $this->total_tenant_store_mall_1, (int) $response->data->returned_records);

        // check all returned records
        // foreach ($response->data->records as $key => $item) {
        //     $this->assertSame((string) $item->merchant_id, (string) $this->tenants_store_mall_1[$key]->merchant_id);
        //     $this->assertSame((string) $item->name, (string) $this->tenants_store_mall_1[$key]->name);
        //     $this->assertSame((string) $item->floor, (string) $this->tenants_store_mall_1[$key]->floor);
        //     $this->assertSame((string) $item->unit, (string) $this->tenants_store_mall_1[$key]->unit);
        //     // foreach ($item->categories as $key2 => $category) {
        //     //     $this->assertSame((string) $category->category_name, (string) $this->tenants_store_mall_1[$key]->categories[$key2]->category_name);
        //     // }
        //     $this->assertSame((string) $item->facebook_like_url, (string) $this->tenants_store_mall_1[$key]->merchantSocialMedia[0]->social_media_uri);
        // }
    }

    public function testOKGetListingTenantServiceOnly()
    {
        // Get search tenant service for user_mall_owner_1
        $_GET['apikey'] = $this->apikey_user_mall_owner_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['parent_id'] = $this->mall_1->merchant_id;
        $_GET['object_type'][] = 'service';

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_mall_owner_1->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame((int) $this->total_tenant_service_mall_1, (int) $response->data->returned_records);

        // check all returned records
        // foreach ($response->data->records as $key => $item) {
        //     $this->assertSame((string) $item->merchant_id, (string) $this->tenants_service_mall_1[$key]->merchant_id);
        //     $this->assertSame((string) $item->name, (string) $this->tenants_service_mall_1[$key]->name);
        //     $this->assertSame((string) $item->floor, (string) $this->tenants_service_mall_1[$key]->floor);
        //     $this->assertSame((string) $item->unit, (string) $this->tenants_service_mall_1[$key]->unit);
        //     // foreach ($item->categories as $key2 => $category) {
        //     //     $this->assertSame((string) $category->category_name, (string) $this->tenants_service_mall_1[$key]->categories[$key2]->category_name);
        //     // }
        //     $this->assertSame((string) $item->facebook_like_url, (string) $this->tenants_service_mall_1[$key]->merchantSocialMedia[0]->social_media_uri);
        // }
    }

    public function testOKGetListingSearchingByTenantType()
    {
    }

}
