<?php
/**
 * Unit test for API /app/v1/pub/url-redirector
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use \Activity;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getRedirectUrlTest extends TestCase
{
    protected $sessionId = 1;
    protected $sessData = NULL;
    protected $apiUrl = '/app/v1/pub/url-redirector';

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

    public function testOKPostNewRedirectFromLandingPageInstagram()
    {
        $this->sessionId = time();
        $this->createSessionForGuestUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.redirect_url_list', $this->genRedirectUrlList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = $this->sessionId;
        $_GET['type'] = 'instagram';

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('GET', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $activity = Activity::where('activity_name_long', Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name_long'))->first();

        $this->assertSame(TRUE, is_object($activity));
        $this->assertSame(Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name'), $activity->activity_name);
        $this->assertSame('Redirected to: ' . Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.url'), $activity->notes);
        $this->assertSame((string)$this->sessionId, (string)$activity->session_id);
    }

    public function testOKPostNewRedirectFromLandingPageFacebook()
    {
        $this->sessionId = time();
        $this->createSessionForGuestUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.redirect_url_list', $this->genRedirectUrlList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = $this->sessionId;
        $_GET['type'] = 'facebook';

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('GET', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $activity = Activity::where('activity_name_long', Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name_long'))->first();

        $this->assertSame(TRUE, is_object($activity));
        $this->assertSame(Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name'), $activity->activity_name);
        $this->assertSame('Redirected to: ' . Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.url'), $activity->notes);
        $this->assertSame((string)$this->sessionId, (string)$activity->session_id);
    }

    public function testFAILPostNewRedirectFromLandingPageNoUser()
    {
        $this->sessionId = time();
        $this->createSessionForGuestUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.redirect_url_list', $this->genRedirectUrlList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = '';
        $_GET['type'] = 'facebook';

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('GET', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $activity = Activity::where('activity_name_long', Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name_long'))->first();

        $this->assertSame(FALSE, is_object($activity));

        $this->assertSame(1, (int) $response->code);
        $this->assertSame('No session found.', $response->message);
    }

    public function testFAILPostNewRedirectFromLandingPageTypeNotFound()
    {
        $this->sessionId = time();
        $this->createSessionForGuestUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.redirect_url_list', $this->genRedirectUrlList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = $this->sessionId;
        $_GET['type'] = 'facebroot'; // non-existent type

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('GET', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $activity = Activity::where('activity_name_long', Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name_long'))->first();

        $this->assertSame(FALSE, is_object($activity));

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('Url type is not supported.', $response->message);
    }

    public function testFAILPostNewRedirectFromLandingPageUrlNotFound()
    {
        $this->sessionId = time();
        $this->createSessionForGuestUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.redirect_url_list', $this->genRedirectUrlList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = $this->sessionId;
        $_GET['type'] = 'facebook';

        // set empty string to url
        Config::set('orbit.redirect_url_list.' . $_GET['type'] . '.url', '');

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('GET', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $activity = Activity::where('activity_name_long', Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name_long'))->first();

        $this->assertSame(FALSE, is_object($activity));

        $this->assertSame(14, (int) $response->code);
        $this->assertSame('No url found for requested type.', $response->message);
    }

    public function testFAILPostNewRedirectFromLandingPageActivityNameNotFound()
    {
        $this->sessionId = time();
        $this->createSessionForGuestUser($this->guest_user);

        Config::set('orbit.session', $this->genSessionConfig());
        Config::set('orbit.redirect_url_list', $this->genRedirectUrlList());
        Config::set('orbit.activity.force.save', TRUE);

        $_GET['X-Orbit-App-Origin'] = 'landing_page';
        $_GET['X-OMS-Mobile'] = $this->sessionId;
        $_GET['type'] = 'facebook';

        // set empty string to activity_name
        Config::set('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name', '');

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $this->apiUrl;

        $json = $this->call('GET', $this->apiUrl)->getContent();
        $response = json_decode($json);

        $activity = Activity::where('activity_name_long', Config::get('orbit.redirect_url_list.' . $_GET['type'] . '.activity_name_long'))->first();

        $this->assertSame(FALSE, is_object($activity));
    }

    protected function createSessionForGuestUser($user)
    {
        $this->sessData->value = [
            'logged_in' => TRUE,
            'guest_user_id' => $user->user_id,
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

    protected function genRedirectUrlList()
    {
        $config = array(
            'instagram' => array(
                'url' => 'https://www.instagram.com/gotomalls',
                'activity_name' => 'click_social_media',
                'activity_name_long' => 'Click Instagram',
                'activity_module_name' => 'Application',
                'activity_type' => 'click',
            ),
            'facebook' => array(
                'url' => 'https://www.facebook.com/Gotomalls.Indo',
                'activity_name' => 'click_social_media',
                'activity_name_long' => 'Click Facebook',
                'activity_module_name' => 'Application',
                'activity_type' => 'click',
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
