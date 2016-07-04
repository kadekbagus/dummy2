<?php


/**
 * Unit testing for Orbit\Controller\API\v1\Customer\CouponAPIController::getCouponItem() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getCouponItemAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/coupons/detail';

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

        $this->user_profiling = Factory::create('user_super_admin');
        $this->userdetail_profiling = Factory::create('UserDetail', [
            'user_id' => $this->user_profiling->user_id,
            'birthdate' => date('Y-m-d', strtotime('-36 year')),
            'gender' => 'm'
        ]);
        $this->user_profiling_apikey = Factory::create('apikey_super_admin', [
            'user_id' => $this->user_profiling->user_id
        ]);

        $this->user_profiling_gender = Factory::create('user_super_admin');
        $this->userdetail_profiling_gender = Factory::create('UserDetail', [
            'user_id' => $this->user_profiling_gender->user_id,
            'birthdate' => date('Y-m-d', strtotime('-36 year')),
            'gender' => 'f'
        ]);
        $this->user_profiling_gender_apikey = Factory::create('apikey_super_admin', [
            'user_id' => $this->user_profiling_gender->user_id
        ]);

        $this->mall_1 = Factory::create('Mall');
        $this->mall_2 = Factory::create('Mall');

        $this->mall_2_age_profiles = [
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => '0 - 14',
                'min_value'     => 0,
                'max_value'     => 14
            ]),
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => '15 - 24',
                'min_value'     => 14,
                'max_value'     => 25
            ]),
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => '25 - 34',
                'min_value'     => 24,
                'max_value'     => 34
            ]),
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => '35 - 44',
                'min_value'     => 35,
                'max_value'     => 44
            ]),
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => '45 - 54',
                'min_value'     => 45,
                'max_value'     => 54
            ]),
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => '55+',
                'min_value'     => 55,
                'max_value'     => 0
            ]),
            Factory::create('AgeRange', [
                'merchant_id'   => $this->mall_2->merchant_id,
                'range_name'    => 'Unknown',
                'min_value'     => 0,
                'max_value'     => 0
            ]),
        ];

        $this->number_of_tenants_mall_1 = 3;
        $this->tenants_mall_1 = array();
        $this->coupons_mall_1 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_1; $x++) {
            list($tenant, $coupon) = $this->createTenant($this->mall_1->merchant_id);
            $this->tenants_mall_1[] = $tenant;
            $this->coupons_mall_1[] = $coupon;
        }

        $this->number_of_tenants_mall_2 = 2;
        $this->tenants_mall_2 = array();
        $this->coupons_mall_2 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_2; $x++) {
            list($tenant, $coupon) = $this->createTenant($this->mall_2->merchant_id);
            $this->tenants_mall_2[] = $tenant;
            $this->coupons_mall_2[] = $coupon;
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
        
        $begin_date = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));

        $coupon = Factory::create('Coupon', [
            'begin_date' => $begin_date,
            'end_date' => $end_date,
            'promotion_type' => 'mall',
            'is_all_gender' => 'Y',
            'is_all_age' => 'Y',
            'coupon_validity_in_date' => $end_date,
        ]);
        $couponMerchant = Factory::create('coupon_link_tenant', [
            'promotion_id' => $coupon->promotion_id,
            'retailer_id' => $tenant->merchant_id,
            'object_type' => 'tenant'
        ]);
        $couponMerchantRedeem = Factory::create('coupon_link_redeem_tenant', [ // self redeem link to self
            'promotion_id' => $coupon->promotion_id,
            'retailer_id' => $tenant->merchant_id,
            'object_type' => 'tenant'
        ]);
        $issuedCoupon_user_profiling = Factory::create('IssuedCoupon', [
            'promotion_id' => $coupon->promotion_id,
            'user_id' => $this->user_profiling->user_id,
            'issued_coupon_code' => $tenant->merchant_id . $this->user_profiling->user_id . time()
        ]);
        $issuedCoupon_user_profiling_gender = Factory::create('IssuedCoupon', [
            'promotion_id' => $coupon->promotion_id,
            'user_id' => $this->user_profiling_gender->user_id,
            'issued_coupon_code' => $tenant->merchant_id . $this->user_profiling_gender->user_id . time()
        ]);

        $tenant->categories()->save($category);
        $tenant->coupons()->save($coupon);
        $tenant->redeemCoupons()->save($couponMerchantRedeem);
        $tenant->load('coupons');
        $tenant->issuedCoupon_user_profiling = $issuedCoupon_user_profiling;
        $tenant->issuedCoupon_user_profiling_gender = $issuedCoupon_user_profiling_gender;

        return [$tenant, $coupon];
    }

    public function testOKGetItemCouponObtainedOnly()
    {
        $_GET['apikey'] = $this->user_profiling_apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['coupon_id'] = $this->coupons_mall_1[0]->promotion_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->user_profiling_apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);

        // check all returned records
        $this->assertSame($this->coupons_mall_1[0]->promotion_id, (string) $response->data->promotion_id);
        $this->assertSame($this->coupons_mall_1[0]->promotion_name, (string) $response->data->promotion_name);
        $this->assertSame($this->coupons_mall_1[0]->description, (string) $response->data->description);
    }

    public function testOKGetItemCouponObtainedOnlyMall2()
    {
        $_GET['apikey'] = $this->user_profiling_apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;
        $_GET['coupon_id'] = $this->coupons_mall_2[0]->promotion_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->user_profiling_apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);

        // check all returned records
        $this->assertSame($this->coupons_mall_2[0]->promotion_id, (string) $response->data->promotion_id);
        $this->assertSame($this->coupons_mall_2[0]->promotion_name, (string) $response->data->promotion_name);
        $this->assertSame($this->coupons_mall_2[0]->description, (string) $response->data->description);
    }
}
