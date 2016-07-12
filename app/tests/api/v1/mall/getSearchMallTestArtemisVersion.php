<?php
/**
 * PHP Unit Test for Mall API Controller getSearchMall
 *
 * @author: Irianto Pratama <irianto@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getSearchMallTestArtemisVersion extends TestCase
{
    private $apiUrlList = 'api/v1/mall/search';

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

    public function setRequestGetSearchMall($api_key, $api_secret_key, $filter)
    {
        // Set the client API Keys
        $_GET['apikey'] = $api_key;
        $_GET['apitimestamp'] = time();

        foreach ($filter as $field => $value) {
            $_GET[$field] = $value;
        }
        $url = $this->apiUrlList . '?' . http_build_query($_GET);

        $secretKey = $api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        unset($_POST);
        unset($_GET);

        return $response;
    }

    public function testGetSearchMallWithWidgetFreeWifi()
    {
        $mall_a = Factory::create('Mall');
        $widget_a = Factory::create('Widget', ['widget_type' => 'free_wifi', 'status' => 'active']);
        Factory::create('WidgetRetailer', ['retailer_id' => $mall_a->merchant_id, 'widget_id' => $widget_a->widget_id]);

        $mall_b = Factory::create('Mall');
        $widget_b = Factory::create('Widget', ['widget_type' => 'free_wifi', 'status' => 'inactive']);
        Factory::create('WidgetRetailer', ['retailer_id' => $mall_b->merchant_id, 'widget_id' => $widget_b->widget_id]);

        $filter = ['with' => ['widget_free_wifi']];

        /*
        * test get widget free wifi status
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id)
                $this->assertSame('inactive', $data->widget_free_wifi[0]->status);
            if($mall_a->merchant_id  === $data->merchant_id)
                $this->assertSame('active', $data->widget_free_wifi[0]->status);
        }
    }

    public function testGetSubdomain()
    {
        $mall_a = Factory::create('Mall', ['ci_domain' => 'lippomall.gotomalls.com']);

        $mall_b = Factory::create('Mall', ['ci_domain' => 'seminyakvillage.gotomalls.com']);

        $filter = [];

        /*
        * test get widget free wifi status
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        $this->assertSame('success', $response_search->status);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id)
                $this->assertSame('seminyakvillage', $data->subdomain);
            if($mall_a->merchant_id  === $data->merchant_id)
                $this->assertSame('lippomall', $data->subdomain);
        }
    }

    public function testGetDescription()
    {
        $mall_a = Factory::create('Mall', ['description' => 'mall antok bagus']);

        $mall_b = Factory::create('Mall', ['description' => 'mall irianto oke']);

        $filter = [];

        /*
        * test get widget free wifi status
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        $this->assertSame('success', $response_search->status);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id)
                $this->assertSame('mall irianto oke', $data->description);
            if($mall_a->merchant_id  === $data->merchant_id)
                $this->assertSame('mall antok bagus', $data->description);
        }
    }

    public function testGetFloor()
    {
        $mall_a = Factory::create('Mall', ['description' => 'mall antok bagus']);

        $mall_b = Factory::create('Mall', ['description' => 'mall irianto oke']);

        $floor_B1 = Factory::create('floor', ['object_name' => 'B1', 'merchant_id' => $mall_a->merchant_id]);
        $floor_L1 = Factory::create('floor', ['object_name' => 'L1', 'merchant_id' => $mall_a->merchant_id]);
        $floor_L2 = Factory::create('floor', ['object_name' => 'L2', 'merchant_id' => $mall_b->merchant_id]);

        $floor_mall_a = [$floor_B1->object_id, $floor_L1->object_id];
        $floor_mall_b = [$floor_L2->object_id];

        $filter = [
            'with' => ['mallFloors']
        ];

        /*
        * test get floor
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        $this->assertSame('success', $response_search->status);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall irianto oke', $data->description);
                foreach ($data->mall_floors as $floor) {
                    $this->assertContains($floor->object_id, $floor_mall_b);
                }
            }
            if($mall_a->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall antok bagus', $data->description);
                foreach ($data->mall_floors as $floor) {
                    $this->assertContains($floor->object_id, $floor_mall_a);
                }
            }
        }
    }

    public function testGetLanguage() {
        $mall_a = Factory::create('Mall', ['description' => 'mall antok bagus', 'mobile_default_language' => 'en']);
        Factory::create('MerchantLanguage', ['language_id' => $this->enLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        $language_mall_a = [$this->enLang->language_id,$this->zhLang->language_id,$this->idLang->language_id];

        $mall_b = Factory::create('Mall', ['description' => 'mall irianto oke', 'mobile_default_language' => 'id']);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->jpLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        $language_mall_b = [$this->idLang->language_id,$this->zhLang->language_id,$this->jpLang->language_id];

        $filter = [
            'with' => ['mallLanguages']
        ];

        /*
        * test get languages
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        $this->assertSame('success', $response_search->status);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall irianto oke', $data->description);
                foreach ($data->mall_languages as $language) {
                    $this->assertContains($language->language_id, $language_mall_b);
                }
            }
            if($mall_a->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall antok bagus', $data->description);
                foreach ($data->mall_languages as $language) {
                    $this->assertContains($language->language_id, $language_mall_a);
                }
            }
        }
    }

    public function testGetMobileDefaultLanguageLinkStatusCampaignLinkToTenant() {
        $mall_a = Factory::create('Mall', ['description' => 'mall antok bagus', 'mobile_default_language' => 'en']);
        Factory::create('MerchantLanguage', ['language_id' => $this->enLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        $language_mall_a = [$this->enLang->language_id,$this->zhLang->language_id,$this->idLang->language_id];

        $mall_b = Factory::create('Mall', ['description' => 'mall irianto oke', 'mobile_default_language' => 'id']);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->jpLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        $language_mall_b = [$this->idLang->language_id,$this->zhLang->language_id,$this->jpLang->language_id];

        // create campaign translation with mobile default language
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->idLang->language_id
            ]);
        // create campaign link to tenant
        $tenant = Factory::create('Tenant', ['parent_id' => $mall_b->merchant_id]);
        $news_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $tenant->merchant_id,
                    'object_type' => 'tenant'
                ]);

        $filter = [
            'with' => ['mallLanguages']
        ];

        /*
        * test get language and mobile default language link status
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        $this->assertSame('success', $response_search->status);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall irianto oke', $data->description);
                foreach ($data->mall_languages as $language) {
                    $this->assertContains($language->language_id, $language_mall_b);
                }
                $this->assertSame('true', $data->disable_mobile_default_language);
            }
            if($mall_a->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall antok bagus', $data->description);
                foreach ($data->mall_languages as $language) {
                    $this->assertContains($language->language_id, $language_mall_a);
                }
                $this->assertSame('false', $data->disable_mobile_default_language);
            }
        }
    }

    public function testGetMobileDefaultLanguageLinkStatusCampaignLinkToMall() {
        $mall_a = Factory::create('Mall', ['description' => 'mall antok bagus', 'mobile_default_language' => 'en']);
        Factory::create('MerchantLanguage', ['language_id' => $this->enLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $mall_a->merchant_id]);
        $language_mall_a = [$this->enLang->language_id,$this->zhLang->language_id,$this->idLang->language_id];

        $mall_b = Factory::create('Mall', ['description' => 'mall irianto oke', 'mobile_default_language' => 'id']);
        Factory::create('MerchantLanguage', ['language_id' => $this->idLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->zhLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        Factory::create('MerchantLanguage', ['language_id' => $this->jpLang->language_id, 'merchant_id' => $mall_b->merchant_id]);
        $language_mall_b = [$this->idLang->language_id,$this->zhLang->language_id,$this->jpLang->language_id];

        // create campaign translation with mobile default language
        $news = Factory::create('News');
        $news_translation = Factory::create('NewsTranslation', [
                'news_id' => $news->news_id,
                'merchant_language_id' => $this->idLang->language_id
            ]);

        // create campaign link to mall
        $news_merchant = Factory::create('NewsMerchant', [
                    'news_id' => $news->news_id,
                    'merchant_id' => $mall_b->merchant_id,
                    'object_type' => 'mall'
                ]);

        $filter = [
            'with' => ['mallLanguages']
        ];

        /*
        * test get language and mobile default language link status
        */
        $response_search = $this->setRequestGetSearchMall($this->apiKey->api_key, $this->apiKey->api_secret_key, $filter);
        $this->assertSame(0, $response_search->code);
        $this->assertSame('success', $response_search->status);
        foreach ($response_search->data->records as $idx => $data) {
            if($mall_b->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall irianto oke', $data->description);
                foreach ($data->mall_languages as $language) {
                    $this->assertContains($language->language_id, $language_mall_b);
                }
                $this->assertSame('true', $data->disable_mobile_default_language);
            }
            if($mall_a->merchant_id  === $data->merchant_id) {
                $this->assertSame('mall antok bagus', $data->description);
                foreach ($data->mall_languages as $language) {
                    $this->assertContains($language->language_id, $language_mall_a);
                }
                $this->assertSame('false', $data->disable_mobile_default_language);
            }
        }
    }
}