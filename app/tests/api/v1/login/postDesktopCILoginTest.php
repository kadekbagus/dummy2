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
        $this->guest_user = Factory::create('user_guest');

        $this->password = 'PasswordOkePakDhe';
        $this->user_profiling = Factory::create('user_consumer', [
            'user_password' => \Hash::make($this->password),
        ]);
        $this->userdetail_profiling = Factory::create('UserDetail', [
            'user_id' => $this->user_profiling->user_id,
            'birthdate' => date('Y-m-d', strtotime('-36 year')),
            'gender' => 'm'
        ]);
        $this->user_profiling_apikey = Factory::create('apikey_super_admin', [
            'user_id' => $this->user_profiling->user_id
        ]);

        $this->mall_1 = Factory::create('Mall');

        $_GET = [];
        $_POST = [];
    }

    public function testOKpostDesktopCILogin()
    {
        $this->sessionId = '1';
        $this->createSessionForUser($this->guest_user);
        $_POST['email'] = $this->user_profiling->user_email;
        $_POST['password'] = $this->password;
        $_POST['mall_id'] = $this->mall_1->merchant_id;

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);

        // check all returned records
        $this->assertSame($this->user_profiling->user_id, $response->data->user_id);
    }

    public function testFailpostDesktopCILoginMallIDNotExist()
    {
        $this->sessionId = '2';
        $this->createSessionForUser($this->guest_user);
        $_POST['email'] = $this->user_profiling->user_email;
        $_POST['password'] = $this->password;
        $_POST['mall_id'] = '';

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('The mall id field is required', $response->message);
    }

    public function testFailpostDesktopCILoginEmailNotExist()
    {
        $this->sessionId = '3';
        $this->createSessionForUser($this->guest_user);
        $_POST['email'] = 'a' . $this->user_profiling->user_email;
        $_POST['password'] = $this->password;
        $_POST['mall_id'] = $this->mall_1->merchant_id;

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('User with the specified email is not found', $response->message);
    }

    public function testFailpostDesktopCILoginWrongPassword()
    {
        $this->sessionId = '4';
        $this->createSessionForUser($this->guest_user);
        $_POST['email'] = $this->user_profiling->user_email;
        $_POST['password'] = 'PasswordSalah';
        $_POST['mall_id'] = $this->mall_1->merchant_id;

        Config::set('orbit.session', $this->genSessionConfig());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('Your email or password is incorrect', $response->message);
    }

    protected function createSessionForUser($user)
    {
        $this->sessData->value = [
            'logged_in' => TRUE,
            'user_id'   => $user->user_id,
            'email'     => $user->user_email,
            'role'      => $user->role->role_name,
            'fullname'  => $user->getFullName()
        ];
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
