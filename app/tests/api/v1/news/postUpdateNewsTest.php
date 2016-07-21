<?php
/**
 * PHP Unit Test for Category Controller getSearchCategory
 *
 * @author: Shelgi Prasetyo <shelgi@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateNewsTest extends TestCase {

    private $baseUrl = '/api/v1/news/update';

    public function setUp()
    {
        parent::setUp();
        $faker = Faker::create();
        $english = Factory::create('Language', ['name' => 'English', 'name' => 'en']);
        $chinese = Factory::create('Language', ['name' => 'Chinese', 'name' => 'ch']);
        $indonesia = Factory::create('Language', ['name' => 'Indonesia', 'name' => 'id']);
        $japanese = Factory::create('Language', ['name' => 'Japanese', 'name' => 'jp']);

        $role = Factory::create('role_campaign_owner');

        $this->user_1 = Factory::create('User', ['user_role_id' => $role->role_id]);
        $this->apikey_user_1 = Factory::create('Apikey', ['user_id' => $this->user_1->user_id]);

        $this->mall_1 = Factory::create('Mall', ['mobile_default_language' => 'en']);
        $this->mall_2 = Factory::create('Mall', ['mobile_default_language' => 'jp']);

        $permission = Factory::create('Permission', ['permission_name' => 'update_news']);

        Factory::create('PermissionRole',
            ['role_id' => $this->mall_1->user->user_role_id, 'permission_id' => $permission->permission_id]);


        $this->tenant_1 = Factory::create('tenant_store', [
            'parent_id' => $this->mall_1->merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);

        $this->tenant_2 = Factory::create('tenant_store', [
            'parent_id' => $this->mall_2->merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);

        Factory::create('UserMerchant', ['user_id' => $this->user_1->user_id, 'merchant_id' => $this->tenant_1->merchant_id, 'object_type' => 'tenant']);
        Factory::create('UserMerchant', ['user_id' => $this->user_1->user_id, 'merchant_id' => $this->tenant_2->merchant_id, 'object_type' => 'tenant']);

        $combos = [
            [$this->mall_1, $english, 'english'],
            [$this->mall_1, $chinese, 'chinese'],
            [$this->mall_2, $indonesia, 'indonesia'],
            [$this->mall_2, $japanese, 'japanese']
        ];
        $merchant_languages = [];
        foreach ($combos as $combo) {
            $lang = new MerchantLanguage();
            $lang->merchant_id = $combo[0]->merchant_id;
            $lang->language_id = $combo[1]->language_id;
            $lang->save();
            $merchant_languages[$combo[2]] = $lang;
        }
        $this->merchantLanguages = $merchant_languages;

        $campaignStatus = [
            ['not started', 2],
            ['ongoing', 3],
            ['paused', 4],
            ['stopped', 5],
            ['expired', 1],
        ];
        $campaign_status = [];
        foreach ($campaignStatus as $status) {
            $cs = new CampaignStatus();
            $cs->campaign_status_name = $status[0];
            $cs->order = $status[0];
            $cs->save();
        }

        $this->campaing_status = Factory::create('CampaignStatus', ['campaign_status_name' => 'not started']);
        $this->news = Factory::create('News', [
            'mall_id' => $this->mall_1->merchant_id,
            'object_type' => 'news',
            'campaign_status_id' => $this->campaing_status->campaign_status_id,
        ]);

        Factory::create('user_campaign_news', ['user_id' => $this->user_1->user_id, 'campaign_id' => $this->news->news_id]);
        Factory::create('CampaignAccount', ['user_id' => $this->user_1->user_id, 'parent_user_id' => NULL]);
        Factory::create('NewsMerchant', ['news_id' => $this->news->news_id, 'merchant_id' => $this->tenant_1->merchant_id]);
        Factory::create('NewsMerchant', ['news_id' => $this->news->news_id, 'merchant_id' => $this->tenant_2->merchant_id]);

        $_GET = [];
        $_POST = [];
    }

    private function makeRequest($tenants, $translations)
    {
        $_GET = [
            'apikey' => $this->apikey_user_1->api_key,
            'apitimestamp' => time(),
        ];

        $_POST['sticky_order'] = 'false';
        $_POST['is_popup'] = 'N';
        $_POST['begin_date_hour'] = '00';
        $_POST['begin_date_minute'] = '00';
        $_POST['end_date_hour'] = '23';
        $_POST['end_date_minute'] = '59';
        $_POST['id_language_default'] = $this->merchantLanguages['english']->language_id;
        $_POST['begin_date'] = '2017-01-01 00:00:00';
        $_POST['end_date'] = '2017-01-31 23:59:00';
        $_POST['news_name'] = Faker::create()->sentence(3);
        $_POST['description'] = Faker::create()->sentence(3);
        $_POST['rule_value'] = '0';
        $_POST['link_object_type'] = 'tenant';
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['is_all_gender'] = 'Y';
        $_POST['is_all_age'] = 'Y';
        $_POST['current_mall'] = $this->mall_1->merchant_id;
        $_POST['translations'] = json_encode($translations);
        $_POST['news_id'] = $this->news->news_id;
        $_POST['object_type'] = 'news';
        $_POST['status'] = 'active';
        $_POST['campaign_status'] = 'not started';

        foreach ($tenants as $tenant) {
            $_POST['retailer_ids'][] = json_encode($tenant);
        }

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey_user_1->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testOK_update_news_with_two_tenant()
    {
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id
        ]);

        $translations_detil_1 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations_detil_2 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_1,
            $this->merchantLanguages['japanese']->language_id => $translations_detil_2,
        ];

        $linkTo1 = [
            'tenant_id' => $this->tenant_1->merchant_id,
            'mall_id' => $this->tenant_1->parent_id,
        ];
        $tenants = array($linkTo1);
        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(1, count($response->data));
    }

    public function testOK_update_news_with_two_tenant_input_desc_default_language()
    {
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['indonesia']->language_id
        ]);

        $translations_detil_1 = [
            'news_name' => '',
            'description' => Faker::create()->sentence(3),
        ];
        $translations_detil_2 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations_detil_3 = [
            'news_name' => '',
            'description' => Faker::create()->sentence(3),
        ];
        $translations_detil_4 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_1,
            $this->merchantLanguages['indonesia']->language_id => $translations_detil_2,
        ];

        $linkTo1 = [
            'tenant_id' => $this->tenant_1->merchant_id,
            'mall_id' => $this->tenant_1->parent_id,
        ];
        $linkTo2 = [
            'tenant_id' => $this->tenant_2->merchant_id,
            'mall_id' => $this->tenant_2->parent_id,
        ];
        $tenants = array($linkTo1, $linkTo2);
        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(14, $response->code);
        $this->assertSame('news default name is required', strtolower($response->message));

        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_4,
            $this->merchantLanguages['japanese']->language_id => $translations_detil_3,
        ];

        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(14, $response->code);
        $this->assertSame('news default name is required', strtolower($response->message));
    }

    public function testOK_update_news_with_two_tenant_input_name_default_language()
    {
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['indonesia']->language_id
        ]);

        $translations_detil_1 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => '',
        ];
        $translations_detil_2 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations_detil_3 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => '',
        ];
        $translations_detil_4 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_1,
            $this->merchantLanguages['indonesia']->language_id => $translations_detil_2,
        ];

        $linkTo1 = [
            'tenant_id' => $this->tenant_1->merchant_id,
            'mall_id' => $this->tenant_1->parent_id,
        ];
        $linkTo2 = [
            'tenant_id' => $this->tenant_2->merchant_id,
            'mall_id' => $this->tenant_2->parent_id,
        ];
        $tenants = array($linkTo1, $linkTo2);
        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(14, $response->code);
        $this->assertSame('default description is required', strtolower($response->message));

        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_4,
            $this->merchantLanguages['japanese']->language_id => $translations_detil_3,
        ];

        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(14, $response->code);
        $this->assertSame('default description is required', strtolower($response->message));
    }

    public function testOK_update_news_with_two_tenant_input_name_and_desc_other_language()
    {
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['chinese']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['indonesia']->language_id
        ]);

        $translations_detil_1 = [
            'news_name' => '',
            'description' => '',
        ];
        $translations_detil_2 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations_detil_3 = [
            'news_name' => '',
            'description' => '',
        ];
        $translations_detil_4 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_1,
            $this->merchantLanguages['indonesia']->language_id => $translations_detil_2,
        ];

        $linkTo1 = [
            'tenant_id' => $this->tenant_1->merchant_id,
            'mall_id' => $this->tenant_1->parent_id,
        ];
        $linkTo2 = [
            'tenant_id' => $this->tenant_2->merchant_id,
            'mall_id' => $this->tenant_2->parent_id,
        ];
        $tenants = array($linkTo1, $linkTo2);
        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(14, $response->code);
        $this->assertSame('news default name and description is required', strtolower($response->message));

        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_4,
            $this->merchantLanguages['japanese']->language_id => $translations_detil_3,
        ];

        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(14, $response->code);
        $this->assertSame('news default name and description is required', strtolower($response->message));
    
    }

    public function testOK_update_news_with_same_translation()
    {
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['chinese']->language_id
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['indonesia']->language_id
        ]);

        $translations_detil_1 = [
            'news_name' => 'aaa',
            'description' => 'aaa',
        ];
        $translations_detil_2 = [
            'news_name' => 'bbb',
            'description' => 'bbb',
        ];
        $translations_detil_3 = [
            'news_name' => '',
            'description' => '',
        ];
        $translations_detil_4 = [
            'news_name' => Faker::create()->sentence(3),
            'description' => Faker::create()->sentence(3),
        ];
        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_1,
            $this->merchantLanguages['indonesia']->language_id => $translations_detil_2,
        ];

        $linkTo1 = [
            'tenant_id' => $this->tenant_1->merchant_id,
            'mall_id' => $this->tenant_1->parent_id,
        ];
        $linkTo2 = [
            'tenant_id' => $this->tenant_2->merchant_id,
            'mall_id' => $this->tenant_2->parent_id,
        ];
        $tenants = array($linkTo1, $linkTo2);
        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(0, $response->code);
        $this->assertSame('Request OK', $response->message);

        $translations = [
            $this->merchantLanguages['english']->language_id => $translations_detil_1,
            $this->merchantLanguages['japanese']->language_id => $translations_detil_2,
        ];

        $response = $this->makeRequest($tenants, $translations);

        $this->assertSame(0, $response->code);
        $this->assertSame('Request OK', $response->message);
    
    }
}
