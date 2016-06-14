<?php
/**
 * Test for API /api/v1/activity/today-statistics
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class getCustomerTodayDashboardTest extends TestCase
{
    private $baseUrl = '/api/v1/activity/today-statistics?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');
        $this->timezone = Factory::create('timezone_jakarta');
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
        $role = Factory::create('role_guest');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $response = $this->makeRequest([], $apikey);
        $this->assertSame(13, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Your role are not allowed to access this resource/i', $response->message);
    }

    public function testAllowedUserRole()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $response = $this->makeRequest([], $apikey);
        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/start date field is required/i', $response->message);
    }

    public function testStartDateGiven()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('start_date' => '2016-06-07 17:00:00');

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/end date field is required/i', $response->message);
    }

    public function testStartAndEndDateGiven()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('start_date' => '2016-06-07 17:00:00', 'end_date' => '2016-06-08 16:59:59');

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
    }

    public function testEmptyData()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);
        $data = array('start_date' => '2016-06-07 17:00:00', 'end_date' => '2016-06-08 16:59:59', 'current_mall' => $mall->merchant_id);

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
        
        $this->assertSame($data['start_date'], $response->data->start_date);
        $this->assertSame($data['end_date'], $response->data->end_date);
        $this->assertSame(0, $response->data->today[0]->count);
        $this->assertSame(0, $response->data->today[0]->customer_count);
        $this->assertSame(0, $response->data->today[0]->guest_count);
    }

    public function testOneGuestSignIn()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $guest1 = Factory::create('user_guest');

        $userSignin = Factory::create('UserSignin', ['user_id' => $guest1->user_id, 'signin_via' => 'guest', 'location_id' => $mall->merchant_id]);
        $data = array('start_date' => '2016-06-06 00:00:00', 'end_date' => '2016-06-09 23:59:59', 'current_mall' => $mall->merchant_id);

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
        
        $this->assertSame($data['start_date'], $response->data->start_date);
        $this->assertSame($data['end_date'], $response->data->end_date);
        $this->assertSame('1', $response->data->today[0]->count);
        $this->assertSame('0', $response->data->today[0]->customer_count);
        $this->assertSame('1', $response->data->today[0]->guest_count);
    }

    public function testTwoGuestSignIn()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $guest1 = Factory::create('user_guest');
        $guest2 = Factory::create('user_guest');

        $userSignin1 = Factory::create('UserSignin', ['user_id' => $guest1->user_id, 'signin_via' => 'guest', 'location_id' => $mall->merchant_id]);
        $userSignin2 = Factory::create('UserSignin', ['user_id' => $guest2->user_id, 'signin_via' => 'guest', 'location_id' => $mall->merchant_id]);

        $data = array('start_date' => '2016-06-06 00:00:00', 'end_date' => '2016-06-09 23:59:59', 'current_mall' => $mall->merchant_id);

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
        
        $this->assertSame($data['start_date'], $response->data->start_date);
        $this->assertSame($data['end_date'], $response->data->end_date);
        $this->assertSame('2', $response->data->today[0]->count);
        $this->assertSame('0', $response->data->today[0]->customer_count);
        $this->assertSame('2', $response->data->today[0]->guest_count);
    }

    public function testOneCustomerSignIn()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $customer1 = Factory::create('user_consumer');

        $userSignin1 = Factory::create('UserSignin', ['user_id' => $customer1->user_id, 'signin_via' => 'form', 'location_id' => $mall->merchant_id]);

        $data = array('start_date' => '2016-06-06 00:00:00', 'end_date' => '2016-06-09 23:59:59', 'current_mall' => $mall->merchant_id);

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
        
        $this->assertSame($data['start_date'], $response->data->start_date);
        $this->assertSame($data['end_date'], $response->data->end_date);
        $this->assertSame('1', $response->data->today[0]->count);
        $this->assertSame('1', $response->data->today[0]->customer_count);
        $this->assertSame('0', $response->data->today[0]->guest_count);
    }

    public function testTwoCustomerSignIn()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $customer1 = Factory::create('user_consumer');
        $customer2 = Factory::create('user_consumer');

        $userSignin1 = Factory::create('UserSignin', ['user_id' => $customer1->user_id, 'signin_via' => 'form', 'location_id' => $mall->merchant_id]);
        $userSignin2 = Factory::create('UserSignin', ['user_id' => $customer2->user_id, 'signin_via' => 'form', 'location_id' => $mall->merchant_id]);

        $data = array('start_date' => '2016-06-06 00:00:00', 'end_date' => '2016-06-09 23:59:59', 'current_mall' => $mall->merchant_id);

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
        
        $this->assertSame($data['start_date'], $response->data->start_date);
        $this->assertSame($data['end_date'], $response->data->end_date);
        $this->assertSame('2', $response->data->today[0]->count);
        $this->assertSame('2', $response->data->today[0]->customer_count);
        $this->assertSame('0', $response->data->today[0]->guest_count);
    }

    public function testTwoCustomerAndOneGuestSignIn()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $customer1 = Factory::create('user_consumer');
        $customer2 = Factory::create('user_consumer');
        $guest1 = Factory::create('user_guest');

        $userSignin1 = Factory::create('UserSignin', ['user_id' => $customer1->user_id, 'signin_via' => 'form', 'location_id' => $mall->merchant_id]);
        $userSignin2 = Factory::create('UserSignin', ['user_id' => $customer2->user_id, 'signin_via' => 'form', 'location_id' => $mall->merchant_id]);
        $userSignin3 = Factory::create('UserSignin', ['user_id' => $guest1->user_id, 'signin_via' => 'guest', 'location_id' => $mall->merchant_id]);

        $data = array('start_date' => '2016-06-06 00:00:00', 'end_date' => '2016-06-09 23:59:59', 'current_mall' => $mall->merchant_id);

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
        
        $this->assertSame($data['start_date'], $response->data->start_date);
        $this->assertSame($data['end_date'], $response->data->end_date);
        $this->assertSame('3', $response->data->today[0]->count);
        $this->assertSame('2', $response->data->today[0]->customer_count);
        $this->assertSame('1', $response->data->today[0]->guest_count);
    }
}