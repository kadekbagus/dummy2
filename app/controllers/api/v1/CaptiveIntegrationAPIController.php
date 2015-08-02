<?php
/**
 * Handle integration with Orbit Captive Portal to provide useful metrics
 * and seamless integration.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Net\MacAddr;

class CaptiveIntegrationAPIController extends ControllerAPI
{
    public function getUserOutOfNetwork()
    {
        $activity = Activity::unknown('captive')
                            ->setActivityType('network');

        // Get captive Server IP address
        $captiveIP = $_SERVER['REMOTE_ADDR'];
        $payload = trim(OrbitInput::get('payload', NULL));

        $captiveLogFile = storage_path() . '/logs/captive-call.log';

        $now = date('Y-m-d H:i:s');
        $format = "[%s] %s; checkout; Email %s do network checkout; %s";
        $httpCode = 200;
        $message = '';
        $email = 'unknown';

        try {
            Event::fire('orbit.network.checkout.before.auth', array($this));

            // Require authentication
            // $this->checkAuth();

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
            if (! isset($output['email'])) {

                $message = 'Email argument on payload is empty.';
                throw new Exception ($message, 1);
            }

            if (! isset($output['mac'])) {
                $message = 'Mac address argument on payload is empty.';
                throw new Exception ($message, 1);
            }
            $email = $output['email'];
            $mac = $output['mac'];

            if (empty($email)) {
                $message = sprintf($format, $now, $captiveIP, $email, 'Failed: email is empty');
                $httpCode = 400;
                throw new Exception ($message, 1);
            }

            // Successfull
            $message = sprintf($format, $now, $captiveIP, $email, 'OK');
            $this->response->message = $message;
            $activity->setUser(NULL)
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
     * PWU Lippo mall captive portal integration with ourbit. The Lippo Mall
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
        $captiveIPFile = storage_path() . '/logs/captive-ip.txt';

        $now = date('Y-m-d H:i:s');
        $format = "[%s] %s; checkin; Email %s do network checkin; %s";
        $httpCode = 200;
        $message = '';
        $email = 'unknown';
        $user = 'guest';
        $customer = 'guest';

        $activity->setUser($customer)
                 ->setActivityName('network_checkin_ok')
                 ->setActivityNameLong('Network Check In')
                 ->setNotes($message)
                 ->setModuleName('Network')
                 ->responseOK()
                 ->save();

        try {
            $this->beginTransaction();

            Event::fire('orbit.network.checkin.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

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
            if (! isset($output['email'])) {
                $message = 'Email argument on payload is empty.';
                throw new Exception ($message, 1);
            }

            if (! isset($output['mac'])) {
                $message = 'Mac address argument on payload is empty.';
                throw new Exception ($message, 1);
            }
            $email = $output['email'];
            $mac = $output['mac'];

            $macAddr = MacAddr::create($mac);
            if (! $macAddr->isValid()) {
                $message = 'Mac address format is not valid.';
                throw new Exception ($message, 1);
            }
            $macAddr->reformat(':');

            // Get the most recent user
            $macModel = MacAddress::firstOrCreate(['mac_address' => $macAddr->getMac(), 'user_email' => $email]);

            if (empty($email)) {
                $message = sprintf($format, $now, $captiveIP, $email, 'Failed: email is empty');
                $httpCode = 400;
                throw new Exception ($message, 1);
            }

            // Need to have the customer now so it can be logged on the activity
            $customer = User::Consumers()
                            ->excludeDeleted()
                            ->where('user_email', $email)
                            ->first();

            if (empty($customer)) {
                // Register this customer and get the raw object
                $_POST['email'] = $email;
                $response = LoginAPIController::create('raw')
                                              ->setUseTransaction(FALSE)
                                              ->postRegisterUserInShop();

                if ($response->code !== 0) {
                    throw new Exception($response->message, $response->code);
                }
                $customer = $response->data;
            }

            $this->commit();

            // Successfull
            $message = sprintf($format, $now, $captiveIP, $email, 'OK');
            $this->response->message = $message;

            $activity->setUser($customer)
                     ->setNotes($message);

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

    protected function registerCustomValidation()
    {
        // Check user email address, it should not exists
        Validator::extend('orbit.email.exists', function($attribute, $value, $parameters)
        {
            $user = User::excludeDeleted()
                        ->where('user_email', $value)
                        ->first();

            if (! empty($user)) {
                return FALSE;
            }

            App::instance('orbit.validation.user', $user);

            return TRUE;
        });
    }
}
