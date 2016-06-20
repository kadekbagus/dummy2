<?php

/**
 * Unit testing for Controller\src\Orbit\Controller\API\v1\Customer\ServiceAPIController::getTenantList() method.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */

use \Tenant;
use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getServiceListAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/services';

    public function setUp()
    {
        parent::setUp();

        // Create user super admin
        $this->user_super_admin = Factory::create('user_super_admin');
        $this->userdetail_user_super_admin = Factory::create('UserDetail', ['user_id' => $this->user_super_admin->user_id]);
        $this->apikey_user_super_admin = Factory::create('apikey_super_admin', ['user_id' => $this->user_super_admin->user_id]);

        // Create user mall owner
        $role = Factory::create('role_mall_owner');

        $this->mall_1 = Factory::create('Mall');
        $this->mall_2 = Factory::create('Mall');

        // Mall 1 : Create 3 tenant service
        $this->total_tenant_service_mall_1 = 3;

        $this->tenants_service_mall_1 = array();
        for($x = 0; $x < $this->total_tenant_service_mall_1; $x++) {
            $this->tenants_service_mall_1[] = $this->createTenantService($this->mall_1->merchant_id);
        }

        // Mall 2 : Create 2 tenant service
        $this->total_tenant_service_mall_2 = 2;

        $this->tenants_service_mall_2 = array();
        for($x = 0; $x < $this->total_tenant_service_mall_2; $x++) {
            $this->tenants_service_mall_2[] = $this->createTenantService($this->mall_2->merchant_id);
        }

        $_GET = [];
        $_POST = [];
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
        $cat = Factory::create('Category', [
            'merchant_id' => $merchant_id
        ]);

        $tenant->categories()->save($cat);

        return $tenant;
    }

    public function testOKGetAllTenantListingMall1()
    {
        // Get search for user_mall_owner_1
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['parent_id'] = $this->mall_1->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        //Check all response value
        foreach ($response->data->records as $key => $val) {
            $this->assertSame($val->merchant_id, $this->tenants_service_mall_1[$key]->merchant_id);
            $this->assertSame($val->name, $this->tenants_service_mall_1[$key]->name);
            $this->assertSame((int) $val->floor, (int) $this->tenants_service_mall_1[$key]->floor);
            $this->assertSame((int) $val->unit, (int) $this->tenants_service_mall_1[$key]->unit);
            foreach ($val->categories as $key_cat => $cat) {
                $this->assertSame((string) $cat->category_name, (string) $this->tenants_service_mall_1[$key]->categories[$key_cat]->category_name);
            }
        }

        $this->assertSame(0, (int) $response->code);
        $this->assertSame((int) $this->total_tenant_service_mall_1, (int) $response->data->returned_records);
    }

    public function testOKGetAllTenantListingMall2()
    {
        // Get search for user_mall_owner_2
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;
        $_GET['parent_id'] = $this->mall_2->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        //Check all value
        foreach ($response->data->records as $key => $val) {
            $this->assertSame($val->merchant_id, $this->tenants_service_mall_2[$key]->merchant_id);
            $this->assertSame($val->name, $this->tenants_service_mall_2[$key]->name);
            $this->assertSame((int) $val->floor, (int) $this->tenants_service_mall_2[$key]->floor);
            $this->assertSame((int) $val->unit, (int) $this->tenants_service_mall_2[$key]->unit);
        }

        $this->assertSame(0, (int) $response->code);
        $this->assertSame((int) $this->total_tenant_service_mall_2, (int) $response->data->returned_records);
    }

    public function testFAILGetListingTenantWithoutMallID()
    {
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame('The mall id field is required', $response->message);
    }

    public function testFAILGetListingTenantWithNonExistentMallID()
    {
        $faker = Faker::create();
        $randomMallID = $faker->lexify('??????'); // use smaller digit to avoid same id generated by uuid
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $randomMallID;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame('The Mall ID you specified is not found', $response->message);
    }

    public function testOKGetListingTenantFilteredByCategoryID()
    {
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['category_id'] = $this->tenants_service_mall_1[0]->categories[0]->category_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_service_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_service_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
        }
    }

    public function testOKGetListingTenantFilteredByFloorString()
    {
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['floor'] = $this->tenants_service_mall_1[0]->floor;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_service_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_service_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
        }
    }

    public function testOKGetListingTenantFilteredByObjectType()
    {
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['object_type'] = $this->tenants_service_mall_1[0]->object_type;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(3, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $item->merchant_id, (string) $this->tenants_service_mall_1[$key]->merchant_id);
            $this->assertSame((string) $item->name, (string) $this->tenants_service_mall_1[$key]->name);
            $this->assertSame((string) $item->floor, (string) $this->tenants_service_mall_1[$key]->floor);
            $this->assertSame((string) $item->unit, (string) $this->tenants_service_mall_1[$key]->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $category->category_name, (string) $this->tenants_service_mall_1[$key]->categories[$key2]->category_name);
            }
        }
    }


    public function testOKGetListingTenantFilteredByKeywordString()
    {
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['keyword'] = substr($this->tenants_service_mall_1[0]->name, 0, -1);

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_service_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_service_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_service_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
        }
    }

}