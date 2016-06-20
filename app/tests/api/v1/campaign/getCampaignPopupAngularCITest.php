<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\CampaignCIAPIController::getCampaignPopup() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getCampaignPopupAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/campaigns/pop-up';

    public function setUp()
    {
        parent::setUp();

        $this->user = Factory::create('user_consumer');
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

        $this->mall_1 = Factory::create('Mall');

        $this->mall_1_age_profiles = Factory::create('AgeRange', [
            'merchant_id'   => $this->mall_1->merchant_id,
            'range_name'    => '35 - 44',
            'min_value'     => 35,
            'max_value'     => 44
        ]);
 
        $begin_date = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
        $this->coupon = Factory::create('Coupon', [
            'begin_date' => $begin_date,
            'end_date' => $end_date,
            'promotion_type' => 'mall',
            'is_all_gender' => 'N',
            'is_all_age' => 'N',
            'is_all_employee' => 'Y',
            'is_popup' => 'Y',
            'coupon_validity_in_date' => $end_date,
        ]);
        $this->couponMerchant = Factory::create('coupon_link_tenant', [
            'promotion_id' => $this->coupon->promotion_id,
            'retailer_id' => $this->mall_1->merchant_id,
            'object_type' => 'mall'
        ]);
        $this->couponAgeProfile = Factory::create('CampaignAge', [
            'campaign_type' => 'coupon',
            'campaign_id'   => $this->coupon->promotion_id,
            'age_range_id'  => $this->mall_1_age_profiles->age_range_id // select this range to have it displayed
        ]);
        $this->couponGenderProfile = Factory::create('CampaignGender', [
            'campaign_type' => 'coupon',
            'campaign_id'   => $this->coupon->promotion_id,
            'gender_value'  => 'M' // select this gender to have it displayed
        ]);

        $this->promotion = Factory::create('News', [
            'mall_id' => $this->mall_1->merchant_id,
            'object_type'  => 'promotion',
            'begin_date'  => $begin_date,
            'end_date'    => $end_date,
            'is_all_gender' => 'N',
            'is_all_age' => 'N',
            'is_popup' => 'Y',
        ]);
        $this->promotionMerchant = Factory::create('NewsMerchant', [
            'news_id' => $this->promotion->news_id,
            'merchant_id' => $this->mall_1->merchant_id,
            'object_type' => 'mall'
        ]);
        $this->promotionAgeProfile = Factory::create('CampaignAge', [
            'campaign_type' => 'coupon',
            'campaign_id'   => $this->promotion->news_id,
            'age_range_id'  => $this->mall_1_age_profiles->age_range_id // select this range to have it displayed
        ]);
        $this->promotionGenderProfile = Factory::create('CampaignGender', [
            'campaign_type' => 'coupon',
            'campaign_id'   => $this->promotion->news_id,
            'gender_value'  => 'M' // select this gender to have it displayed
        ]);

        $this->news = Factory::create('News', [
            'mall_id' => $this->mall_1->merchant_id,
            'object_type'  => 'news',
            'begin_date'  => $begin_date,
            'end_date'    => $end_date,
            'is_all_gender' => 'N',
            'is_all_age' => 'N',
            'is_popup' => 'Y',
        ]);
        $this->newsMerchant = Factory::create('NewsMerchant', [
            'news_id' => $this->news->news_id,
            'merchant_id' => $this->mall_1->merchant_id,
            'object_type' => 'mall'
        ]);
        $this->newsAgeProfile = Factory::create('CampaignAge', [
            'campaign_type' => 'coupon',
            'campaign_id'   => $this->news->news_id,
            'age_range_id'  => $this->mall_1_age_profiles->age_range_id // select this range to have it displayed
        ]);
        $this->newsGenderProfile = Factory::create('CampaignGender', [
            'campaign_type' => 'coupon',
            'campaign_id'   => $this->news->news_id,
            'gender_value'  => 'M' // select this gender to have it displayed
        ]);
        
        Config::set('orbit.shop.main_domain', 'gotomalls.cool');

        $_GET = [];
        $_POST = [];
    }

    public function testOKGetCampaignPopUpWithProfiling()
    {
        $_GET['apikey'] = $this->user_profiling_apikey->api_key;
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['apitimestamp'] = time();

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->user_profiling_apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(3, (int) $response->data->returned_records);
    }

    public function testOKGetCampaignPopUpWithNoProfiling()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['apitimestamp'] = time();

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame(0, (int) $response->data->returned_records);
    }
}
