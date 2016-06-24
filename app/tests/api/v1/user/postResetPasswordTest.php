<?php
/**
 * Test for API /api/v1/pub/user/reset-password-link
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class postResetPassword extends TestCase
{
    private $baseUrl  = '/api/v1/pub/user/reset-password?';
    private $baseUrl2 = '/api/v1/pub/user/reset-password-link?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');
        $this->timezone = Factory::create('timezone_jakarta');
        $this->mall = Factory::create('Mall');
    }

    private function makeRequest($data, $url2 = false)
    {
        $_POST = $data;
        if ($url2) {
            $url = $this->baseUrl2 . http_build_query($_GET);
        } else {
            $url = $this->baseUrl . http_build_query($_GET);
        }
        $secretKey = null;//$authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testNoInputParameter()
    {
        // send nothing
        $data = array();

        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/token value field is required/i', $response->message);
    }

    public function testInvalidToken()
    {
        // test invalid token
        $data = array('token' => '123456');

        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Token you specified is not found/i', $response->message);
    }

    public function testValidToken()
    {
        // test valid token but no password
        $role = Factory::create('role_consumer');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('email' => $user->user_email);

        $response = $this->makeRequest($data, true);

        $token = Token::first();

        $data = array('token' => $token->token_value);
        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/password field is required/i', $response->message);
    }

    public function testValidTokenWithPassword()
    {
        // test valid token but no password
        $role = Factory::create('role_consumer');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('email' => $user->user_email);

        $response = $this->makeRequest($data, true);

        $token = Token::first();

        $data = array('token' => $token->token_value, 'password' => '123456', 'password_confirmation' => '123456');
        $response = $this->makeRequest($data);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Your password has been successfully updated./i', $response->message);

        $this->assertSame($user->user_id, $response->data->user_id);
        $this->assertSame($user->user_email, $response->data->user_email);
        $this->assertSame($user->user_firstname, $response->data->user_firstname);
        $this->assertSame($user->user_lastname, $response->data->user_lastname);
    }

    public function testPasswordConfirmationNotSend()
    {
        // test email that exist on the database
        $role = Factory::create('role_consumer');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('email' => $user->user_email);

        $response = $this->makeRequest($data, true);

        $token = Token::first();

        $data = array('token' => $token->token_value, 'password' => '123456');
        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/password confirmation does not match/i', $response->message);
    }
}