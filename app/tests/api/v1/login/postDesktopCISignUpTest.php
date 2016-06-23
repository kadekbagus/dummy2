<?php
/**
 * Unit test for API app/v1/pub/login/customer/desktop
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postDesktopCILoginTest extends TestCase
{
    protected $sessionId = 1;
    protected $sessData = NULL;
    protected $apiUrl = '/app/v1/pub/login/customer/desktop';

    public function setUp()
    {
        parent::setUp();
        $data = new stdClass();
        $data->id = $this->sessionId;
        $data->createdAt = strtotime('yesterday');
        $data->lastActivityAt = strtotime('1 minutes ago');
        $data->expireAt = strtotime('1 minutes');
        $data->userAgent = 'Orbit/2.2';
        $data->ipAddress = '127.0.0.1';
        $value = new stdClass();
        $value->user_id = NULL;
        $value->logged_in = FALSE;
        $data->value = $value;
        $this->sessData = $data;
        $guest_role = Factory::create('role_guest');
        $consumer_role = Factory::create('role_consumer');
        $this->guest_user = Factory::create('user_guest', [
            'user_role_id' => $guest_role->role_id
        ]);

        $this->password = 'PasswordOkePakDhe';

        $this->mall_1 = Factory::create('Mall');

        $this->user_data = [
            'email' => 'will.smith@gmail.com',
            'password' => $this->password,
            'password_confirmation' => $this->password,
            'first_name' => 'Will',
            'last_name' => 'Smith',
            'gender' => 'm',
            'birthdate' => '18-12-1972',
        ];

        $_GET = [];
        $_POST = [];
    }

    public function testOKpostDesktopCISignUp()
    {
        $this->sessionId = time();
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = $this->user_data['email'];
        $_POST['password'] = $this->user_data['password'];
        $_POST['password_confirmation'] = $this->user_data['password_confirmation'];
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);

        // check all returned records
        $this->assertSame($this->user_data['first_name'], $response->data->user_firstname);
        $this->assertSame($this->user_data['last_name'], $response->data->user_lastname);
        $this->assertSame($this->user_data['email'], $response->data->user_email);
        // check Inbox
        $inbox = \Inbox::where('user_id', $response->data->user_id)
            ->where('inbox_type', 'activation')
            ->first();
        $this->assertSame($this->mall_1->merchant_id, $inbox->merchant_id);
    }

    public function testFailpostDesktopCISignUpMallIDEmpty()
    {
        $this->sessionId = '2';
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = $this->user_data['email'];
        $_POST['password'] = $this->user_data['password'];
        $_POST['password_confirmation'] = $this->user_data['password_confirmation'];
        $_POST['mall_id'] = '';
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The mall id field is required', $response->message);
    }

    public function testFailpostDesktopCISignUpMallIDNotExist()
    {
        $this->sessionId = '3';
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = $this->user_data['email'];
        $_POST['password'] = $this->user_data['password'];
        $_POST['password_confirmation'] = $this->user_data['password_confirmation'];
        $_POST['mall_id'] = '1' . $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The Mall ID you specified is not found', $response->message);
    }

    public function testFailpostDesktopCISignUpEmailEmpty()
    {
        $this->sessionId = '4';
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = '';
        $_POST['password'] = $this->user_data['password'];
        $_POST['password_confirmation'] = $this->user_data['password_confirmation'];
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The email field is required', $response->message);
    }

    public function testFailpostDesktopCISignUpEmailAlreadyExist()
    {
        $this->sessionId = '5';
        $this->createSessionForUser($this->guest_user, TRUE);
        $existing_user = Factory::create('user_consumer');
        $_POST['email'] = $existing_user->user_email;
        $_POST['password'] = $this->user_data['password'];
        $_POST['password_confirmation'] = $this->user_data['password_confirmation'];
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('Email address has already been taken', $response->message);
    }

    public function testFailpostDesktopCISignUpEmptyPassword()
    {
        $this->sessionId = '6';
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = $this->user_data['email'];
        $_POST['password'] = '';
        $_POST['password_confirmation'] = $this->user_data['password_confirmation'];
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The password field is required', $response->message);
    }

    public function testFailpostDesktopCISignUpPasswordEmpty()
    {
        $this->sessionId = '7';
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = $this->user_data['email'];
        $_POST['password'] = $this->user_data['password_confirmation'];
        $_POST['password_confirmation'] = '';
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The password confirmation field is required', $response->message);
    }

    public function testFailpostDesktopCISignUpPasswordMissmatch()
    {
        $this->sessionId = '8';
        $this->createSessionForUser($this->guest_user, TRUE);
        $_POST['email'] = $this->user_data['email'];
        $_POST['password'] = $this->user_data['password_confirmation'];
        $_POST['password_confirmation'] = '123456';
        $_POST['mall_id'] = $this->mall_1->merchant_id;
        $_POST['first_name'] = $this->user_data['first_name'];
        $_POST['last_name'] = $this->user_data['last_name'];
        $_POST['gender'] = $this->user_data['gender'];
        $_POST['birthdate'] = $this->user_data['birthdate'];

        $_POST['mode'] = 'registration';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The password confirmation does not match', $response->message);
    }

    protected function createSessionForUser($user, $guest = FALSE)
    {
        $this->sessData->value = [
            'logged_in' => TRUE,
            'user_id'   => $user->user_id,
            'email'     => $user->user_email,
            'role'      => $user->role->role_name,
            'fullname'  => $user->getFullName()
        ];
        if ($guest) {
            unset($this->sessData->value['user_id']);
            unset($this->sessData->value['email']);
            $this->sessData->value = [
                'logged_in' => TRUE,
                'guest_user_id' => $user->user_id,
                'guest_email' => $user->user_email,
                'role'      => $user->role->role_name,
                'fullname'  => ''
            ];
        }
        $sessionData = [
            'session_id' => $this->sessionId,
            'session_data' => serialize($this->sessData),
            'last_activity' => $this->sessData->expireAt,
            'application_id' => 1
        ];
        DB::table('sessions')->insert($sessionData);
        // Set the orbit_session on the query string
        $_SERVER['HTTP_X_ORBIT_SESSION'] = $this->sessionId;
    }

    protected function genSessionConfig()
    {
        return [
            'expire' => 3600,
            'path' => DB::getTablePrefix() . 'sessions',
            // 'path' => storage_path() . DIRECTORY_SEPARATOR . 'orbit-session',
            'strict' => FALSE,
            'connection' => DB::connection()->getPdo(),
            'driver' => 'database',
            'availability' => array(
                'header'        => TRUE,
                // UrlChecker turn the query string manually, so we can not rely on this anymore
                'query_string'  => FALSE,
                // We don't need cookie on test because we are in console
                'cookie'        => FALSE,
            ),
            'session_origin' => array(
                'header'  => array(
                    'name'      => 'X-Orbit-Session'
                ),
                'query_string' => array(
                    'name'      => 'orbit_session'
                ),
                'cookie'    => array(
                    'name'      => 'orbit_sessionx',
                    'expire' => 62208000,
                    'path'      => '/',
                    'domain'    => NULL,
                    'secure'    => FALSE,
                    'httponly'  => FALSE
                )
            )
        ];
    }
}
