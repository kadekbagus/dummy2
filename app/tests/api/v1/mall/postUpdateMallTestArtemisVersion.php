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
        $this->idLang = Factory::create('Language', ['name' => 'id']);
        $this->zhLang = Factory::create('Language', ['name' => 'zh']);
        $this->jpLang = Factory::create('Language', ['name' => 'jp']);

        $this->country = Factory::create('Country');

        $this->timezone = Factory::create('Timezone');

        $this->facebook = Factory::create('SocialMedia', [ 'social_media_code'=> 'facebook']);

        Factory::create('role_mall_owner');

        $_GET = [];
        $_POST = [];
    }

    public function setRequestPostUpdateMall($api_key, $api_secret_key, $update)
    {
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
        $this->fl_b3 = Factory::create('Object', ['merchant_id' => $mall_c->merchant_id, 'object_name' => 'B3', 'object_type' => 'floor', 'object_order' => 0]);
        $this->fl_b2 = Factory::create('Object', ['merchant_id' => $mall_c->merchant_id, 'object_name' => 'B2', 'object_type' => 'floor', 'object_order' => 1]);
        $this->fl_b1 = Factory::create('Object', ['merchant_id' => $mall_c->merchant_id, 'object_name' => 'B1', 'object_type' => 'floor', 'object_order' => 2]);

        Factory::create('Tenant', ['name' => 'tenant firman', 'floor_id' => $this->fl_b1->object_id, 'parent_id' => $mall_c->merchant_id]);

        $this->mall_d = Factory::create('Mall', ['ci_domain' => 'lippomall.gotomalls.cool']);
        Factory::create('Setting', ['setting_name' => 'dom:lippomall.gotomalls.com', 'setting_value' => $this->mall_d->merchant_id]);

        $this->mall_e = Factory::create('Mall', ['description' => 'antok mall oke', 'mobile_default_language' => 'id']);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $this->mall_e->merchant_id]);
        $this->oldZhLang = Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $this->mall_e->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->jpLang->language_id, 'merchant_id' => $this->mall_e->merchant_id]);
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

        $floor_array = ["{\"id\":\"{$this->fl_b3->object_id}\",\"name\":\"{$this->fl_b3->object_name}\",\"order\":\"1\"}",
            "{\"id\":\"{$this->fl_b2->object_id}\",\"name\":\"{$this->fl_b2->object_name}\",\"order\":\"2\"}",
            "{\"id\":\"{$this->fl_b1->object_id}\",\"name\":\"{$this->fl_b1->object_name}\",\"order\":\"0\"}"];

        /*
        * test update floor order
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
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

    public function testUpdateFloorName()
    {
        $this->setDataMall();

        $floor_array = [
            "{\"id\":\"{$this->fl_b3->object_id}\",\"name\":\"{$this->fl_b3->object_name}\",\"order\":\"0\"}",
            "{\"id\":\"{$this->fl_b2->object_id}\",\"name\":\"{$this->fl_b2->object_name}\",\"order\":\"1\"}",
            "{\"id\":\"{$this->fl_b1->object_id}\",\"name\":\"L1\",\"order\":\"2\"}"
        ];

        /*
        * test update floor name
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

        $floor_array = [
                "{\"id\":\"{$this->fl_b3->object_id}\",\"name\":\"{$this->fl_b3->object_name}\",\"order\":\"3\", \"floor_delete\":\"yes\"}"
            ];

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
    }

    public function testInsertNewFloor()
    {
        $this->setDataMall();

        $floor_array = ["{\"id\":\"{$this->fl_b3->object_id}\",\"name\":\"{$this->fl_b3->object_name}\",\"order\":\"1\"}",
            "{\"id\":\"{$this->fl_b2->object_id}\",\"name\":\"{$this->fl_b2->object_name}\",\"order\":\"2\"}",
            "{\"name\":\"L1\",\"order\":\"3\"}",
            "{\"id\":\"{$this->fl_b1->object_id}\",\"name\":\"{$this->fl_b1->object_name}\",\"order\":\"0\"}"];
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

    public function testInsertExistFloorWithoutFloorId()
    {
        $this->setDataMall();

        $floor_array = [
            "{\"name\":\"B3\",\"order\":\"3\"}",
        ];
        /*
        * test insert new floor
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
    }

    public function testDeleteAndInsertNewFloor()
    {
        $this->setDataMall();

        $floor_array = [
                "{\"id\":\"{$this->fl_b2->object_id}\",\"name\":\"{$this->fl_b2->object_name}\",\"order\":\"2\"}",
                "{\"name\":\"L1\",\"order\":\"3\"}",
                "{\"id\":\"{$this->fl_b3->object_id}\",\"name\":\"{$this->fl_b3->object_name}\",\"order\":\"3\", \"floor_delete\":\"yes\"}",
                "{\"id\":\"{$this->fl_b1->object_id}\",\"name\":\"{$this->fl_b1->object_name}\",\"order\":\"0\"}"
            ];
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
    }

    public function testInsertDuplicateFloorName()
    {
        $this->setDataMall();

        $floor_array = [
                "{\"id\":\"{$this->fl_b3->object_id}\",\"name\":\"{$this->fl_b3->object_name}\",\"order\":\"0\"}",
                "{\"id\":\"{$this->fl_b2->object_id}\",\"name\":\"{$this->fl_b2->object_name}\",\"order\":\"1\"}",
                "{\"name\":\"B3\",\"order\":\"2\"}",
                "{\"name\":\"L1\",\"order\":\"3\"}",
            ];

        /*
        * test insert duplicate floor name
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Floor name has already been used", $response->message);

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

        $floor_array = [
                "{\"id\":\"{$this->fl_b1->object_id}\",\"name\":\"{$this->fl_b1->object_name}\",\"order\":\"3\", \"floor_delete\":\"yes\"}"
            ];
        /*
        * test delete floor will error when link to tenant
        */
        $data = ['merchant_id' => $this->mall_c->merchant_id,
            'floors'    => $floor_array
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("One or more active tenants are located on this floor", $response->message);

        $floor_on_db = Object::excludeDeleted()
                        ->where('merchant_id', $this->mall_c->merchant_id)
                        ->where('object_type', 'floor')
                        ->get();

        $this->assertSame(3, count($floor_on_db));
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

    public function testUpdateDuplicateSubdomain()
    {
        $this->setDataMall();

        $mall_xx = Factory::create('Mall', ['ci_domain' => 'seminyak.gotomalls.cool']);

        $subdomain = 'seminyak';

        /*
        * test update duplicate subdomain
        */
        $data = ['merchant_id' => $this->mall_d->merchant_id,
            'domain'    => $subdomain
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mall URL Application Domain name has already been taken", $response->message);
    }

    public function testUpdateDuplicateSubdomainButNotMe()
    {
        $this->setDataMall();

        $mall_xx = Factory::create('Mall', ['ci_domain' => 'seminyak.gotomalls.cool']);

        $subdomain = 'lippomall';

        /*
        * test update duplicate subdomain
        */
        $data = ['merchant_id' => $this->mall_d->merchant_id,
            'domain'    => $subdomain
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($subdomain . Config::get('orbit.shop.ci_domain'), $response->data->ci_domain);
    }

    public function testUpdateDescription()
    {
        $this->setDataMall();

        $description = 'antok mall bagus';

        /*
        * test update description
        */
        $data = ['merchant_id' => $this->mall_e->merchant_id,
            'description'    => $description
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($description, $response->data->description);
    }

    public function testUpdateDescriptionMaxChar()
    {
        $this->setDataMall();

        $description = 'antok mall bagus banget beneran';

        /*
        * test update description
        */
        $data = ['merchant_id' => $this->mall_e->merchant_id,
            'description'    => $description
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The description may not be greater than 25 characters", $response->message);
    }

    public function testUpdateAddLanguage()
    {
        $this->setDataMall();

        // add english
        $languages               = ['jp','zh','id', 'en'];
        $mobile_default_language = 'id';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($mobile_default_language, $response->data->mobile_default_language);
        // check mall languages on database
        $lang_db = MerchantLanguage::excludeDeleted('merchant_languages')
                    ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                    ->where('merchant_id', $this->mall_e->merchant_id)
                    ->get();
        foreach ($lang_db as $idx => $lang) {
            $this->assertContains($lang->name, $languages);
        }
    }

    public function testUpdateRemoveLanguage()
    {
        $this->setDataMall();

        // remove zh
        $languages               = ['jp','id'];
        $mobile_default_language = 'id';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($mobile_default_language, $response->data->mobile_default_language);
        // check mall languages on database
        $lang_db = MerchantLanguage::excludeDeleted('merchant_languages')
                    ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                    ->where('merchant_id', $this->mall_e->merchant_id)
                    ->get();
        foreach ($lang_db as $idx => $lang) {
            $this->assertContains($lang->name, $languages);
        }
    }

    public function testUpdateRemoveLanguageWhenUseOnMobileLanguage()
    {
        /*
        * test remove language when use on mobile default language
        */
        $this->setDataMall();

        $languages               = ['jp','zh'];
        $mobile_default_language = 'id';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mobile default language must on list languages", $response->message);
    }

    public function testRemoveLanguagesHasLink()
    {
        /*
        * when language has link its allowed to delete
        */
        $this->setDataMall();

        // link to campaign
        // create campaign translation with mobile default language
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->zhLang->language_id
            ]);
        $tenant = Factory::create('Tenant', ['parent_id' => $this->mall_e->merchant_id]);
        $new_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $languages               = ['jp', 'id'];
        $mobile_default_language = 'id';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check merchant language is deleted
        $del_mall_lang = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall_e->merchant_id)
                            ->first();

        $this->assertSame("deleted", $del_mall_lang->status);

        // check link campaign translation is still active
        $_news_translation = NewsTranslation::where('news_id', $news->news_id)
                                ->first();

        $this->assertSame("active", $_news_translation->status);
    }

    public function testRemoveLanguagesHasLinkThenAddThatLanguage()
    {
        /*
        * when language has link its allowed to delete
        */
        $this->setDataMall();

        // link to campaign
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->zhLang->language_id
            ]);
        $tenant = Factory::create('Tenant', ['parent_id' => $this->mall_e->merchant_id]);
        $new_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $languages               = ['jp', 'id'];
        $mobile_default_language = 'id';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check merchant language is deleted
        $del_mall_lang = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall_e->merchant_id)
                            ->first();
        $this->assertSame("deleted", $del_mall_lang->status);

        // check link campaign translation is still active
        $_news_translation = NewsTranslation::where('news_id', $news->news_id)
                                ->first();
        $this->assertSame("active", $_news_translation->status);

        // add mall language zh
        $new_zhLang = Factory::Create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $this->mall_e->merchant_id]);

        // check database has two zh and one of data is active
        $zh_on_db = MerchantLanguage::where('language_id', $this->zhLang->language_id)
                            ->where('merchant_id', $this->mall_e->merchant_id)
                            ->get();
        $this->assertSame(2, count($zh_on_db));
        foreach ($zh_on_db as $_zhLang) {
            if ($_zhLang->merchant_language_id === $this->oldZhLang->merchant_language_id)
                $this->assertSame('deleted', $_zhLang->status);
            if ($_zhLang->merchant_language_id === $new_zhLang->merchant_language_id)
                $this->assertSame('active', $_zhLang->status);
        }

        // check language is still link to old campaign zh translation
        $check_news_translation = NewsTranslation::join('news_merchant', 'news_merchant.news_id', '=', 'news_translations.news_id')
                                    ->where('merchant_language_id', $new_zhLang->language_id)
                                    ->where('news_merchant.merchant_id', $tenant->merchant_id)
                                    ->first();
        $this->assertSame($news_translation->news_name, $check_news_translation->news_name);
    }

    public function testUpdateMobileLanguages()
    {
        /*
        * when update mobile default language doesnt has link is allowed to change
        */
        $this->setDataMall();

        $languages               = ['jp','zh','id'];
        $mobile_default_language = 'zh';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        $this->assertSame($mobile_default_language, $response->data->mobile_default_language);
    }

    public function testUpdateMobileLanguagesHasLink()
    {
        /*
        * when update mobile default language has link is doesnt allowed to change
        */
        $this->setDataMall();

        // create campaign translation with mobile default language
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->idLang->language_id
            ]);
        $tenant = Factory::create('Tenant', ['parent_id' => $this->mall_e->merchant_id]);
        $new_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $languages               = ['jp','zh','id'];
        $mobile_default_language = 'zh';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Cannot change default supported language has campaign translation", $response->message);
    }

    public function testUpdateMobileLanguagesNotOnListLanguages()
    {
        /*
        * test update mobile default language not on list language
        */
        $this->setDataMall();

        $languages               = ['jp','zh','id'];
        $mobile_default_language = 'en';

        $data = [
            'merchant_id'             => $this->mall_e->merchant_id,
            'languages'               => $languages,
            'mobile_default_language' => $mobile_default_language
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("Mobile default language must on list languages", $response->message);
    }

    public function testGeofenceLatitudeRangeError()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '100';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence latitude is not on range", $response->message);
    }

    public function testGeofenceLongitudeRangeError()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '200';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174527';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence longitude is not on range", $response->message);
    }

    public function testGeofenceAreaPolygonFormatError()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceAreaPolygonFirstAndLastPointDifferent()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-8.663007 115.174527,-8.662275 115.176930,-8.664174 115.177735,-8.665669 115.175836,-8.664842 115.174227,-8.663007 115.174537';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceAreaLatLonFormatError()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-190 80,-191 81,-192 83,-190 80';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceAreaSamePointWillErrorExceptFirstAndLastPoint()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-90 180,-90 180,-90 180,-90 180';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(14, $response->code);
        $this->assertSame("error", $response->status);
        $this->assertSame("The Geofence area is not valid", $response->message);
    }

    public function testGeofenceSuccess()
    {
        /*
        * test geofence
        */
        $this->setDataMall();

        $geo_point_latitude  = '-8.663937';
        $geo_point_longitude = '115.174142';
        $geo_point_area      = '-0.219726 112.5, 0.329588 114.785156, -1.58183 114.345703, -0.219726 112.5';

        $data = [
            'merchant_id'         => $this->mall_e->merchant_id,
            'geo_point_latitude'  => $geo_point_latitude,
            'geo_point_longitude' => $geo_point_longitude,
            'geo_area'            => $geo_point_area,
        ];

        $response = $this->setRequestPostUpdateMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $data);
        $this->assertSame(0, $response->code);
        $this->assertSame("success", $response->status);
        // check data is on database

        $geo_mall = MerchantGeofence::select(
                            DB::Raw('
                                asText(area) as area,
                                X(position) as latitude,
                                Y(position) as longitude
                            ')
                        )
                        ->where('merchant_id', $this->mall_e->merchant_id)
                        ->first();

        $this->assertSame($geo_point_latitude, $geo_mall->latitude);
        $this->assertSame($geo_point_longitude, $geo_mall->longitude);
    }
}