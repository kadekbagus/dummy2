<?php
/**
 * PHP Unit Test for Coupon API Controller postUpdateCoupon
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateCouponTest extends TestCase
{
    private $apiUrl = '/api/v1/coupon/update';

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
        $this->campaign_status_ongoing = Factory::create('CampaignStatus', ['campaign_status_name' => 'ongoing','order' => 3]);
        Factory::create('CampaignStatus', ['campaign_status_name' => 'paused','order' => 4]);
        Factory::create('CampaignStatus', ['campaign_status_name' => 'stopped','order' => 5]);

        Factory::create('CampaignBasePrice', ['merchant_id' => $this->mall_a->merchant_id, 'campaign_type' => 'coupon']);

        $this->campaign_history_action_add_tenant = Factory::create('CampaignHistoryAction', ['action_name' => 'add_tenant']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'delete_tenant']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'activate']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'deactivate']);
        Factory::create('CampaignHistoryAction', ['action_name' => 'change_base_price']);

        $this->coupon_a = Factory::create('Coupon', [
                    'promotion_name'     => 'Coupon Update Test',
                    'status'             => 'active',
                    'campaign_status_id' => $this->campaign_status_ongoing->campaign_status_id,
                ]);

        $this->coupon_a_translation = Factory::create('CouponTranslation', [
                'promotion_id' => $this->coupon_a->promotion_id,
                'promotion_name' => 'Coupon Update Test En',
                'merchant_language_id' => $this->enLang->language_id,
            ]);

        Factory::create('PromotionRetailer',[
                'promotion_id' => $this->coupon_a->promotion_id,
                'retailer_id'  => $this->tenant_mall_a->merchant_id,
            ]);

        Factory::create('user_campaign_coupon', [
                'user_id'       => $this->apiKey->user_id,
                'campaign_id'   => $this->coupon_a->promotion_id,
                'campaign_type' => 'coupon'
            ]);

        Factory::create('CampaignAccount',[
                'user_id'        => $this->apiKey->user_id,
                'parent_user_id' => NULL,
            ]);

        Factory::create('CouponRule',[
                'promotion_id' => $this->coupon_a->promotion_id,
            ]);

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostUpdateCoupon($api_key, $api_secret_key, $update_data)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($update_data as $field => $value) {
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

    public function testRequiredCouponId()
    {
        /*
        * test coupon id is required
        */
        $data = [];

        $response = $this->setRequestPostUpdateCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The promotion id field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testUpdateCouponSuccess()
    {
        /*
        * test coupon update success
        */
        $data = [
                'promotion_id'        => $this->coupon_a->promotion_id,
                'id_language_default' => $this->enLang->language_id,
                'is_all_gender'       => 'Y',
                'is_all_age'          => 'Y',
                'promotion_name'      => 'Coupon Baru Update',
            ];

        $response = $this->setRequestPostUpdateCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('Coupon Baru Update', $response->data->promotion_name);
    }

    public function testUpdateCouponDuplicateNameSuccess()
    {
        /*
        * test coupon update success
        */
        $data = [
                'promotion_id'        => $this->coupon_a->promotion_id,
                'id_language_default' => $this->enLang->language_id,
                'is_all_gender'       => 'Y',
                'is_all_age'          => 'Y',
                'promotion_name'      => 'Coupon Update Test',
            ];

        $response = $this->setRequestPostUpdateCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('Coupon Update Test', $response->data->promotion_name);
    }

    public function testUpdateCouponDuplicateTranslationNameSuccess()
    {
        /*
        * test coupon update success
        */
        $data = [
                'promotion_id'        => $this->coupon_a->promotion_id,
                'id_language_default' => $this->enLang->language_id,
                'is_all_gender'       => 'Y',
                'is_all_age'          => 'Y',
                'promotion_name'      => 'Coupon Update Test',
                'translations'        => '{"' . $this->enLang->language_id . '":{"promotion_name":"Coupon Update Test En","description":"Description Coupon Update Test English","long_description":""}}',
            ];

        $response = $this->setRequestPostUpdateCoupon($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame("Request OK", $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame('Coupon Update Test', $response->data->promotion_name);

        $translations_key_id = 'translation_' . snake_case($this->enLang->language_id);
        if (ctype_upper(substr($this->enLang->language_id, 0, 1))) {
            $translations_key_id = 'translation__' . snake_case($this->enLang->language_id);
        }

        $this->assertSame('Coupon Update Test En', $response->data->$translations_key_id->promotion_name);
    }
}