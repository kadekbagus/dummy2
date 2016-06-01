<?php
/**
 * PHP Unit Test for Mall API Controller postUpdateMall
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateMallTestArtemisVersion extends TestCase
{
    private $apiUrlUpdate = 'api/v1/mall/update';

    public function setUp()
    {
        parent::setUp();

        $this->apiKey = Factory::create('apikey_super_admin');

        $this->enLang = Factory::create('Language', ['name' => 'en']);

        $this->country = Factory::create('Country');

        $this->timezone = Factory::create('Timezone');

        $this->facebook = Factory::create('SocialMedia', [ 'social_media_code'=> 'facebook']);

        Factory::create('role_mall_owner');

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostUpdateMall($api_key, $api_secret_key, $update)
    {
        $_GET = [];
        $_POST = [];

        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($update as $field => $value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrlUpdate . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function setDataMall()
    {
        $this->mall_a = $mall_a = Factory::create('Mall', ['name' => 'mall antok']);
        $this->widget_a = $widget_a = Factory::create('Widget', ['widget_type' => 'get_internet_access', 'status' => 'active']);
        Factory::create('WidgetRetailer', ['retailer_id' => $mall_a->merchant_id, 'widget_id' => $widget_a->widget_id]);

        $this->mall_b = $mall_b = Factory::create('Mall', ['name' => 'mall kadek']);
        $this->widget_b = $widget_b = Factory::create('Widget', ['widget_type' => 'get_internet_access', 'status' => 'inactive']);
        Factory::create('WidgetRetailer', ['retailer_id' => $mall_b->merchant_id, 'widget_id' => $widget_b->widget_id]);
    }

    public function testRequiredMerchantId()
    {
        /*
        * test merchant id is required
        */
        $data = [];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The merchant id field is required", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testExistMallName()
    {
        $this->setDataMall();

        /*
        * test exist mall name
        */

        $data = ['merchant_id' => $this->mall_a->merchant_id, 'name' => 'mall kadek'];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mall name already exists", $response->message);
        $this->assertSame(NULL, $response->data);
    }

    public function testUpdateWidgetGetInternetAccessToActive()
    {
        $this->setDataMall();

        /*
        * test update get internet access to active
        */
        $data = ['merchant_id' => $this->mall_b->merchant_id,
            'get_internet_access_status'    => 'active'
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("active", $response->data->get_internet_access_status);
    }

    public function testUpdateWidgetGetInternetAccessToInactive()
    {
        $this->setDataMall();
        /*
        * test update get internet access to active
        */
        $data = ['merchant_id' => $this->mall_a->merchant_id,
            'get_internet_access_status'    => 'inactive'
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("inactive", $response->data->get_internet_access_status);
    }
}