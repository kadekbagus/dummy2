<?php

/**
 * Unit testing for Controller\src\Orbit\Controller\API\v1\Customer\ServiceAPIController::getServiceItem() method.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */

use \Tenant;
use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getServiceDetailAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/services/detail';

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
        $category = Factory::create('Category', [
            'merchant_id' => $merchant_id
        ]);

        $tenant->categories()->save($category);

        return $tenant;
    }

    public function testOKGetAllTenantListingMall1()
    {
        $key = 0;

        // Get search for user_mall_owner_1
        $_GET['apikey'] = $this->apikey_user_super_admin->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['service_id'] = $this->tenants_service_mall_1[$key]->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey_user_super_admin->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame($response->data->merchant_id, $this->tenants_service_mall_1[$key]->merchant_id);
        $this->assertSame($response->data->name, $this->tenants_service_mall_1[$key]->name);
        $this->assertSame((int) $response->data->floor, (int) $this->tenants_service_mall_1[$key]->floor);
        $this->assertSame((int) $response->data->unit, (int) $this->tenants_service_mall_1[$key]->unit);

        $this->assertSame(0, (int) $response->code);
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

}