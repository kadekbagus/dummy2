<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\ObjectAPIController::getObjectList() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getObjectListAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/floors';

    public function setUp()
    {
        parent::setUp();

        $this->user = Factory::create('user_super_admin');
        $this->userdetail = Factory::create('UserDetail', [
            'user_id' => $this->user->user_id
        ]);
        $this->apikey = Factory::create('apikey_super_admin', [
            'user_id' => $this->user->user_id
        ]);

        $this->mall_1 = Factory::create('Mall');
        $this->mall_2 = Factory::create('Mall');

        $this->all_floor_mall_1 = array();
        $this->all_floor_mall_2 = array();
        $this->number_of_objects_mall_1 = 6;
        $this->number_of_objects_mall_2 = 4;

        for($x = 0; $x < $this->number_of_objects_mall_1; $x++) {
            $floor = $this->createFloorObjects($this->mall_1->merchant_id, $x);
            $this->all_floor_mall_1[] = $floor->object_name;
        }

        for($x = 0; $x < $this->number_of_objects_mall_2; $x++) {
            $floor = $this->createFloorObjects($this->mall_2->merchant_id, $x);
            $this->all_floor_mall_2[] = $floor->object_name;
        }

        $this->number_of_tenants_mall_1 = 3;
        $this->tenants_mall_1 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_1; $x++) {
            // plot unique object(floor) to each tenant so the number of the result with this mall id 
            // will result in 3 records
            $floor = $this->all_floor_mall_1[$x];
            $this->tenants_mall_1[] = $this->createTenant($this->mall_1->merchant_id, $floor);
        }

        $this->number_of_tenants_mall_2 = 2;
        $this->tenants_mall_2 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_2; $x++) {
            // plot single object(floor) to each tenant so the number of the result with this mall id 
            // will result in 1 records
            $floor = $this->all_floor_mall_2[0];
            $this->tenants_mall_2[] = $this->createTenant($this->mall_2->merchant_id, $floor);
        }

        $_GET = [];
        $_POST = [];
    }

    private function createTenant($merchant_id, $floor)
    {
        $faker = Faker::create();
        $tenant = Factory::create('tenant_angular_ci', [
            'parent_id' => $merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
            'floor' => $floor
        ]);

        return $tenant;
    }

    private function createFloorObjects($merchant_id, $idx)
    {
        $faker = Faker::create();
        $obj = Factory::create('Object', [
            'merchant_id' => $merchant_id,
            'object_type' => 'floor',
            'object_order' => $idx,
        ]);

        return $obj;
    }

    public function testOKGetListingFloorMall1()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(6, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $object) {
            $this->assertSame(TRUE, in_array((string) $object->object_name, $this->all_floor_mall_1));
        }
    }

    public function testOKGetListingFloorMall2()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(4, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $object) {
            $this->assertSame(TRUE, in_array((string) $object->object_name, $this->all_floor_mall_2));
        }
    }
}
