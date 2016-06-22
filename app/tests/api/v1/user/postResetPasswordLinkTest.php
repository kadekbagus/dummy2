<?php
/**
 * Test for API /api/v1/pub/user/reset-password-link
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class postResetPasswordLinkTest extends TestCase
{
    private $baseUrl = '/api/v1/pub/user/reset-password-link?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');
        $this->timezone = Factory::create('timezone_jakarta');
        $this->mall = Factory::create('Mall');
    }

    private function makeRequest($data, $authData = null)
    {
        $_POST = $data;
        $url = $this->baseUrl . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
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

    public function testEmailNotGiven()
    {
        // test email validation required
        $role = Factory::create('role_guest');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array();

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/email field is required/i', $response->message);
    }

    public function testNonRegisteredEmail()
    {
        // test email that not exist on the database
        $role = Factory::create('role_guest');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('email' => 'test@test.com');

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/We couldn\'t find an account associated with/i', $response->message);
    }

    public function testEmailValidAndRegistered()
    {
        // test email that exist on the database
        $role = Factory::create('role_consumer');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('email' => $user->user_email);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);
        $this->assertSame(null, $response->data);
    }

    public function testCheckTokenData()
    {
        // test email that exist on the database
        $role = Factory::create('role_consumer');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('email' => $user->user_email);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);
        $this->assertSame(null, $response->data);

        // should be only one token on the database, right?
        $token = Token::where('email', '=', $user->user_email)->first();

        $this->assertSame(1, count($token));
        $this->assertSame('reset_password', $token->token_name);
        $this->assertInternalType('string', $token->token_value);
        $this->assertSame('active', $token->status);
        $this->assertSame($user->user_email, $token->email);
        $this->assertSame($user->user_id, $token->user_id);
    }
}