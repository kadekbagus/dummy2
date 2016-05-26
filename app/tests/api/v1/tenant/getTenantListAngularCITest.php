<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\TenantAPIController::getTenantList() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use \Tenant;
use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getTenantListAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/stores';

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

        $this->socMed = new SocialMedia();
        $this->socMed->social_media_code = 'facebook';
        $this->socMed->social_media_main_url = 'facebook.com';
        $this->socMed->save();

        $this->number_of_tenants_mall_1 = 3;
        $this->tenants_mall_1 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_1; $x++) {
            $this->tenants_mall_1[] = $this->createTenant($this->mall_1->merchant_id);
        }

        $this->number_of_tenants_mall_2 = 2;
        $this->tenants_mall_2 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_2; $x++) {
            $this->tenants_mall_2[] = $this->createTenant($this->mall_2->merchant_id);
        }

        $_GET = [];
        $_POST = [];
    }

    private function createTenant($merchant_id)
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
        
        $tenantSocMed = new MerchantSocialMedia();
        $tenantSocMed->social_media_id = $this->socMed->social_media_id;
        $tenantSocMed->merchant_id = $tenant->merchant_id;
        $tenantSocMed->social_media_uri = $faker->userName;
        $tenantSocMed->save();

        $news = Factory::create('News', [
            'object_type' => 'news'
        ]);
        $newsMerchant = Factory::create('NewsMerchant', [
            'news_id' => $news->news_id,
            'merchant_id' => $tenant->merchant_id,
            'object_type' => 'retailer'
        ]);
        $promotion = Factory::create('News', [
            'object_type' => 'promotion'
        ]);
        $promotionMerchant = Factory::create('NewsMerchant', [
            'news_id' => $promotion->news_id,
            'merchant_id' => $tenant->merchant_id,
            'object_type' => 'retailer'
        ]);
        $coupon = Factory::create('Coupon', [
            'promotion_type' => 'mall'
        ]);
        $couponMerchant = Factory::create('coupon_link_tenant', [
            'promotion_id' => $coupon->promotion_id,
            'retailer_id' => $tenant->merchant_id,
            'object_type' => 'tenant'
        ]);

        $tenant->categories()->save($category);
        $tenant->merchantSocialMedia()->save($tenantSocMed);
        $tenant->news()->save($news);
        $tenant->newsPromotions()->save($promotion);
        $tenant->coupons()->save($coupon);
        $tenant->load('news', 'newsPromotions', 'coupons');

        return $tenant;
    }

    public function testOKGetListingTenant()
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
        $this->assertSame((int) $this->number_of_tenants_mall_1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $item->merchant_id, (string) $this->tenants_mall_1[$key]->merchant_id);
            $this->assertSame((string) $item->name, (string) $this->tenants_mall_1[$key]->name);
            $this->assertSame((string) $item->floor, (string) $this->tenants_mall_1[$key]->floor);
            $this->assertSame((string) $item->unit, (string) $this->tenants_mall_1[$key]->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $category->category_name, (string) $this->tenants_mall_1[$key]->categories[$key2]->category_name);
            }
            $this->assertSame((string) $item->facebook_like_url, (string) $this->tenants_mall_1[$key]->merchantSocialMedia[0]->social_media_uri);
            $this->assertSame((string) $item->news_flag, (string) count($this->tenants_mall_1[$key]->news) > 0 ? 'true' : 'false', '>> Iteration: ' . $key);
            $this->assertSame((string) $item->promotion_flag, (string) count($this->tenants_mall_1[$key]->newsPromotions) > 0 ? 'true' : 'false');
            $this->assertSame((string) $item->coupon_flag, (string) count($this->tenants_mall_1[$key]->coupons) > 0 ? 'true' : 'false');
        }
    }

    public function testFAILGetListingTenantWithoutMallID()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

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
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $randomMallID;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame('The Mall ID you specified is not found', $response->message);
    }
}
