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

        $this->socMed = new SocialMedia();
        $this->socMed->social_media_code = 'facebook';
        $this->socMed->social_media_main_url = 'facebook.com';
        $this->socMed->save();

        $this->number_of_tenants_mall_1 = 3;
        $this->tenants_mall_1 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_1; $x++) {
            $this->tenants_mall_1[] = $this->createTenant($this->mall_1->merchant_id, FALSE);
        }

        $this->number_of_tenants_mall_2 = 2;
        $this->tenants_mall_2 = array();
        for($x = 0; $x < $this->number_of_tenants_mall_2; $x++) {
            $this->tenants_mall_2[] = $this->createTenant($this->mall_2->merchant_id, TRUE);
        }

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
        
        $tenantSocMed = new MerchantSocialMedia();
        $tenantSocMed->social_media_id = $this->socMed->social_media_id;
        $tenantSocMed->merchant_id = $tenant->merchant_id;
        $tenantSocMed->social_media_uri = $faker->userName;
        $tenantSocMed->save();
        $begin_date = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
        if ($withProfilingBadge) {
            $news = Factory::create('News', [
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'object_type' => 'news',
                'is_all_gender' => 'N',
                'is_all_age' => 'N',
            ]);
            $newsMerchant = Factory::create('NewsMerchant', [
                'news_id' => $news->news_id,
                'merchant_id' => $tenant->merchant_id,
                'object_type' => 'retailer'
            ]);
            $newsAgeProfile = Factory::create('CampaignAge', [
                'campaign_type' => 'news',
                'campaign_id'   => $news->news_id,
                'age_range_id'  => $this->mall_2_age_profiles[3]->age_range_id // select this range to have it displayed
            ]);
            $newsGenderProfile = Factory::create('CampaignGender', [
                'campaign_type' => 'news',
                'campaign_id'   => $news->news_id,
                'gender_value'  => 'M' // select this gender to have it displayed
            ]);

            $promotion = Factory::create('News', [
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'object_type' => 'promotion',
                'is_all_gender' => 'N',
                'is_all_age' => 'N',
            ]);
            $promotionMerchant = Factory::create('NewsMerchant', [
                'news_id' => $promotion->news_id,
                'merchant_id' => $tenant->merchant_id,
                'object_type' => 'retailer'
            ]);
            $promotionAgeProfile = Factory::create('CampaignAge', [
                'campaign_type' => 'promotion',
                'campaign_id'   => $promotion->news_id,
                'age_range_id'  => $this->mall_2_age_profiles[3]->age_range_id // select this range to have it displayed
            ]);
            $promotionGenderProfile = Factory::create('CampaignGender', [
                'campaign_type' => 'promotion',
                'campaign_id'   => $promotion->news_id,
                'gender_value'  => 'M' // select this gender to have it displayed
            ]);

            $coupon = Factory::create('Coupon', [
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'promotion_type' => 'mall',
                'is_all_gender' => 'N',
                'is_all_age' => 'N',
                'is_all_employee' => 'Y', // set all employee to mall 2
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
            $couponAgeProfile = Factory::create('CampaignAge', [
                'campaign_type' => 'coupon',
                'campaign_id'   => $coupon->promotion_id,
                'age_range_id'  => $this->mall_2_age_profiles[3]->age_range_id // select this range to have it displayed
            ]);
            $couponGenderProfile = Factory::create('CampaignGender', [
                'campaign_type' => 'coupon',
                'campaign_id'   => $coupon->promotion_id,
                'gender_value'  => 'M' // select this gender to have it displayed
            ]);

            $issued_coupon = Factory::create('IssuedCoupon', [
                'promotion_id' => $coupon->promotion_id,
                'user_id' => $this->user->user_id,
                'issued_coupon_code' => $tenant->merchant_id . $this->user->user_id . time()
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
        } else {
            $news = Factory::create('News', [
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'object_type' => 'news',
                'is_all_gender' => 'Y',
                'is_all_age' => 'Y',
            ]);
            $newsMerchant = Factory::create('NewsMerchant', [
                'news_id' => $news->news_id,
                'merchant_id' => $tenant->merchant_id,
                'object_type' => 'retailer',
            ]);
            $promotion = Factory::create('News', [
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'object_type' => 'promotion',
                'is_all_gender' => 'Y',
                'is_all_age' => 'Y',
            ]);
            $promotionMerchant = Factory::create('NewsMerchant', [
                'news_id' => $promotion->news_id,
                'merchant_id' => $tenant->merchant_id,
                'object_type' => 'retailer'
            ]);
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
            $issued_coupon = Factory::create('IssuedCoupon', [
                'promotion_id' => $coupon->promotion_id,
                'user_id' => $this->user->user_id,
                'issued_coupon_code' => $tenant->merchant_id . $this->user->user_id . time()
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
        }

        $tenant->categories()->save($category);
        $tenant->merchantSocialMedia()->save($tenantSocMed);
        $tenant->news()->save($news);
        $tenant->newsPromotions()->save($promotion);
        $tenant->coupons()->save($coupon);
        $tenant->redeemCoupons()->save($couponMerchantRedeem);
        $tenant->load('news', 'newsPromotions', 'coupons');
        $tenant->issued_coupon = $issued_coupon;
        $tenant->issuedCoupon_user_profiling = $issuedCoupon_user_profiling;
        $tenant->issuedCoupon_user_profiling_gender = $issuedCoupon_user_profiling_gender;

        return $tenant;
    }

    public function testOKGetListingTenantDefault()
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
            $this->assertSame((string) count($this->tenants_mall_1[$key]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[$key]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag); // all users has coupons
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

    public function testOKGetListingTenantFilteredByNewsID()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['news_id'] = $this->tenants_mall_1[0]->news[0]->news_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantFilteredByPromotionID()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['promotion_id'] = $this->tenants_mall_1[0]->newsPromotions[0]->news_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantFilteredByCouponID()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['coupon_id'] = $this->tenants_mall_1[0]->issued_coupon->promotion_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantFilteredByCouponRedeemID()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['coupon_redeem_id'] = $this->tenants_mall_1[0]->coupons[0]->promotion_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantFilteredByCouponRedeemIDAllEmployee()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;
        $_GET['coupon_redeem_id'] = $this->tenants_mall_2[0]->coupons[0]->promotion_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);
        $this->assertSame('true', (string) $response->data->extras->redeem_to_cs_flag);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_2[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_2[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_2[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_2[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_2[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_2[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame('false', (string) $item->news_flag);
            $this->assertSame('false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    //todo: testOKGetListingTenantFilteredByCouponRedeemIDSingleEmployee

    public function testOKGetListingTenantFilteredByCategoryID()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['category_id'] = $this->tenants_mall_1[0]->categories[0]->category_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantFilteredByFloorString()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['floor'] = $this->tenants_mall_1[0]->floor;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantFilteredByKeywordString()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['keyword'] = substr($this->tenants_mall_1[0]->name, 0, -1);

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(1, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame((string) $this->tenants_mall_1[0]->merchant_id, (string) $item->merchant_id);
            $this->assertSame((string) $this->tenants_mall_1[0]->name,(string) $item->name);
            $this->assertSame((string) $this->tenants_mall_1[0]->floor, (string) $item->floor);
            $this->assertSame((string) $this->tenants_mall_1[0]->unit, (string) $item->unit);
            foreach ($item->categories as $key2 => $category) {
                $this->assertSame((string) $this->tenants_mall_1[0]->categories[$key2]->category_name, (string) $category->category_name);
            }
            $this->assertSame((string) $this->tenants_mall_1[0]->merchantSocialMedia[0]->social_media_uri, (string) $item->facebook_like_url);
            $this->assertSame((string) count($this->tenants_mall_1[0]->news) > 0 ? 'true' : 'false', (string) $item->news_flag);
            $this->assertSame((string) count($this->tenants_mall_1[0]->newsPromotions) > 0 ? 'true' : 'false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testOKGetListingTenantWithCampaignAgeBadgesProfiling()
    {
        $_GET['apikey'] = $this->user_profiling_apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->user_profiling_apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(2, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame('true', (string) $item->news_flag);
            $this->assertSame('true', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testFAILGetListingTenantWithCampaignAgeBadgesProfiling()
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
        $this->assertSame(2, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame('false', (string) $item->news_flag);
            $this->assertSame('false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag); // everyone has coupon
        }
    }

    public function testOKGetListingTenantWithCampaignGenderBadgesProfiling()
    {
        $_GET['apikey'] = $this->user_profiling_apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->user_profiling_apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(2, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame('true', (string) $item->news_flag);
            $this->assertSame('true', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag);
        }
    }

    public function testFAILGetListingTenantWithCampaignGenderBadgesProfiling()
    {
        $_GET['apikey'] = $this->user_profiling_gender_apikey->api_key; // use the female user to fetch
        $_GET['apitimestamp'] = time();
        $_GET['mall_id'] = $this->mall_2->merchant_id;

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->user_profiling_gender_apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(2, (int) $response->data->returned_records);

        // check all returned records
        foreach ($response->data->records as $key => $item) {
            $this->assertSame('false', (string) $item->news_flag);
            $this->assertSame('false', (string) $item->promotion_flag);
            $this->assertSame('true', (string) $item->coupon_flag); // everyone has coupon
        }
    }
}
