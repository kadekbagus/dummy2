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

class DummyAPIController extends ControllerAPI
{
    public function IamOK()
    {
        return $this->render();
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
