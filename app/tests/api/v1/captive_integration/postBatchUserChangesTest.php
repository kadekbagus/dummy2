<?php
use Carbon\Carbon;
use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * @property MacAddress[] macAddresses
 * @property Apikey authData
 * @property User[] users
 */
class postBatchUserChangesTest extends TestCase
{
    public function setUp() {
        parent::setUp();
        // this needs MAC addresses with an associated email
        $this->macAddresses = Factory::times(5)->create('MacAddress', ['status' => 'active']);
        // and the email should be associated with a user with consumer role
        $role = Factory::create('Role', [
            'role_name' => 'Consumer',
            'role_order' => 1
        ]);
        $this->users = [];
        foreach ($this->macAddresses as $address) {
            $this->users[] = Factory::create('User', [
                'user_email' => $address->user_email,
                'user_role_id' => $role->role_id
            ]);
        }

        $this->authData = Factory::create('apikey_super_admin');
    }

    private function makeRequest($in = null, $out = null)
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];
        $_POST = [];
        if ($in !== null) {
            $_POST['in_macs'] = json_encode($in);
        }
        if ($out !== null) {
            $_POST['out_macs'] = json_encode($out);
        }
        $url = '/api/v1/captive-portal/network/batch-enter-leave?' . http_build_query($_GET);
        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    private function assertSingleResponseOk($response)
    {
        $this->assertSame(200, $response[0]);
        $json = json_decode($response[1]);
        $this->assertStringEndsWith('OK', $json->message);
        $this->assertSame('success', $json->status);
    }

    private function assertSingleResponseFail($response, $code, $expected_message_regexp)
    {
        $this->assertSame($code, $response[0]);
        $json = json_decode($response[1]);
        $this->assertSame('error', $json->status);
        $this->assertRegExp($expected_message_regexp, $json->message);
    }

    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    private function assertJsonResponseMatchesRegExp(
        $expected_code,
        $expected_status,
        $expected_message_regexp,
        $response
    ) {
        $this->assertRegExp($expected_message_regexp, $response->message);
        $this->assertSame($expected_status, $response->status);
        $this->assertSame($expected_code, (int)$response->code);
    }

    public function testBatchSendSingleString()
    {
        $this->withActivitiesSaved(function() {
            $checkin_count_before = $this->countNetCheckInActivitiesForUser($this->users[0]->user_id);
            $checkout_count_before = $this->countNetCheckOutActivitiesForUser($this->users[1]->user_id);
            $response = $this->makeRequest($this->macAddresses[0]->mac_address, $this->macAddresses[1]->mac_address);
            $this->assertJsonResponseOk($response);
            $count = 0;
            foreach ($response->data->in as $mac => $in_response) {
                $this->assertSingleResponseOk($in_response);
                $count++;
            }
            $this->assertSame(1, $count);
            $count = 0;
            foreach ($response->data->out as $mac => $out_response) {
                $this->assertSingleResponseOk($out_response);
                $count++;
            }
            $this->assertSame(1, $count);
            $checkin_count_after = $this->countNetCheckInActivitiesForUser($this->users[0]->user_id);
            $checkout_count_after = $this->countNetCheckOutActivitiesForUser($this->users[1]->user_id);
            $this->assertSame($checkin_count_before + 1, $checkin_count_after);
            $this->assertSame($checkout_count_before + 1, $checkout_count_after);
        });
    }

    public function testBatchSendMultipleString()
    {
        $response = $this->makeRequest(
            [$this->macAddresses[0]->mac_address, $this->macAddresses[1]->mac_address],
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address]
            );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(2, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testMacNotFoundIn()
    {
        $deleted_mac = Factory::create('MacAddress', ['status' => 'deleted']);
        $response = $this->makeRequest(
            [$deleted_mac->mac_address],
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address]
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseFail($in_response, 200, '/not found/i');
            $count++;
        }
        $this->assertSame(1, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testMacNotFoundOut()
    {
        $deleted_mac = Factory::create('MacAddress', ['status' => 'deleted']);
        $response = $this->makeRequest(
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address],
            [$deleted_mac->mac_address]
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $deleted_mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(2, $count);
        $count = 0;
        foreach ($response->data->out as $deleted_mac => $out_response) {
            $this->assertSingleResponseFail($out_response, 200, '/not found/i');
            $count++;
        }
        $this->assertSame(1, $count);
    }

    public function testInvalidMacIn()
    {
        $response = $this->makeRequest(
            ['xx:yy:zz:ww:aa:bb'],
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address]
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $mac => $in_response) {
            $this->assertSingleResponseFail($in_response, 200, '/not valid/i');
            $count++;
        }
        $this->assertSame(1, $count);
        $count = 0;
        foreach ($response->data->out as $mac => $out_response) {
            $this->assertSingleResponseOk($out_response);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testInvalidMacOut()
    {
        $response = $this->makeRequest(
            [$this->macAddresses[2]->mac_address, $this->macAddresses[3]->mac_address],
            ['xx:yy:zz:ww:aa:bb']
        );
        $this->assertJsonResponseOk($response);
        $count = 0;
        foreach ($response->data->in as $deleted_mac => $in_response) {
            $this->assertSingleResponseOk($in_response);
            $count++;
        }
        $this->assertSame(2, $count);
        $count = 0;
        foreach ($response->data->out as $deleted_mac => $out_response) {
            $this->assertSingleResponseFail($out_response, 200, '/not valid/i');
            $count++;
        }
        $this->assertSame(1, $count);
    }

    public function testLogsUsersOutIfNotLoggedOutYet()
    {
        $this->withActivitiesSaved(function() {
            $event_fired = false;
            $event_activity = null;
            Event::listen('orbit.network.checkout.force_mobileci_checkout', function($controller, $activity) use (&$event_fired, &$event_activity) {
                $event_activity = $activity;
                $event_fired = true;
            });

            // log user in first
            $activity = Activity::mobileci()
                ->setActivityType('login')
                ->setUser($this->users[0])
                ->setActivityName('login_ok')
                ->setActivityNameLong('Sign In')
                ->responseOK();
            $activity->save();

            $response = $this->makeRequest(
                [],
                [$this->macAddresses[0]->mac_address]
            );
            $this->assertJsonResponseOk($response);
            $count = 0;
            foreach ($response->data->out as $mac => $out_response) {
                $this->assertSingleResponseOk($out_response);
                $count++;
            }

            $this->assertTrue($event_fired);
            $this->assertNotNull($event_activity);
            $this->assertSame('logout', $event_activity->activity_type);
            $this->assertSame('logout_ok', $event_activity->activity_name);
            $this->assertSame('mobile-ci', $event_activity->group);
            $this->assertSame((string)$this->users[0]->user_id, (string)$event_activity->user_id);
        });
    }

    public function testDoesNotLogUserOutIfAlreadyLoggedOut()
    {
        $this->withActivitiesSaved(function() {
            $event_fired = false;
            $event_activity = null;
            Event::listen('orbit.network.checkout.force_mobileci_checkout', function($controller, $activity) use (&$event_fired, &$event_activity) {
                $event_activity = $activity;
                $event_fired = true;
            });

            // log user in first
            $activity = Activity::mobileci()
                ->setActivityType('login')
                ->setUser($this->users[0])
                ->setActivityName('login_ok')
                ->setActivityNameLong('Sign In')
                ->responseOK();
            $activity->save();
            sleep(1);

            // then log out
            $activity = Activity::mobileci()
                ->setActivityType('logout')
                ->setUser($this->users[0])
                ->setActivityName('logout_ok')
                ->setActivityNameLong('Sign Out')
                ->responseOK();
            $activity->save();

            $response = $this->makeRequest(
                [],
                [$this->macAddresses[0]->mac_address]
            );
            $this->assertJsonResponseOk($response);
            $count = 0;
            foreach ($response->data->out as $mac => $out_response) {
                $this->assertSingleResponseOk($out_response);
                $count++;
            }

            $this->assertFalse($event_fired);
            $this->assertNull($event_activity);
        });
    }

    public function testLogsUsersOutIfLogoutBeforeLogin()
    {
        $this->withActivitiesSaved(function() {
            $event_fired = false;
            $event_activity = null;
            Event::listen('orbit.network.checkout.force_mobileci_checkout', function($controller, $activity) use (&$event_fired, &$event_activity) {
                $event_activity = $activity;
                $event_fired = true;
            });

            // log user in first
            $activity = Activity::mobileci()
                ->setActivityType('login')
                ->setUser($this->users[0])
                ->setActivityName('login_ok')
                ->setActivityNameLong('Sign In')
                ->responseOK();
            $activity->save();

            // log out is in the past
            $activity = Activity::mobileci()
                ->setActivityType('logout')
                ->setUser($this->users[0])
                ->setActivityName('logout_ok')
                ->setActivityNameLong('Sign Out')
                ->responseOK();
            $activity->created_at = $activity->updated_at = Carbon::yesterday();
            $activity->save();

            $response = $this->makeRequest(
                [],
                [$this->macAddresses[0]->mac_address]
            );
            $this->assertJsonResponseOk($response);
            $count = 0;
            foreach ($response->data->out as $mac => $out_response) {
                $this->assertSingleResponseOk($out_response);
                $count++;
            }

            $this->assertTrue($event_fired);
            $this->assertNotNull($event_activity);
            $this->assertSame('logout', $event_activity->activity_type);
            $this->assertSame('logout_ok', $event_activity->activity_name);
            $this->assertSame('mobile-ci', $event_activity->group);
            $this->assertSame((string)$this->users[0]->user_id, (string)$event_activity->user_id);
        });
    }

    private function withActivitiesSaved(Closure $c)
    {
        $key = 'orbit.activity.force.save';
        $old_force = Config::get($key, FALSE);
        try {
            Config::set($key, TRUE);
            $c();
            // assume "finally" not supported (PHP 5.5).
            Config::set($key, $old_force);
        } catch (Exception $e)
        {
            Config::set($key, $old_force);
            throw $e;
        }
    }

    private function countNetCheckInActivitiesForUser($user_id)
    {
        return Activity::active()
            ->where('activity_type', '=', 'network')
            ->where('activity_name', '=', 'network_checkin_ok')
            ->where('user_id', '=', $user_id)
            ->orderBy('created_at', 'desc')
            ->count();
    }

    private function countNetCheckOutActivitiesForUser($user_id)
    {
        return Activity::active()
            ->where('activity_type', '=', 'network')
            ->where('activity_name', '=', 'network_checkout_ok')
            ->where('user_id', '=', $user_id)
            ->orderBy('created_at', 'desc')
            ->count();
    }
}
