<?php
/**
 * Handle integration with Orbit Captive Portal to provide useful metrics
 * and seamless integration.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Http\Response;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Net\MacAddr;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\ResponseProvider;

class CaptiveIntegrationAPIController extends ControllerAPI
{
    private $inInternalRequest = false;

    public function postBatchUserEnterLeave()
    {
        try {
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            // todo encrypt?
            $in = OrbitInput::post('in_macs', '[]');
            $in = @json_decode($in);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('Invalid in_macs: not JSON');
            }
            if (!is_array($in)) {
                $in = [$in];
            }

            $out = OrbitInput::post('out_macs', '[]');
            $out = @json_decode($out);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('Invalid out_macs: not JSON');
            }
            if (!is_array($out)) {
                $out = [$out];
            }

            $results = [
                'in' => [],
                'out' => [],
            ];
            foreach ($in as $newly_seen_mac) {
                /** @var Response $response */
                $response = $this->doLogIn($newly_seen_mac);
                $results['in'][$newly_seen_mac] = [$response->getStatusCode(), $response->getContent()];
            }

            foreach ($out as $leaving_mac) {
                /** @var Response $response */
                $response = $this->doLogOut($leaving_mac);
                $results['out'][$leaving_mac] = [$response->getStatusCode(), $response->getContent()];
            }

            // the in/out result keyed with a string results in JSON object, but if
            // there are no elements it results in JSON array, so we "fix" that.
            if (empty($results['in'])) {
                $results['in'] = new stdClass();
            }
            if (empty($results['out'])) {
                $results['out'] = new stdClass();
            }


            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';
            $this->response->data = $results;
            return $this->render(200);
        }
        catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            return $this->render(403); // todo code?
        }
    }

    private function doLogIn($mac_address)
    {
        // clean the response between internal requests
        $this->response = new ResponseProvider();
        $_GET['payload'] = base64_encode(http_build_query(['mac' => $mac_address]));
        $old_internal_request = $this->inInternalRequest;
        $this->inInternalRequest = true;
        $response = $this->getUserSignInNetwork(); // XXX
        $this->inInternalRequest = $old_internal_request;
        unset($_GET['payload']);
        return $response;
    }

    private function doLogOut($mac_address)
    {
        // clean the response between internal requests
        $this->response = new ResponseProvider();
        $_GET['payload'] = base64_encode(http_build_query(['mac' => $mac_address]));
        $old_internal_request = $this->inInternalRequest;
        $this->inInternalRequest = true;
        $response = $this->getUserOutOfNetwork(); // XXX
        $this->inInternalRequest = $old_internal_request;
        unset($_GET['payload']);
        return $response;
    }


    public function getUserOutOfNetwork()
    {
        $activity = Activity::mobileCI()
                            ->setActivityType('network');

        // Get captive Server IP address
        $captiveIP = $_SERVER['REMOTE_ADDR'];
        $payload = trim(OrbitInput::get('payload', NULL));

        $captiveLogFile = storage_path() . '/logs/captive-call.log';

        $now = date('Y-m-d H:i:s');
        $format = "[%s] %s; checkout; Email %s do network checkout; %s";
        $httpCode = 200;
        $message = '';
        $customer = 'guest';
        $email = 'unknown';

        try {
            Event::fire('orbit.network.checkout.before.auth', array($this));

            // Require authentication
            if (!$this->inInternalRequest) {
                $this->checkAuth();
            }

            Event::fire('orbit.network.checkout.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.network.checkout.before.authz', array($this, $user));

            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.network.checkout.after.authz', array($this, $user));

            if (empty($payload)) {
                $message = 'Captive payload is empty.';
                throw new Exception ($message, 1);
            }

            // Decrypt and parse the payload
            $payload = base64_decode($payload);

            parse_str($payload, $output);

            if (! isset($output['mac'])) {
                $message = 'Mac address argument on payload is empty.';
                throw new Exception ($message, 1);
            }
            $mac = $output['mac'];

            $macAddr = MacAddr::create($mac);
            if (! $macAddr->isValid()) {
                $message = 'Mac address format is not valid.';
                throw new Exception ($message, 1);
            }
            $macAddr->reformat(':');

            // Get the most recent user using this mac address
            $macModel = MacAddress::excludeDeleted()
                                  ->where('mac_address', $macAddr->getMac())
                                  ->orderBy('created_at', 'desc')
                                  ->first();

            if ($macModel !== null) {
                // find consumer with MAC

                $_customer = User::Consumers()
                    ->excludeDeleted()
                    ->where('user_email', $macModel->user_email)
                    ->first();

                if (! empty($_customer)) {
                    // user is a consumer user. log out if still logged in.
                    $customer = $_customer;
                    $email = $customer->user_email;

                    $this->logCustomerOutIfStillLoggedIn($_customer);
                }

            }

            // if User not recognized ($_customer null) log it as 'guest'
            $message = sprintf($format, $now, $captiveIP, $email, 'OK');
            $this->response->message = $message;
            $activity->setUser($customer)
                     ->setActivityName('network_checkout_ok')
                     ->setActivityNameLong('Network Check Out')
                     ->setModuleName('Network')
                     ->setNotes($message)
                     ->responseOK();

            Event::fire('orbit.network.checkout.done', array($this, ['']));
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            Event::fire('orbit.network.checkout.error', array($this, ['']));

            $activity->setUser('guest')
                     ->setActivityName('network_checkout_failed')
                     ->setActivityNameLong('Network Checkout Failed')
                     ->setNotes($e->getMessage())
                     ->setModuleName('Network')
                     ->responseFailed();

        }

        $activity->user_email = $email;
        $activity->save();
        file_put_contents($captiveLogFile, $message . "\n", FILE_APPEND);

        return $this->render($httpCode);
    }

    /**
     * Lippo mall captive portal integration with Orbit. The Lippo Mall
     * captive portal will send email address and mac address from query string.
     *
     * i.e:
     * http://orbit.box/?email=foo@bar.com&mac=AA:BB:CC:DD:EE
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function getUserSignInNetwork()
    {
        $activity = Activity::mobileCI()
                            ->setActivityType('network');

        // Get Captive Server IP address
        $captiveIP = $_SERVER['REMOTE_ADDR'];
        $payload = trim(OrbitInput::get('payload', NULL));

        $captiveLogFile = storage_path() . '/logs/captive-call.log';

        $now = date('Y-m-d H:i:s');
        $format = "[%s] %s; checkin; Email %s do network checkin; %s";
        $httpCode = 200;
        $message = '';
        $email = 'unknown';
        $user = 'guest';
        $customer = 'guest';

        try {
            $this->beginTransaction();

            Event::fire('orbit.network.checkin.before.auth', array($this));

            // Require authentication
            if (!$this->inInternalRequest) {
                $this->checkAuth();
            }

            Event::fire('orbit.network.checkin.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.network.checkin.before.authz', array($this, $user));

            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.network.checkin.after.authz', array($this, $user));

            if (empty($payload)) {
                $message = 'Captive payload is empty.';
                throw new Exception ($message, 1);
            }

            // Decrypt and parse the payload
            $payload = base64_decode($payload);

            parse_str($payload, $output);
            if (! isset($output['mac'])) {
                $message = 'Mac address argument on payload is empty.';
                throw new Exception ($message, 1);
            }
            $mac = $output['mac'];

            $macAddr = MacAddr::create($mac);
            if (! $macAddr->isValid()) {
                $message = 'Mac address format is not valid.';
                throw new Exception ($message, 1);
            }
            $macAddr->reformat(':');

            // Get the most recent user using this mac address
            $macModel = MacAddress::excludeDeleted()
                                  ->where('mac_address', $macAddr->getMac())
                                  ->orderBy('created_at', 'desc')
                                  ->first();

            if ($macModel !== null) {
                // find customer using this MAC

                $_customer = User::Consumers()
                    ->excludeDeleted()
                    ->where('user_email', $macModel->user_email)
                    ->first();

                if (! empty($_customer)) {
                    // User is consumer, use found email & user object
                    $customer = $_customer;
                    $email = $_customer->user_email;
                }
                // else use guest as default user name
            }


            $this->commit();

            // Successfull
            $message = sprintf($format, $now, $captiveIP, $email, 'OK');
            $this->response->message = $message;

            $activity->setUser($customer)
                     ->setActivityName('network_checkin_ok')
                     ->setActivityNameLong('Network Check In')
                     ->setNotes($message)
                     ->setModuleName('Network')
                     ->responseOK();

            Event::fire('orbit.network.checkin.done', array($this, $macModel, $payload));
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile() . ':' . $e->getLine();

            $this->rollback();

            $activity->setUser($customer)
                     ->setActivityName('network_checkin_failed')
                     ->setActivityNameLong('Network Check In Failed')
                     ->setNotes($e->getMessage())
                     ->setModuleName('Network')
                     ->responseFailed();

            Event::fire('orbit.network.checkin.done', array($this, $payload));
        }

        $activity->user_email = $email;
        $activity->save();
        file_put_contents($captiveLogFile, $message . "\n", FILE_APPEND);

        return $this->render($httpCode);
    }

    /**
     * Lippo mall captive portal integration with Orbit. The Lippo Mall
     * captive portal will send email address and mac address from query string.
     *
     * i.e:
     * http://orbit.box/?email=foo@bar.com&mac=AA:BB:CC:DD:EE
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function getMacAddress()
    {
        $httpCode = 200;

        try {
            // $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            // $user = $this->api->user;

            // $role = $user->role;
            $role = new stdClass(); $role->role_name = 'super admin';
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this page.';
                ACL::throwAccessForbidden($message);
            }

            $mac = OrbitInput::get('mac');

            $macs = MacAddress::with('user')->where('mac_address', $mac);
            $_macs = clone $macs;

            $totalMacs = RecordCounter::create($_macs)->count();

            // Get the take args
            $take = 5;
            $maxRecord = 50;

            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $macs->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $macs) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $macs->skip($skip);

            // Default sort by
            $sortBy = 'mac_addresses.updated_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'created_at'              => 'mac_addresses.created_at',
                    'updated_at'              => 'mac_addresses.updated_at',
                    'mac'                     => 'mac_addresses.mac_address',
                    'email'                   => 'mac_addresses.email'
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });
            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $macs->orderBy($sortBy, $sortMode);

            $listOfMacs = $macs->get();

            $data = new stdclass();
            $data->total_records = $totalMacs;
            $data->returned_records = count($listOfMacs);
            $data->records = $listOfMacs;

            if ($totalMacs === 0) {
                $data->records = null;
                $this->response->message = 'No mac address found.';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }

            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $httpCode = 500;
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->getFile() . ':' . $e->getLine();
            } else {
                $this->response->data = NULL;
            }
        }

        return $this->render($httpCode);
    }

    /**
     * @param User $customer
     */
    private function logCustomerOutIfStillLoggedIn($customer)
    {
        // get most recent mobileCI logout of customer
        $most_recent_logout = Activity::active()
            ->where('group', '=', 'mobile-ci')
            ->where('activity_type', '=', 'logout')
            ->where('activity_name', '=', 'logout_ok')
            ->where('user_id', '=', $customer->user_id)
            ->orderBy('created_at', 'desc')
            ->first();
        // get most recent mobileCI login of customer
        $most_recent_login = Activity::active()
            ->where('group', '=', 'mobile-ci')
            ->where('activity_type', '=', 'login')
            ->where('activity_name', '=', 'login_ok')
            ->where('user_id', '=', $customer->user_id)
            ->orderBy('created_at', 'desc')
            ->first();
        // if no logout or login later than logout insert logout record
        if ($most_recent_login === null) {
            // ???
            return;
        }

        // compare using carbon gt() method
        if ($most_recent_logout === null || $most_recent_login->created_at->gt($most_recent_logout->created_at)) {
            $logout_activity = Activity::mobileci()
                ->setActivityType('logout')
                ->setUser($customer)
                ->setActivityName('logout_ok')
                ->setActivityNameLong('Sign Out')
                ->setModuleName('Application')
                ->responseOK();
            // copy the user-agent across to help with analysis, but not the IP address (no reason to do that for now)
            $logout_activity->user_agent = $most_recent_login->user_agent;
            $logout_activity->session_id = $most_recent_login->session_id;
            $logout_activity->save();
            Event::fire('orbit.network.checkout.force_mobileci_checkout', array($this, $logout_activity));
        }
    }
}
