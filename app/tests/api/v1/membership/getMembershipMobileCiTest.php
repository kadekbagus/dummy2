<?php
/**
 * Test for API /api/v1/cust/membership
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class getMembershipCITest extends TestCase
{
    private $baseUrl = '/api/v1/cust/membership?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');

        $this->timezone = Factory::create('timezone_jakarta');

        $this->mallA = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);
        $this->mallB = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id]);

        $this->user = Factory::create('user_guest');

        $this->userdetail = Factory::create('UserDetail', [
            'user_id' => $this->user->user_id,
            'gender'  => null,
        ]);
        $this->apikey = Factory::create('Apikey', [
            'user_id' => $this->user->user_id
        ]);
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }

        $_GET = array_merge($data, [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);

        $_GET['apikey'] = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST = [];
        $url = $this->baseUrl . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testNotAllowedUserRole()
    {
        // only super admin, guest and consumer can access the API
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $response = $this->makeRequest([], $apikey);

        $this->assertSame(13, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Your role are not allowed to access this resource/i', $response->message);
    }

    public function testAllowedUserRole()
    {
        // only super admin, guest and consumer can access the API
        $authData = Factory::create('Apikey');
        $response = $this->makeRequest([], $authData);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/mall id field is required/i', $response->message);
    }

    public function testInvalidMallId()
    {
        // test wrong mall id
        $authData = Factory::create('Apikey');
        $data = array('mall_id' => '123213');
        $response = $this->makeRequest($data, $authData);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Mall ID you specified is not found/i', $response->message);
    }

    public function testMembershipDisabled()
    {
        // test membership disabled 
        $mall = Factory::create('Mall');
        $setting = Factory::create('enable_membership_card', ['object_id' => $mall->merchant_id, 'setting_value' => 'false']);
        $data = array('mall_id' => $mall->merchant_id);

        $authData = Factory::create('Apikey');
        $response = $this->makeRequest($data, $authData);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('false', $response->data->membership_enable);
        $this->assertSame(null, $response->data->membership_data);
    }

    public function testMembershipEnabledUserGuest()
    {
        // test membership enabled with user guest login
        $mall = Factory::create('Mall');
        $setting = Factory::create('enable_membership_card', ['object_id' => $mall->merchant_id]);
        $data = array('mall_id' => $mall->merchant_id);

        $authData = Factory::create('Apikey');
        $response = $this->makeRequest($data, $authData);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('true', $response->data->membership_enable);
        $this->assertSame(null, $response->data->membership_data);
    }

    public function testMembershipEnabledUserCustomerNoMembership()
    {
        // test membership enabled with user customer login but no membership data
        $mall = Factory::create('Mall');
        $setting = Factory::create('enable_membership_card', ['object_id' => $mall->merchant_id]);
        $data = array('mall_id' => $mall->merchant_id);

        $customer1 = Factory::create('user_consumer');
        $apikey = Factory::create('Apikey', ['user_id' => $customer1->user_id]);
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('true', $response->data->membership_enable);
        $this->assertSame(null, $response->data->membership_data);
    }

    public function testMembershipEnabledUserCustomerWithMembership()
    {
        // test membership enabled with user customer login with membership data
        $mall = Factory::create('Mall');
        $setting = Factory::create('enable_membership_card', ['object_id' => $mall->merchant_id]);
        $customer1 = Factory::create('user_consumer');
        $apikey = Factory::create('Apikey', ['user_id' => $customer1->user_id]);
        $membership = Factory::create('Membership', ['merchant_id' => $mall->merchant_id]);
        $membershipNumber = Factory::create('MembershipNumber', ['user_id' => $customer1->user_id, 'membership_id' => $membership->membership_id]);

        $data = array('mall_id' => $mall->merchant_id);
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('true', $response->data->membership_enable);
        $this->assertSame($customer1->user_id, $response->data->membership_data->user_id);
        $this->assertSame($customer1->user_firstname, $response->data->membership_data->user_firstname);
        $this->assertSame($customer1->user_lastname, $response->data->membership_data->user_lastname);
        $this->assertSame($membershipNumber->membership_number, (int)$response->data->membership_data->membership_number);
    }

    public function testGetMembershipWithMedia()
    {
        // test membership with media
        $mall = Factory::create('Mall');
        $setting = Factory::create('enable_membership_card', ['object_id' => $mall->merchant_id]);
        $customer1 = Factory::create('user_consumer');
        $apikey = Factory::create('Apikey', ['user_id' => $customer1->user_id]);
        $membership = Factory::create('Membership', ['merchant_id' => $mall->merchant_id]);
        $membershipNumber = Factory::create('MembershipNumber', ['user_id' => $customer1->user_id, 'membership_id' => $membership->membership_id]);
        $media = Factory::create('Media', ['media_name_id' => 'membership_image', 
                                           'object_id' => $membership->membership_id, 
                                           'object_name' => 'membership', 
                                           'media_name_long' => 'membership_image_orig', 
                                           'path' => '/uploads/membership/2123.png']);

        $data = array('mall_id' => $mall->merchant_id);
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('true', $response->data->membership_enable);
        $this->assertSame($customer1->user_id, $response->data->membership_data->user_id);
        $this->assertSame($customer1->user_firstname, $response->data->membership_data->user_firstname);
        $this->assertSame($customer1->user_lastname, $response->data->membership_data->user_lastname);
        $this->assertSame($membershipNumber->membership_number, (int)$response->data->membership_data->membership_number);
        $this->assertSame($membership->membership_name, $response->data->membership_data->membership_name);
        $this->assertSame($media->path, $response->data->membership_data->path);
    }

    public function testMembershipMoreThanOne()
    {
        // right now one user have one membership on the same mall
        // this test is for user with more than one membership but on different mall
        $mall1 = Factory::create('Mall');
        $mall2 = Factory::create('Mall');

        $setting1 = Factory::create('enable_membership_card', ['object_id' => $mall1->merchant_id]);
        $setting2 = Factory::create('enable_membership_card', ['object_id' => $mall2->merchant_id]);

        $customer1 = Factory::create('user_consumer');
        $apikey = Factory::create('Apikey', ['user_id' => $customer1->user_id]);
        $membership1 = Factory::create('Membership', ['merchant_id' => $mall1->merchant_id]);
        $membership2 = Factory::create('Membership', ['merchant_id' => $mall2->merchant_id]);

        $membershipNumber1 = Factory::create('MembershipNumber', ['user_id' => $customer1->user_id, 'membership_id' => $membership1->membership_id]);
        $membershipNumber2 = Factory::create('MembershipNumber', ['user_id' => $customer1->user_id, 'membership_id' => $membership2->membership_id]);

        $media1 = Factory::create('Media', ['media_name_id' => 'membership_image', 
                                            'object_id' => $membership1->membership_id, 
                                            'object_name' => 'membership', 
                                            'media_name_long' => 'membership_image_orig', 
                                            'path' => '/uploads/membership/mall1.png']);

        $media2 = Factory::create('Media', ['media_name_id' => 'membership_image', 
                                            'object_id' => $membership2->membership_id, 
                                            'object_name' => 'membership', 
                                            'media_name_long' => 'membership_image_orig', 
                                            'path' => '/uploads/membership/mall2.png']);

        // should return membership from mall1
        $data = array('mall_id' => $mall1->merchant_id);
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('true', $response->data->membership_enable);
        $this->assertSame($customer1->user_id, $response->data->membership_data->user_id);
        $this->assertSame($customer1->user_firstname, $response->data->membership_data->user_firstname);
        $this->assertSame($customer1->user_lastname, $response->data->membership_data->user_lastname);
        $this->assertSame($membershipNumber1->membership_number, (int)$response->data->membership_data->membership_number);
        $this->assertSame($media1->path, $response->data->membership_data->path);

        // should return membership from mall2
        $data = array('mall_id' => $mall2->merchant_id);
        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $this->assertSame('true', $response->data->membership_enable);
        $this->assertSame($customer1->user_id, $response->data->membership_data->user_id);
        $this->assertSame($customer1->user_firstname, $response->data->membership_data->user_firstname);
        $this->assertSame($customer1->user_lastname, $response->data->membership_data->user_lastname);
        $this->assertSame($membershipNumber2->membership_number, (int)$response->data->membership_data->membership_number);
        $this->assertSame($media2->path, $response->data->membership_data->path);
    }
}