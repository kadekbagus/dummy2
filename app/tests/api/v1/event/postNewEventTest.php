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

        $this->authData = Factory::create('apikey_super_admin');
        $this->events   = Factory::times(3)->create("EventModel");
    }

    public function testOK_post_new_event_with_more_than_one_link_id()
    {
        $merchant = Factory::create('Merchant');

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id']    = $merchant->merchant_id;
        $_POST['event_name']     = 'Unique Submitted Event';
        $_POST['event_type']     = 'link';
        $_POST['status']         = 'active';
        $_POST['description']    = 'Description for event here';

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
        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'create_event']);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);

        Factory::create('PermissionRole', ['role_id' => $user->user_role_id, 'permission_id' => $permission->permission_id]);

        $_GET['apikey']       = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id']    = $merchant->merchant_id;
        $_POST['event_name']     = 'Unique Submitted Event';
        $_POST['event_type']     = 'link';
        $_POST['status']         = 'active';
        $_POST['description']    = 'Description for event here';

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $authData->api_secret_key;
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
