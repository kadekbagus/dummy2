<?php
/**
 * PHP Unit Test for Tenant API Controller postNewTenant
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewTenantTestArtemisVersion extends TestCase
{
    private $apiUrl = 'api/v1/tenant/new';

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
    }

    public function setRequestPostNewTenant($api_key, $api_secret_key, $new_data)
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

    public function testSetTenantFloor()
    {
        /*
        * test set Tenant Floor
        */
        $data = [
            'merchant_id'         => $this->mall_a->merchant_id,
            'id_language_default' => $this->enLang->language_id,
            'name'                => 'tenant 1',
            'external_object_id'  => 0,
            'object_type'         => 'tenant',
            'floor_id'            => $this->floor->object_id,
            'status'              => 'active'
        ];

        $response = $this->setRequestPostNewTenant($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($this->floor->object_name, $response->data->floor);
        $this->assertSame($this->floor->object_id, $response->data->floor_id);
    }
}