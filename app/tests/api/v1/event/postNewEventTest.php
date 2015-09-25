<?php
/**
 * PHP Unit Test for Category Controller getSearchCategory
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 * note: can't do this test if merchant language table is empty
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postNewEventTest extends TestCase {

    private $baseUrl = '/api/v1/event/new';

    public function setUp()
    {
        parent::setUp();

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
    }

    public function testOK_post_new_event_with_more_than_one_link_id()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id']    = $this->mall->merchant_id;
        $_POST['event_name']     = 'Unique Submitted Event';
        $_POST['event_type']     = 'link';
        $_POST['status']         = 'active';
        $_POST['description']    = 'Description for event here';

        $_POST['id_language_default'] = 1;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        dd($response);
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

        $_POST['id_language_default'] = 1;

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
