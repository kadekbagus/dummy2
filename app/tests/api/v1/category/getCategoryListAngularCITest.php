<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\CategoryAPIController::getCategoryList() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getCategoryListAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/categories';

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

        $this->number_of_tenants_mall_1 = 3;
        $this->all_categories = array();
        $this->all_categories_mall_1 = array();
        $this->all_categories_mall_2 = array();

        $this->tenants_mall_1 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_1; $x++) {
            list($tenant, $category) = $this->createTenant($this->mall_1->merchant_id, FALSE);
            $this->tenants_mall_1[] = $tenant;
            $this->all_categories[] = $category;
            $this->all_categories_mall_1[] = $category;
        }

        $this->number_of_tenants_mall_2 = 2;
        $this->tenants_mall_2 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_2; $x++) {
            list($tenant, $category) = $this->createTenant($this->mall_2->merchant_id, TRUE);
            $this->tenants_mall_2[] = $tenant;
            $this->all_categories[] = $category;
            $this->all_categories_mall_2[] = $category;
        }

        $this->unlinkedCategory = Factory::create('Category');
        $this->all_categories[] = $this->unlinkedCategory;

        $_GET = [];
        $_POST = [];
    }

    private function createTenant($merchant_id, $withProfilingBadge)
    {
        $faker = Faker::create();
        $tenant = Factory::create('tenant_angular_ci', [
            'parent_id' => $merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);
        $category = Factory::create('Category', [
            'merchant_id' => $merchant_id
        ]);
        
        $tenant->categories()->save($category);

        return [$tenant, $category];
    }

    public function testOKGetListingCategoryMall1()
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
        // will only return category that attached to tenants of mall 1 (3)
        $this->assertSame(3, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $category) {
            $this->assertSame((string) $category->category_name, (string) $this->all_categories_mall_1[$key]->category_name);
        }
    }

    public function testOKGetListingCategoryMall2()
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
        // will only return category that attached to tenants of mall 2 (2)
        $this->assertSame(2, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $category) {
            $this->assertSame((string) $category->category_name, (string) $this->all_categories_mall_2[$key]->category_name);
        }
    }
}
