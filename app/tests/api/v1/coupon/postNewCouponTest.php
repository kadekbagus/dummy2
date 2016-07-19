<?php
/**
 * PHP Unit Test for Coupon API Controller postNewCoupon
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewCouponTest extends TestCase
{
    private $apiUrl = '/api/v1/coupon/new';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_campaign_owner');

        $this->enLang = Factory::create('Language', ['name' => 'en']);
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);
        $this->jpLang = Factory::create('Language', ['name' => 'jp']);

        $this->timezone = Factory::create('timezone_jakarta');

        $this->mall_a = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);
        $this->tenant_mall_a = Factory::create('Tenant', ['parent_id' => $this->mall_a->merchant_id]);

        Factory::create('CampaignStatus', ['campaign_status_name' => 'expired','order' => 1]);
        Factory::create('CampaignStatus', ['campaign_status_name' => 'not started','order' => 2]);
        Factory::create('CampaignStatus', ['campaign_status_name' => 'ongoing','order' => 3]);
        Factory::create('CampaignStatus', ['campaign_status_name' => 'paused','order' => 4]);
        Factory::create('CampaignStatus', ['campaign_status_name' => 'stopped','order' => 5]);

        Factory::create('CampaignBasePrice', ['merchant_id' => $this->mall_a->merchant_id, 'campaign_type' => 'coupon']);

        $this->campaign_history_action_add_tenant = Factory::create('CampaignHistoryAction', ['action_name' => 'add_tenant']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'delete_tenant']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'activate']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'deactivate']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'change_base_price']);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostNewCoupon($api_key, $api_secret_key, $new_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($new_data as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        return $response;
    }

    public function testRequiredCouponName()
    {
        /*
        * test coupon name is required
        */
        $data = [];

        $response = $this->setRequestPostNewCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The promotion name field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testNewCouponSuccess()
    {
        /*
        * test new coupon success
        */
        $data = [
            'promotion_name'          => 'Coupon Satu TEST',
            'promotion_type'          => 'mall',
            'begin_date'              => '2016-07-12 16:22:00',
            'end_date'                => '2016-07-31 23:59:00',
            'rule_type'               => 'auto_issue_on_every_signin',
            'rule_value'              => 1,
            'discount_value'          => 10,
            'id_language_default'     => $this->enLang->language_id,
            'is_all_gender'           => 'Y',
            'is_all_age'              => 'Y',
            'is_popup'                => 'Y',
            'coupon_validity_in_date' => '2016-07-31 23:59:59',
            'retailer_ids'            => ['{"tenant_id":"' . $this->tenant_mall_a->merchant_id . '","mall_id":"' . $this->mall_a->merchant_id . '"}'],
            'current_mall'            => $this->mall_a->merchant_id,
            'is_all_employee'         => 'N',
            'link_to_tenant_ids'      => ['{"tenant_id":"' . $this->tenant_mall_a->merchant_id . '","mall_id":"' . $this->mall_a->merchant_id . '"}'],
        ];

        $response = $this->setRequestPostNewCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('Coupon Satu TEST', $response->data->promotion_name);
    }

    public function testNewCouponDuplicateNameSuccess()
    {
        $coupon_satu = Factory::create('Coupon', ['promotion_name' => 'Coupon Satu TEST']);

        /*
        * test new coupon success
        */
        $data = [
            'promotion_name'          => 'Coupon Satu TEST',
            'promotion_type'          => 'mall',
            'begin_date'              => '2016-07-12 16:22:00',
            'end_date'                => '2016-07-31 23:59:00',
            'rule_type'               => 'auto_issue_on_every_signin',
            'rule_value'              => 1,
            'discount_value'          => 10,
            'id_language_default'     => $this->enLang->language_id,
            'is_all_gender'           => 'Y',
            'is_all_age'              => 'Y',
            'is_popup'                => 'Y',
            'coupon_validity_in_date' => '2016-07-31 23:59:59',
            'retailer_ids'            => ['{"tenant_id":"' . $this->tenant_mall_a->merchant_id . '","mall_id":"' . $this->mall_a->merchant_id . '"}'],
            'current_mall'            => $this->mall_a->merchant_id,
            'is_all_employee'         => 'N',
            'link_to_tenant_ids'      => ['{"tenant_id":"' . $this->tenant_mall_a->merchant_id . '","mall_id":"' . $this->mall_a->merchant_id . '"}'],
        ];

        $response = $this->setRequestPostNewCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('Coupon Satu TEST', $response->data->promotion_name);
    }

    public function testNewCouponDuplicateNameTranslationSuccess()
    {
        $coupon_satu = Factory::create('Coupon', ['promotion_name' => 'Coupon Satu TEST']);

        $coupon_satu_translation = Factory::create('CouponTranslation', [
                'promotion_id' => $coupon_satu->promotion_id,
                'promotion_name' => 'Coupon Satu TEST En',
                'merchant_language_id' => $this->enLang->language_id,
            ]);

        $db_coupon = Coupon::excludeDeleted()
                            ->first();
        $this->assertSame('Coupon Satu TEST', $db_coupon->promotion_name);

        $db_coupon_translation = CouponTranslation::excludeDeleted()
                                ->where('promotion_id', $db_coupon->promotion_id)
                                ->where('merchant_language_id', $this->enLang->language_id)
                                ->first();
        $this->assertSame('Coupon Satu TEST En', $db_coupon_translation->promotion_name);

        /*
        * test new coupon success
        */
        $data = [
            'promotion_name'          => 'Coupon Satu TEST',
            'promotion_type'          => 'mall',
            'begin_date'              => '2016-07-12 16:22:00',
            'end_date'                => '2016-07-31 23:59:00',
            'rule_type'               => 'auto_issue_on_every_signin',
            'rule_value'              => 1,
            'discount_value'          => 10,
            'id_language_default'     => $this->enLang->language_id,
            'is_all_gender'           => 'Y',
            'is_all_age'              => 'Y',
            'is_popup'                => 'Y',
            'coupon_validity_in_date' => '2016-07-31 23:59:59',
            'retailer_ids'            => ['{"tenant_id":"' . $this->tenant_mall_a->merchant_id . '","mall_id":"' . $this->mall_a->merchant_id . '"}'],
            'current_mall'            => $this->mall_a->merchant_id,
            'is_all_employee'         => 'N',
            'link_to_tenant_ids'      => ['{"tenant_id":"' . $this->tenant_mall_a->merchant_id . '","mall_id":"' . $this->mall_a->merchant_id . '"}'],
            'translations'            => '{"' . $this->enLang->language_id . '":{"promotion_name":"Coupon Satu TEST En","description":"Description Coupon Satu TEST English","long_description":""}}',
        ];

        $response = $this->setRequestPostNewCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('Coupon Satu TEST', $response->data->promotion_name);

        $translations_key_id = 'translation_' . snake_case($this->enLang->language_id);
        if (ctype_upper(substr($this->enLang->language_id, 0, 1))) {
            $translations_key_id = 'translation__' . snake_case($this->enLang->language_id);
        }

        $this->assertSame('Coupon Satu TEST En', $response->data->$translations_key_id->promotion_name);
    }
}