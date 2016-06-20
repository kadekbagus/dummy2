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
        $this->widget_a = $widget_a = Factory::create('Widget', ['widget_type' => 'free_wifi', 'status' => 'active']);
        Factory::create('WidgetRetailer', ['retailer_id' => $mall_a->merchant_id, 'widget_id' => $widget_a->widget_id]);

        $this->mall_b = $mall_b = Factory::create('Mall', ['name' => 'mall kadek']);
        $this->widget_b = $widget_b = Factory::create('Widget', ['widget_type' => 'free_wifi', 'status' => 'inactive']);
        Factory::create('WidgetRetailer', ['retailer_id' => $mall_b->merchant_id, 'widget_id' => $widget_b->widget_id]);

        $this->mall_c = $mall_c = Factory::create('Mall', ['name' => 'mall firman']);
        Factory::create('Object', ['merchant_id' => $mall_c->merchant_id, 'object_name' => 'B3', 'object_type' => 'floor', 'object_order' => 0]);
        Factory::create('Object', ['merchant_id' => $mall_c->merchant_id, 'object_name' => 'B2', 'object_type' => 'floor', 'object_order' => 1]);
        Factory::create('Object', ['merchant_id' => $mall_c->merchant_id, 'object_name' => 'B1', 'object_type' => 'floor', 'object_order' => 2]);

        Factory::create('Tenant', ['name' => 'tenant firman', 'floor' => 'B1', 'parent_id' => $mall_c->merchant_id]);

        $this->mall_d = Factory::create('Mall', ['ci_domain' => 'lippomall.gotomalls.com']);
        Factory::create('Setting', ['setting_name' => 'dom:lippomall.gotomalls.com', 'setting_value' => $this->mall_d->merchant_id]);
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

    public function testUpdateWidgetFreeWifiToActive()
    {
        $this->setDataMall();

        /*
        * test update free wifi to active
        */
        $data = ['merchant_id' => $this->mall_b->merchant_id,
            'free_wifi_status'    => 'active'
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("active", $response->data->free_wifi_status);
    }

    public function testUpdateWidgetFreeWifiToInactive()
    {
        $this->setDataMall();
        /*
        * test update free wifi to active
        */
        $data = ['merchant_id' => $this->mall_a->merchant_id,
            'free_wifi_status'    => 'inactive'
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame("inactive", $response->data->free_wifi_status);
    }

    public function testUpdateWidgetFreeWifiWhenMallDoesNotHaveWidgetFreeWifiOnDB()
    {
        $this->setDataMall();
        /*
        * test update free wifi when mall doesn have widget free wifi on database
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'free_wifi_status' => 'active'
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $widget = Widget::excludeDeleted()
                    ->leftJoin('widget_retailer', 'widget_retailer.widget_id', '=', 'widgets.widget_id')
                    ->where('widget_type', 'free_wifi')
                    ->where('retailer_id', $this->mall_c->merchant_id)
                    ->first();
        $this->assertSame('active', $widget->status);

        $widget_translations = WidgetTranslation::excludeDeleted('widget_translations')
                ->leftJoin('widgets', 'widgets.widget_id', '=', 'widget_translations.widget_id')
                ->leftJoin('widget_retailer', 'widget_retailer.widget_id', '=', 'widget_translations.widget_id')
                ->where('widget_type', 'free_wifi')
                ->where('retailer_id', $this->mall_c->merchant_id)
                ->get();
        foreach ($widget_translations as $idx => $translation) {
            $this->assertSame($this->mall_c->merchant_id, $translation->retailer_id);
            $this->assertSame($widget->widget_id, $translation->widget_id);
        }
    }

    public function testUpdateFloorOrder()
    {
        $this->setDataMall();

        $floor_array = ["{\"name\":\"B3\",\"order\":\"1\"}","{\"name\":\"B2\",\"order\":\"2\"}","{\"name\":\"B1\",\"order\":\"0\"}"];

        /*
        * test update floor order
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $response->data->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(3, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testDeleteFloor()
    {
        $this->setDataMall();

        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}"];

        /*
        * test delete floor not link on tenant
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $response->data->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(2, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testInsertNewFloor()
    {
        $this->setDataMall();

        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}","{\"name\":\"L1\",\"order\":\"3\"}"];

        /*
        * test insert new floor
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $response->data->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(4, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testDeleteAndInsertNewFloor()
    {
        $this->setDataMall();

        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"L1\",\"order\":\"3\"}"];

        /*
        * test delete and insert new floor
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $response->data->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(3, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testInsertDuplicateFloorName()
    {
        $this->setDataMall();

        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B3\",\"order\":\"2\"}","{\"name\":\"L1\",\"order\":\"3\"}"];

        /*
        * test insert duplicate floor name
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The floor name has already been taken", $response->message);

        $floor_array_db = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"];

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $this->mall_c->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(3, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array_db as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testDeleteFloorErrorWhenLinkToTenant()
    {
        $this->setDataMall();

        $floor_array = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}"];

        /*
        * test delete floor will error when link to tenant
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The floor B1 cannot be deleted: because used on Tenant", $response->message);

        $floor_array_db = ["{\"name\":\"B3\",\"order\":\"0\"}","{\"name\":\"B2\",\"order\":\"1\"}","{\"name\":\"B1\",\"order\":\"2\"}"];

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $this->mall_c->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(3, count($floor_on_db));

        foreach ($floor_on_db as $floor_db) {
            foreach ($floor_array_db as $floor_json) {
                $floor = @json_decode($floor_json);
                if ($floor_db->object_order === $floor->order) {
                    $this->assertSame($floor_db->object_name, $floor->name);
                }
            }
        }
    }

    public function testUpdateSubdomainAlphaNumericDash()
    {
        $this->setDataMall();

        $subdomain = 'seminyak-village23';

        /*
        * test update subdomain
        */
        $data = ['merchant_id' => $this->mall_d->merchant_id,
            'domain'    => $subdomain
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($subdomain . Config::get('orbit.shop.ci_domain'), $response->data->ci_domain);

        // check domain setting
        $dom_setting = Setting::where('setting_value', $this->mall_d->merchant_id)
                            ->where('setting_name', 'like', '%dom%')
                            ->first();

        $this->assertSame('dom:' . $subdomain . Config::get('orbit.shop.ci_domain'), $dom_setting->setting_name);
    }

    public function testUpdateSubdomainAlphaNumericDashDot()
    {
        $this->setDataMall();

        $subdomain = 'seminyak-village23.mall';

        /*
        * test update subdomain
        */
        $data = ['merchant_id' => $this->mall_d->merchant_id,
            'domain'    => $subdomain
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The domain may only contain letters, numbers, and dashes", $response->message);
    }

    public function testUpdateSubdomainAlphaNumericDashOtherChar()
    {
        $this->setDataMall();

        $subdomain = 'seminyak-villa#$@%^&ge23';

        /*
        * test update subdomain
        */
        $data = ['merchant_id' => $this->mall_d->merchant_id,
            'domain'    => $subdomain
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The domain may only contain letters, numbers, and dashes", $response->message);
    }
}