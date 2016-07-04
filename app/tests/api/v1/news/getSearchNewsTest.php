<?php
/**
 * Unit test for NewsAPIController::getSearchNews(). Call to this
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;
use OrbitShop\API\v1\Helper\Generator;

class getSearchNewsTest extends TestCase
{
	private $baseUrl = '/api/v1/news/search';

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

        $this->campaing_status = Factory::create('CampaignStatus', ['campaign_status_name' => 'not started']);
        $this->news_1 = Factory::create('News', [
            'mall_id' => $this->mall_1->merchant_id,
            'object_type' => 'news',
            'campaign_status_id' => $this->campaing_status->campaign_status_id,
        ]);

        Factory::create('user_campaign_news', ['user_id' => $this->user_1->user_id, 'campaign_id' => $this->news_1->news_id]);
        Factory::create('CampaignAccount', ['user_id' => $this->user_1->user_id, 'parent_user_id' => NULL]);
        Factory::create('NewsMerchant', ['news_id' => $this->news_1->news_id, 'merchant_id' => $this->tenant_1->merchant_id]);
        Factory::create('NewsMerchant', ['news_id' => $this->news_1->news_id, 'merchant_id' => $this->tenant_2->merchant_id]);

        $this->news_2 = Factory::create('News', [
            'mall_id' => $this->mall_1->merchant_id,
            'object_type' => 'news',
            'campaign_status_id' => $this->campaing_status->campaign_status_id,
        ]);

        Factory::create('user_campaign_news', ['user_id' => $this->user_1->user_id, 'campaign_id' => $this->news_2->news_id]);
        Factory::create('NewsMerchant', ['news_id' => $this->news_2->news_id, 'merchant_id' => $this->tenant_2->merchant_id]);
        Factory::create('NewsMerchant', ['news_id' => $this->news_2->news_id, 'merchant_id' => $this->tenant_1->merchant_id]);

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

        Factory::create('NewsTranslation', [
            'news_id' => $this->news_1->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id,
            'news_name' => 'english name news 1',
            'description' => 'english description news 1'
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news_1->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id,
            'news_name' => 'japanese name news 1',
            'description' => 'japanese description news 1'
        ]);

        Factory::create('NewsTranslation', [
            'news_id' => $this->news_2->news_id, 
            'merchant_id' => $this->mall_1->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['english']->language_id,
            'news_name' => 'english name news 2',
            'description' => 'english description news 2'
        ]);
        Factory::create('NewsTranslation', [
            'news_id' => $this->news_2->news_id, 
            'merchant_id' => $this->mall_2->merchant_id,
            'merchant_language_id' => $this->merchantLanguages['japanese']->language_id,
            'news_name' => 'japanese name news 2',
            'description' => 'japanese description news 2'
        ]);

    	$_GET = [];
        $_POST = [];
    }

    private function makeRequest($nameLike = '')
    {
        $_GET = [
        	'news_name_like' => $nameLike,
            'apikey' => $this->apikey_user_1->api_key,
            'apitimestamp' => time()
        ];
        $_GET['object_type'][] = 'news';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey_user_1->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    public function testOK_news_list()
    {
        $response = $this->makeRequest();
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame('english name news 1', $response->data->records[0]->display_name);
        $this->assertSame('japanese name news 2', $response->data->records[1]->display_name);
    }

    public function testOK_news_list_filter_name()
    {
        $response = $this->makeRequest('japanese');
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame('japanese name news 1', $response->data->records[0]->display_name);
        $this->assertSame('japanese name news 2', $response->data->records[1]->display_name);
    }

    public function testOK_news_list_filter_name_same_in_two_translation()
    {
        $response = $this->makeRequest('name');
        $this->assertSame(2, $response->data->total_records);
        $this->assertContains($response->data->records[0]->display_name, ['english name news 1', 'japanese name news 1']);
        $this->assertContains($response->data->records[1]->display_name, ['english name news 2', 'japanese name news 2']);
    }
}