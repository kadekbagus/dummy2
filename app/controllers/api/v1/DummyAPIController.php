<?php
/**
 * A dummy API controller for testing purpose.
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

class DummyAPIController extends ControllerAPI
{
    public function getUserOutOfNetwork()
    {
        $activity = Activity::unknown('captive')
                            ->setActivityType('network_check_out');

        // Get PWU Server IP address
        $userIP = $_SERVER['REMOTE_ADDR'];
        $email = trim(OrbitInput::get('email', NULL));

        $logFile = storage_path() . '/logs/pwu-call.log';
        $pwuIPFile = storage_path() . '/logs/pwu-ip.txt';

        $now = date('Y-m-d H:i:s');
        $format = "[%s] %s; checkout; Email %s do network checkout; %s";
        $httpCode = 200;
        $message = '';

        try {
            $ips = [];
            if (file_exists($pwuIPFile)) {
                $ips = explode("\n", file_get_contents($pwuIPFile));
                $ips = array_map('trim', $ips);
                $ips = array_filter($ips);
            }
            $ips[] = '127.0.0.1';

            if (empty($email)) {
                $message = sprintf($format, $now, $userIP, $email, 'Failed: email is empty');
                $httpCode = 400;
                throw new Exception ($message, 1);
            }

            if (! in_array($userIP, $ips)) {
                $message = sprintf($format, $now, $userIP, $email, 'Failed: IP is not allowed to access this resource');
                $httpCode = 403;
                throw new Exception ($message, 1);
            }

            // Successfull
            $message = sprintf($format, $now, $userIP, $email, 'OK');
            $this->response->message = $message;
            $activity->setUser(NULL)
                     ->setActivityName('Network checkout ok')
                     ->setActivityNameLong($message)
                     ->setModuleName('Network')
                     ->responseOK();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            $activity->setUser('guest')
                     ->setActivityName('Network checkout failed')
                     ->setActivityNameLong($e->getMessage())
                     ->setNotes($e->getMessage())
                     ->setModuleName('Network')
                     ->responseFailed();
        }

        $activity->user_email = $email;
        $activity->save();
        file_put_contents($logFile, $message . "\n", FILE_APPEND);

        return $this->render($httpCode);
    }

    /**
     * PWU Lippo mall captive portal integration with ourbit. The Lippo Mall
     * captive portal will send email address and mac address from query string.
     *
     * i.e:
     * http://orbit.box/?email=foo@bar.com&m=AA:BB:CC:DD:EE
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function getUserSignInNetwork()
    {
        $activity = Activity::unknown('captive')
                            ->setActivityType('network_check_in');

        // Get PWU Server IP address
        $userIP = $_SERVER['REMOTE_ADDR'];
        $email = trim(OrbitInput::get('email', NULL));
        $mac = trim(OrbitInput::get('m', NULL));
        $logFile = storage_path() . '/logs/pwu-call.log';
        $now = date('Y-m-d H:i:s');
        $format = "[%s] %s; checkin; Email %s do network checkin; %s";
        $httpCode = 200;
        $message = '';

        try {
            if (empty($email)) {
                $message = sprintf($format, $now, $userIP, $email, 'Failed: email is empty');
                $httpCode = 400;
                throw new Exception ($message, 1);
            }

            // Successfull
            $message = sprintf($format, $now, $userIP, $email, 'OK');
            $this->response->message = $message;
            $activity->setUser(NULL)
                     ->setActivityName('Network check in')
                     ->setActivityNameLong('Network Check In')
                     ->setNotes($message)
                     ->setModuleName('Network')
                     ->responseOK();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            $activity->setUser('guest')
                     ->setActivityName('Network checkin failed')
                     ->setActivityNameLong('Network checkin failed')
                     ->setNotes($e->getMessage())
                     ->setModuleName('Network')
                     ->responseFailed();
        }

        $activity->user_email = $email;
        $activity->save();
        file_put_contents($logFile, $message . "\n", FILE_APPEND);

        return $this->render($httpCode);
    }

    /**
     * Return time of the server in unix timestamp.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param `string`      `$mode`     (optional)  Unix timestamp $mode
     * @return int
     */
    public function getServerTime()
    {
        $format = OrbitInput::get('format', 'U');
        return date($format);
    }

    public function IamOK()
    {
        return $this->render();
    }

    public function unsupported()
    {
        $this->response->code = Status::UNKNOWN_ERROR;
        $this->response->status = 'error';
        $this->response->message = 'Call to this URL is unsupported.';
        $this->response->data = NULL;

        return $this->render(410);
    }

    public function hisName()
    {
        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';
        $this->response->data = $name;

        $output = $this->render();
        Event::fire('orbit.dummy.gethisname.before.render', array($this, &$output));

        return $output;
    }

    public function hisNameAuth()
    {
        try {
            // Require authentication
            $this->checkAuth();

            $name = new stdclass();
            $name->first_name = 'John';
            $name->last_name = 'Smith';
            $this->response->data = $name;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }

        return $this->render();
    }

    public function hisNameAuthz()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            if (! ACL::create($user)->isAllowed('say_his_name')) {
                ACL::throwAccessForbidden('You do not have permission to say his name');
            }

            $name = new stdclass();
            $name->first_name = 'John';
            $name->last_name = 'Smith';
            $this->response->data = $name;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }

        return $this->render($httpCode);
    }

    public function myName()
    {
        $name = new stdclass();
        $name->first_name = OrbitInput::post('firstname');
        $name->last_name = OrbitInput::post('lastname');
        $this->response->data = $name;

        return $this->render();
    }

    public function myNameAuth()
    {
        try {
            // Require authentication
            $this->checkAuth();

            $name = new stdclass();
            $name->first_name = OrbitInput::post('firstname');
            $name->last_name = OrbitInput::post('lastname');
            $this->response->data = $name;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }

        return $this->render();
    }

    public function myNameAuthz()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            if (! ACL::create($user)->isAllowed('say_my_name')) {
                ACL::throwAccessForbidden('You do not have permission to say your name');
            }

            $name = new stdclass();
            $name->first_name = OrbitInput::post('firstname');
            $name->last_name = OrbitInput::post('lastname');
            $this->response->data = $name;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }

        return $this->render($httpCode);
    }

    public function postRegisterUserAuthz()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.dummy.postreguser.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.dummy.postreguser.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.dummy.postreguser.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_user')) {
                Event::fire('orbit.dummy.postreguser.authz.notallowed', array($this, $user));

                ACL::throwAccessForbidden('You do not have permission to add new user');
            }
            Event::fire('orbit.dummy.postreguser.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $email = OrbitInput::post('email');
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');

            $validator = Validator::make(
                array(
                    'email'     => $email,
                    'password'  => $password,
                    'password_confirmation' => $password2,
                ),
                array(
                    'email'     => 'required|email|orbit.email.exists',
                    'password'  => 'required|min:5|confirmed',
                )
            );

            Event::fire('orbit.dummy.postreguser.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.dummy.postreguser.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newuser = new User();
            $newuser->username = $email;
            $newuser->user_email = $email;
            $newuser->user_password = Hash::make($password);
            $newuser->status = 'pending';

            Event::fire('orbit.dummy.postreguser.before.save', array($this, $newuser));

            $newuser->save();

            $newuser->setVisible(array('username', 'user_email', 'status'));

            Event::fire('orbit.dummy.postreguser.after.save', array($this, $newuser));
            $this->response->data = $newuser;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.dummy.postreguser.after.commit', array($this, $newuser));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.dummy.postreguser.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.dummy.postreguser.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.user.postnewuser.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = NULL;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.dummy.postreguser.general.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.dummy.postreguser.before.render', array($this, &$output));

        return $output;
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
