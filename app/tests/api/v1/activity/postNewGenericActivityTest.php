<?php
/**
 * Unit test for API /app/v1/pub/generic-activity/new
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use \Activity;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewGenericActivityTest extends TestCase
{
    protected $sessionId = 1;
    protected $sessData = NULL;
    protected $apiUrl = '/app/v1/pub/generic-activity/new';

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

        $this->user_profiling = Factory::create('user_consumer');

        $this->mall_1 = Factory::create('Mall');

        $_GET = [];
        $_POST = [];
    }

    public function testOKPostNewGenericActivityFromLandingPage()
    {
        $this->sessionId = time();
        $this->createSessionForUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.generic_activity', $this->genGenericActivityList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = $this->sessionId;

        $_POST[Config::get('orbit.generic_activity.parameter_name')] = 1;

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);

        $activity = Activity::where('activity_id', $response->data)->first();

        $this->assertSame(TRUE, is_object($activity));
        $this->assertSame(Config::get('orbit.generic_activity.activity_list.1.name'), $activity->activity_name);
    }

    public function testFAILPostNewGenericActivityFromLandingPageNoUser()
    {
        $this->sessionId = time();
        $this->createSessionForUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.generic_activity', $this->genGenericActivityList());
        Config::set('orbit.activity.force.save', TRUE);

        $_POST['X-Orbit-App-Origin'] = 'landing_page';
        $_POST[Config::get('orbit.generic_activity.parameter_name')] = 1;

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(1, (int) $response->code);
        $this->assertSame('No session found.', $response->message);
    }

    public function testFAILPostNewGenericActivityFromLandingPageConfigNotSet()
    {
        $this->sessionId = time();
        $this->createSessionForUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.generic_activity', '');
        Config::set('orbit.activity.force.save', TRUE);

        $_POST['X-Orbit-App-Origin'] = 'landing_page';
        $_POST['X-OMS-Mobile'] = $this->sessionId;
        $_POST[Config::get('orbit.generic_activity.parameter_name')] = 1;

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(1, (int) $response->code);
        $this->assertSame('Activity config is not configured correctly.', $response->message);
    }

    public function testFAILPostNewGenericActivityFromLandingPageINameNotSet()
    {
        $this->sessionId = time();
        $this->createSessionForUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.generic_activity', $this->genGenericActivityList());
        Config::set('orbit.generic_activity.activity_list.1.name', '');
        Config::set('orbit.activity.force.save', TRUE);

        $_POST['X-Orbit-App-Origin'] = 'landing_page';
        $_POST['X-OMS-Mobile'] = $this->sessionId;
        $_POST[Config::get('orbit.generic_activity.parameter_name')] = 1;

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(1, (int) $response->code);
        $this->assertSame('Activity config is not configured correctly.', $response->message);
    }

    public function testFAILPostNewGenericActivityFromLandingPageNoActivityIdentifier()
    {
        $this->sessionId = time();
        $this->createSessionForUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.generic_activity', $this->genGenericActivityList());
        Config::set('orbit.activity.force.save', TRUE);

        $_POST['X-Orbit-App-Origin'] = 'landing_page';
        $_POST['X-OMS-Mobile'] = $this->sessionId;
        $_POST[Config::get('orbit.generic_activity.parameter_name')] = '';

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('POST', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $this->assertSame(1, (int) $response->code);
        $this->assertSame('Activity identifier is required.', $response->message);
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
        $session = DB::table('sessions')->insert($sessionData);
        // Set the orbit_session on the query string
        $_SERVER['X-OMS-Mobile'] = $this->sessionId;
        $_SERVER['HTTP_USER_AGENT'] = 'BrowserSangar';
    }

    protected function genGenericActivityList()
    {
        $config = array(
            'parameter_name' => 'act',
            'activity_list' => array(
                // landing page activity list
                // - Successfully landed in Gotomalls
                '1' => array(
                    'name' => 'view_landing_page',
                    'name_long' => 'View Landing Page',
                    'module_name' => 'Application',
                    'type' => 'view',
                ),
                // - Clicking on a mall in result list
                '2' => array(
                    'name' => 'click_mall_list',
                    'name_long' => 'Click Mall List',
                    'module_name' => 'Application',
                    'type' => 'click',
                ),
                // - Clicking on a mall pin on the map
                '3' => array(
                    'name' => 'click_mall_pin',
                    'name_long' => 'Click Mall Pin',
                    'module_name' => 'Application',
                    'type' => 'click',
                ),
                // - Visiting a mall
                '4' => array(
                    'name' => 'view_mall',
                    'name_long' => 'View Mall',
                    'module_name' => 'Application',
                    'type' => 'view',
                ),
                // - Viewing mall info
                '5' => array(
                    'name' => 'view_mall_info',
                    'name_long' => 'View Mall Info',
                    'module_name' => 'Application',
                    'type' => 'view',
                ),
                // - Switching user
                '6' => array(
                    'name' => 'click_not_me',
                    'name_long' => 'Switch User',
                    'module_name' => 'Application',
                    'type' => 'click',
                ),
            ),
        );

        return $config;
    }

    protected function genSessionConfig()
    {
        // Return mall_portal, cs_portal, pmp_portal etc
        $config = array(
            /**
             * How long session will expire in seconds
             */
            'expire' => 3600,

            /**
             * Application list mapping for session
             */
            'app_list' => [
                'mobile_ci' => 'X-OMS-Mobile',
                'desktop_ci' => 'X-OMS-Mobile',
                'landing_page' => 'X-OMS-Mobile',

                // Non Customer
                'mall_portal' => 'X-OMS-Mall',
                'cs_portal' => 'X-OMS-CS',
                'pmp_portal' => 'X-OMS-PMP',
                'admin_portal' => 'X-OMS-Admin'
            ],

            /**
             * Application ID mapping for session
             */
            'app_id' => [
                'mobile_ci' => '1',
                'desktop_ci' => '2',
                'landing_page' => '3',

                // Non Customer
                'mall_portal' => '4',
                'cs_portal' => '5',
                'pmp_portal' => '6',
                'admin_portal' => '7'
            ],

            /**
             * Path to write the session data
             */
            //'path' => storage_path() . DIRECTORY_SEPARATOR . 'orbit-session',
            'path' => DB::getTablePrefix() . 'sessions',

            /**
             * Strict mode, will check the user agent and ip address
             */
            'strict' => FALSE,

            /**
             * Session Driver
             */
            'driver' => 'database',

            // 'connection' => array(),
            'connection' => DB::connection()->getPdo(),

            /**
             * Session data available
             */
            'availability' => array(
                'header'        => TRUE,
                'query_string'  => TRUE,
                'cookie'        => FALSE,
            ),

            /**
             * Where is session data should be coming from
             */
            'session_origin' => array(
                'expire' => 62208000,

                // From HTTP Headers
                'header'  => array(
                    'name'      => 'X-Orbit-Sessionx'
                ),

                // From Query String
                'query_string' => array(
                    'name'      => 'orbit_session'
                ),

                // From Cookie
                'cookie'    => array(
                    'name'      => 'orbit_sessionx',

                    // Expire time, should be set equals or higher than
                    // SessionConifg.expire
                    'expire' => 62208000,   // two years

                    // Path of the cookie
                    'path'      => '/',

                    // domain
                    'domain'    => NULL,

                    // secure transfer via HTTPS only
                    'secure'    => FALSE,

                    // Deny access from client side script
                    'httponly'  => FALSE
                ),
            ),

            /**
             * Where is session data should be coming from
             * @Todo: use this config instead
             */
            'origin' => array(
                'mobile_ci' => array(
                    'expire' => 62208000,

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-Mobile'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-Mobile'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-Mobile',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 62208000,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => 'gotomalls.cool',

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => FALSE
                    ),
                ),

                'desktop_ci' => array(
                    'expire' => 62208000,   // two years

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-Mobile'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-Mobile'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-Mobile',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 62208000,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => 'gotomalls.cool',

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => TRUE
                    ),
                ),

                'landing_page' => array(
                    'expire' => 62208000,   // two years

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-Mobile'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-Mobile'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-Mobile',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 62208000,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => 'gotomalls.cool',

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => TRUE
                    ),
                ),

                'admin_portal' => array(
                    'expire' => 3600,

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-Admin'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-Admin'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-Admin',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 3600,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => NULL,

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => TRUE
                    ),
                ),

                'mall_portal' => array(
                    'expire' => 3600,

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-Mall'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-Mall'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-Mall',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 3600,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => NULL,

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => TRUE
                    ),
                ),

                'cs_portal' => array(
                    'expire' => 3600,

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-CS'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-CS'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-CS',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 3600,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => NULL,

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => TRUE
                    ),
                ),

                'pmp_portal' => array(
                    'expire' => 3600,   // 1 hour

                    // From HTTP Headers
                    'header'  => array(
                        'name'      => 'X-OMS-PMP'
                    ),

                    // From Query String
                    'query_string' => array(
                        'name'      => 'X-OMS-PMP'
                    ),

                    // From Cookie
                    'cookie'    => array(
                        'name'      => 'X-OMS-PMP',

                        // Expire time, should be set equals or higher than
                        // SessionConifg.expire
                        'expire' => 3600,   // two years

                        // Path of the cookie
                        'path'      => '/',

                        // domain
                        'domain'    => NULL,

                        // secure transfer via HTTPS only
                        'secure'    => FALSE,

                        // Deny access from client side script
                        'httponly'  => TRUE
                    ),
                ),
            ),
        );

        return $config;
    }
}
