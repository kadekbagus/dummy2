<?php
/**
 * Unit testing for UserAPIController::postNewUser() method.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class postNewUserTest extends OrbitTestCase
{
    /**
     * Executed only once at the beginning of the test.
     */
    public static function setUpBeforeClass()
    {
        parent::createAppStatic();

        // Truncate the data just in case previous test was not clean up
        static::truncateData();

        // Get the prefix of the table name
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_table = static::$dbPrefix . 'users';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';

        // Insert dummy data on apikeys
        DB::statement("INSERT INTO `{$apikey_table}`
                (`apikey_id`, `api_key`, `api_secret_key`, `user_id`, `status`, `created_at`, `updated_at`)
                VALUES
                (1, 'abc123', 'abc12345678910', '1', 'deleted', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'active', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'blocked', '2014-10-19 20:02:05', '2014-10-19 20:03:05'),
                (6, 'hij313', 'hijklmn0987623', '4', 'active', '2014-10-19 20:02:06', '2014-10-19 20:03:06'),
                (7, 'klm432', 'klm09876543211', '5', 'active', '2014-10-19 20:02:07', '2014-10-19 20:03:07')"
        );

        $password = array(
            'john'      => Hash::make('john'),
            'smith'     => Hash::make('smith'),
            'chuck'     => Hash::make('chuck'),
            'optimus'   => Hash::make('optimus'),
            'panther'   => Hash::make('panther')
        );

        // Insert dummy data on users
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '{$password['john']}', 'john@localhost.org', 'John', 'Doe', '2014-10-20 06:20:01', '10.10.0.11', '1', 'active', '1', '2014-10-20 06:30:01', '2014-10-20 06:31:01'),
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'active', '1', '2014-10-20 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05')"
        );

        // Insert dummy data on roles
        DB::statement("INSERT INTO `{$role_table}`
                (`role_id`, `role_name`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'Super Admin', '1', NOW(), NOW()),
                ('2', 'Guest', '1', NOW(), NOW()),
                ('3', 'Customer', '1', NOW(), NOW())"
        );

        // Insert dummy data on permissions
        DB::statement("INSERT INTO `{$permission_table}`
                (`permission_id`, `permission_name`, `permission_label`, `permission_group`, `permission_group_label`, `permission_name_order`, `permission_group_order`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'login', 'Login', 'general', 'General', '0', '0', '1', NOW(), NOW()),
                ('2', 'view_user', 'View User', 'user', 'User', '1', '1', '1', NOW(), NOW()),
                ('3', 'create_user', 'Create User', 'user', 'User', '0', '1', '1', NOW(), NOW()),
                ('4', 'view_product', 'View Product', 'product', 'Product', '1', '2', '1', NOW(), NOW()),
                ('5', 'add_product', 'Add Product', 'product', 'Product', '0', '2', '1', NOW(), nOW())"
        );

        // Insert dummy data on permission_role
        DB::statement("INSERT INTO `{$permission_role_table}`
                (`permission_role_id`, `role_id`, `permission_id`, `allowed`, `created_at`, `updated_at`)
                VALUES
                ('1', '2', '1', 'yes', NOW(), NOW()),
                ('2', '3', '1', 'yes', NOW(), NOW()),
                ('3', '3', '2', 'no', NOW(), NOW()),
                ('4', '3', '3', 'no', NOW(), NOW()),
                ('5', '3', '4', 'no', NOW(), NOW()),
                ('6', '3', '5', 'no', NOW(), NOW())"
        );
    }

    /**
     * Clear all data that has been inserted.
     */
    public static function truncateData()
    {
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_table = static::$dbPrefix . 'users';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`");
    }

    public function tearDown()
    {
        unset($_GET);
        unset($_POST);
        $_GET = array();
        $_POST = array();

        unset($_SERVER['HTTP_X_ORBIT_SIGNATURE'],
              $_SERVER['REQUEST_METHOD'],
              $_SERVER['REQUEST_URI'],
              $_SERVER['REMOTE_ADDR']);

        // Make sure we always get a fresh instance of user
        $apikeys = array(
            'abc123',
            'bcd234',
            'cde345',
            'def123',
            'efg212',
            'hij313',
            'klm432',
        );

        foreach ($apikeys as $key) {
            OrbitShopAPI::clearLookupCache($key);
        }

        // Clear every event dispatcher so we get no queue event on each
        // test
        $events = array(
            'orbit.user.postnewuser.before.auth',
            'orbit.user.postnewuser.after.auth',
            'orbit.user.postnewuser.before.authz',
            'orbit.user.postnewuser.authz.notallowed',
            'orbit.user.postnewuser.after.authz',
            'orbit.user.postnewuser.before.validation',
            'orbit.user.postnewuser.after.validation',
            'orbit.user.postnewuser.before.save',
            'orbit.user.postnewuser.after.save',
            'orbit.user.postnewuser.after.commit',
            'orbit.user.postnewuser.access.forbidden',
            'orbit.user.postnewuser.invalid.arguments',
            'orbit.user.postnewuser.general.exception',
            'orbit.user.postnewuser.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new UserAPIController();
        $this->assertInstanceOf('UserAPIController', $ctl);
    }

    public function testNoAuthData_POST_api_v1_user_new()
    {
        $url = '/api/v1/user/new';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_POST_api_v1_user_new()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = 'dummy-signature';

        $data = new stdclass();
        $data->code = Status::INVALID_SIGNATURE;
        $data->status = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testSignatureExpire_POST_api_v1_user_new()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::REQUEST_EXPIRED;
        $data->status = 'error';
        $data->message = Status::REQUEST_EXPIRED_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testAccessForbidden_POST_api_v1_new_user()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $message = Lang::get('validation.orbit.access.forbidden',
                             array('action' => 'add new user'));

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = $message;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testMissingEmail_POST_api_v1_user_new()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Add new permission name 'create_user'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'create_user';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));

        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = Lang::get('validation.required', array('attribute' => 'email'));
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidEmailFormat_POST_api_v1_dummy_user_new()
    {
        // Data to be post
        $_POST['email'] = 'wrong-format@localhost';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = Lang::get('validation.email', array('attribute' => 'email'));
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testEmailAlreadExists_POST_api_v1_user_new()
    {
        // Data to be post
        $_POST['email'] = 'chuck@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.email.exists');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testMissingPassword_POST_api_v1_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.required', array('attribute' => 'password'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testPasswordNotMatch_POST_api_v1_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = '123456';
        $_POST['password_confirmation'] = '12345A';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.confirmed', array('attribute' => 'password'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testMissingRoleId_POST_api_v1_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = '123456';
        $_POST['password_confirmation'] = '123456';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.required', array('attribute' => 'role id'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testRoleIdNotNumeric_POST_api_v1_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = '123456';
        $_POST['password_confirmation'] = '123456';
        $_POST['role_id'] = 'foo';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.numeric', array('attribute' => 'role id'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testRoleIdNotExists_POST_api_v1_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = '123456';
        $_POST['password_confirmation'] = '123456';
        $_POST['role_id'] = 99;

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.empty.role');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_POST_api_v1_user_new()
    {
        // Number of user account before this operation
        $numBefore = User::count();

        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = 'cool-password';
        $_POST['password_confirmation'] = 'cool-password';
        $_POST['role_id'] = 3;

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();
        $_GET['apiver'] = '1.0';

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '10.10.0.99';

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('george@localhost.org', $response->data->username);
        $this->assertSame('george@localhost.org', $response->data->user_email);
        $this->assertSame('3', (string)$response->data->user_role_id);
        $this->assertSame($_SERVER['REMOTE_ADDR'], (string)$response->data->user_ip);
        $this->assertSame('3', (string)$response->data->modified_by);
        $this->assertSame('pending', (string)$response->data->status);
        $this->assertTrue(property_exists($response->data, 'user_id'));

        // userdetail relationship property
        $this->assertTrue(property_exists($response->data, 'userdetail'));

        // apikey relationship property
        $this->assertTrue(property_exists($response->data, 'apikey'));

        // Check the user detail on database, it should be exists also
        $details = UserDetail::where('user_id', $response->data->user_id)->first();
        $this->assertInstanceOf('UserDetail', $details);
        $this->assertSame((string)$response->data->user_id, (string)$details->user_id);

        // Check the api keys on database, it should be active keys
        $apikey = Apikey::active()->where('user_id', $response->data->user_id)->first();
        $this->assertInstanceOf('Apikey', $apikey);
        $this->assertSame((string)$response->data->user_id, (string)$apikey->user_id);
        $this->assertSame(40, strlen($apikey->api_key));
        $this->assertSame(64, strlen($apikey->api_secret_key));

        $numAfter = User::count();
        $this->assertSame($numBefore + 1, $numAfter);
    }

    public function testSavedThenRollback_POST_api_v1_user_new()
    {
        // Register an event on 'orbit.user.postnewuser.after.save'
        // and thrown some exception so the data that has been saved
        // does not commited
        Event::listen('orbit.user.postnewuser.after.save', function($controller, $user)
        {
            throw new Exception('This is bad bro!', 99);
        });

        // Number of user account before this operation
        $numBefore = User::count();

        // Data to be post
        $_POST['email'] = 'george2@localhost.org';
        $_POST['password'] = 'cool-password';
        $_POST['password_confirmation'] = 'cool-password';
        $_POST['role_id'] = 3;

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();
        $_GET['apiver'] = '1.0';

        $url = '/api/v1/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '10.10.0.99';

        $user = new stdclass();
        $user->username = $_POST['email'];
        $user->user_email = $_POST['email'];
        $user->status = 'pending';

        $message = Lang::get('validation.confirmed', array('attribute' => 'password'));
        $data = new stdclass();
        $data->code = 99;
        $data->status = 'error';
        $data->message = 'This is bad bro!';
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);

        $numAfter = User::count();
        $this->assertSame($numBefore, $numAfter);
    }
}
