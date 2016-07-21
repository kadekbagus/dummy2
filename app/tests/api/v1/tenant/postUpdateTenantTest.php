<?php
/**
 * PHP Unit Test for Tenant API Controller postUpdateTenant
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateTenantTest extends TestCase
{
    private $apiUrl = 'api/v1/tenant/update';

    public function setUp()
    {
        parent::setUp();

        $this->user_mall_owner = Factory::create('user_mall_owner');
        $this->apiKey = Factory::create('apikey_mall_owner', ['user_id' => $this->user_mall_owner->user_id]);

        $this->mall_a = Factory::create('Mall', ['user_id' => $this->user_mall_owner->user_id]);

        $this->enLang = Factory::create('Language', ['name' => 'en']);

        $this->merchant_languages = Factory::create('MerchantLanguage', ['language_id' => $this->enLang->language_id, 'merchant_id' => $this->mall_a->merchant_id]);

        $this->floor = Factory::create('Object', [
                'object_type' => 'floor',
                'object_name' => 'B1',
                'merchant_id' => $this->mall_a->merchant_id
            ]);

        $this->floor_b2 = Factory::create('Object', [
                'object_type' => 'floor',
                'object_name' => 'B2',
                'merchant_id' => $this->mall_a->merchant_id
            ]);

        $this->tenant = Factory::create('Tenant', [
                'object_type' => 'tenant',
                'parent_id' => $this->mall_a->merchant_id,
                'floor_id' => $this->floor->object_id,
                'floor' => $this->floor->object_name,
            ]);
    }

    public function setRequestPostUpdateTenant($api_key, $api_secret_key, $update_data)
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

    public function testUpdateFloor()
    {
        /*
        * test set Tenant Floor
        */
        $data = [
            'retailer_id'         => $this->tenant->merchant_id, // tenant_id
            'current_mall'        => $this->mall_a->merchant_id, // parent_id
            'parent_id'           => $this->mall_a->merchant_id, // parent_id
            'id_language_default' => $this->enLang->language_id,
            'floor_id'            => $this->floor_b2->object_id,
        ];

        $response = $this->setRequestPostUpdateTenant($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($this->floor_b2->object_name, $response->data->floor);
        $this->assertSame($this->floor_b2->object_id, $response->data->floor_id);
    }

    public function testErrorUpdateFloor()
    {
        /*
        * test set Tenant Floor
        */
        $data = [
            'retailer_id'         => $this->tenant->merchant_id, // tenant_id
            'current_mall'        => $this->mall_a->merchant_id, // parent_id
            'parent_id'           => $this->mall_a->merchant_id, // parent_id
            'id_language_default' => $this->enLang->language_id,
            'floor_id'            => 'dsfa4474',
        ];

        $response = $this->setRequestPostUpdateTenant($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Floor you specified is not found", $response->message);
    }

    public function testUpdateFloorWithEmpyString()
    {
        /*
        * test set Tenant Floor
        */
        $data = [
            'retailer_id'         => $this->tenant->merchant_id, // tenant_id
            'current_mall'        => $this->mall_a->merchant_id, // parent_id
            'parent_id'           => $this->mall_a->merchant_id, // parent_id
            'id_language_default' => $this->enLang->language_id,
            'floor_id'            => '',
        ];

        $response = $this->setRequestPostUpdateTenant($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
    }
}