<?php
/**
 * PHP Unit Test for Category Controller getSearchCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postNewEventTest extends TestCase {

    private $baseUrl = '/api/v1/event/new';

    public function setUp()
    {
        parent::setUp();

        $english = Factory::create('Language', ['name' => 'English']);
        $chinese = Factory::create('Language', ['name' => 'Chinese']);
        $french = Factory::create('Language', ['name' => 'French']);
        $balinese = Factory::create('Language', ['name' => 'Balinese']);

        $this->group = $merchant = Factory::create('Merchant');
        $this->unrelatedGroup = $unrelatedMerchant = Factory::create('Merchant');

        $owner_role = Factory::create('Role', ['role_name' => 'mall owner']);

        $owner_user = Factory::create('User', ['user_role_id' => $owner_role->role_id]);

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes', 'user_id' => $owner_user->user_id]);
        $this->unrelatedMall = Factory::create('Retailer', ['is_mall' => 'yes', 'parent_id' => $this->unrelatedGroup->merchant_id]);

        $setting = new Setting();
        $setting->setting_name = 'current_retailer';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'create_event']);

        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $owner_user->user_id]);
        $this->userId = $owner_user->user_id;

        $this->authData = Factory::create('apikey_super_admin');
        $this->events   = Factory::times(3)->create("EventModel");

        $combos = [
            [$this->mall, $english, 'english'],
            [$this->mall, $french, 'french'],
            [$this->mall, $balinese, 'deleted_balinese'],
            [$this->unrelatedMall, $balinese, 'balinese'],
            [$this->unrelatedMall, $chinese, 'chinese']
        ];
        $merchant_languages = [];
        foreach ($combos as $combo) {
            $lang = new MerchantLanguage();
            $lang->merchant_id = $combo[0]->merchant_id;
            $lang->language_id = $combo[1]->language_id;
            $lang->save();
            $merchant_languages[$combo[2]] = $lang;
        }
        $merchant_languages['deleted_balinese']->delete();

        $this->merchantLanguages = $merchant_languages;
    }

    private function createEventData()
    {
        $faker = Faker\Factory::create();
        return Factory::attributesFor('EventModel', [
            'merchant_id' => $this->mall->merchant_id,
        ]);
    }

    public function testOK_post_new_event_with_more_than_one_link_id()
    {
        $event = $this->createEventData();

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST = array_merge($event, [
            'id_language_default' => $this->merchantLanguages['english']->merchant_language_id,
        ]);

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }

    public function testOK_post_new_category_for_owned_merchant()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id']    = $this->mall->merchant_id;
        $_POST['event_name']     = 'Unique Submitted Event';
        $_POST['event_type']     = 'link';
        $_POST['status']         = 'active';
        $_POST['description']    = 'Description for event here';

        $_POST['id_language_default'] = $this->merchantLanguages['english']->merchant_language_id;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }
}
