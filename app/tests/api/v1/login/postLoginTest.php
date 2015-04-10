<?php
/**
 * Unit testing for LoginAPIController::postLogin() method.
 *
 * @author Tian <tian@dominopos.com>
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class postLoginTest extends OrbitTestCase
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
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $user_table = static::$dbPrefix . 'users';
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';

        // Insert dummy data on roles table.
        DB::statement("INSERT INTO `{$role_table}`
                (`role_id`, `role_name`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'Super Admin', '1', NOW(), NOW()),
                ('2', 'Guest', '1', NOW(), NOW()),
                ('3', 'Customer', '1', NOW(), NOW())"
        );

        // Insert dummy data on permissions table.
        DB::statement("INSERT INTO `{$permission_table}`
                (`permission_id`, `permission_name`, `permission_label`, `permission_group`, `permission_group_label`, `permission_name_order`, `permission_group_order`, `permission_default_value`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'login', 'Login', 'general', 'General', '0', '0', 'no', '1', NOW(), NOW()),
                ('2', 'view_user', 'View User', 'user', 'User', '1', '1', 'no', '1', NOW(), NOW()),
                ('3', 'create_user', 'Create User', 'user', 'User', '0', '1', 'no', '1', NOW(), NOW()),
                ('4', 'view_product', 'View Product', 'product', 'Product', '1', '2', 'no', '1', NOW(), NOW()),
                ('5', 'add_product', 'Add Product', 'product', 'Product', '0', '2', 'no', '1', NOW(), NOW())"
        );

        // Insert dummy data on permission_role table.
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


        // create dummy hash password.
        $password = array(
            'john'      => Hash::make('john'),
            'smith'     => Hash::make('smith'),
            'chuck'     => Hash::make('chuck'),
            'optimus'   => Hash::make('optimus'),
            'panther'   => Hash::make('panther'),
            'ironman'   => Hash::make('ironman')
        );

        // Insert dummy data on users table.
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '{$password['john']}', 'john@localhost.org', 'John', 'Doe', '2014-10-20 06:20:01', '10.10.0.11', '1', 'active', '1', '2014-10-20 06:30:01', '2014-10-20 06:31:01'),
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'active', '1', '2014-10-20 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05'),
                ('6', 'ironman', '{$password['ironman']}', 'ironman@localhost.org', 'Iron', 'Man', '2014-11-20 06:20:05', '10.10.0.17', '3', 'pending', '1', '2014-11-20 06:30:05', '2014-10-20 06:31:05')"
        );

        // Insert dummy data on apikeys table.
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
    }

    /**
     * Clear all data that has been inserted.
     */
    public static function truncateData()
    {
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $user_table = static::$dbPrefix . 'users';
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';

        DB::unprepared("TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`;
                        TRUNCATE `{$role_table}`;
                        ");
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

        // Clear every event dispatcher so we get no queue event on each test.
        $events = array(
            'orbit.login.postlogin.before.auth',
            'orbit.login.postlogin.after.auth',
            'orbit.login.postlogin.before.authz',
            'orbit.login.postlogin.authz.notallowed',
            'orbit.login.postlogin.after.authz',
            'orbit.login.postlogin.before.validation',
            'orbit.login.postlogin.after.validation',
            'orbit.login.postlogin.before.save',
            'orbit.login.postlogin.after.save',
            'orbit.login.postlogin.after.commit',
            'orbit.login.postlogin.access.forbidden',
            'orbit.login.postlogin.invalid.arguments',
            'orbit.login.postlogin.general.exception',
            'orbit.login.postlogin.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new LoginAPIController();
        $this->assertInstanceOf('LoginAPIController', $ctl);
    }

    // testcase: right email and password.
    public function testRightEmailAndPassword_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = 'smith@localhost.org';
        $_POST['password'] = 'smith';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();
        $response = json_decode($return);

        // test data.
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame(Status::OK_MSG, $response->message);

        // test user data.
        $this->assertTrue(property_exists($response->data, 'user_id'));
        $this->assertSame('2', (string)$response->data->user_id);
        $this->assertSame('smith', $response->data->username);
        $this->assertSame('smith@localhost.org', $response->data->user_email);
        $this->assertSame('John', $response->data->user_firstname);
        $this->assertSame('Smith', $response->data->user_lastname);
        $this->assertSame('3', (string)$response->data->user_role_id);
        $this->assertSame('active', (string)$response->data->status);
        $this->assertSame('1', (string)$response->data->modified_by);

        // test apikey data.
        $this->assertTrue(property_exists($response->data, 'apikey'));
        $this->assertSame('bcd234', (string)$response->data->apikey->api_key);
        $this->assertSame('bcd23456789010', (string)$response->data->apikey->api_secret_key);
        $this->assertSame('2', (string)$response->data->apikey->user_id);
        $this->assertSame('active', (string)$response->data->apikey->status);

        // test userdetail data.
        $this->assertTrue(property_exists($response->data, 'userdetail'));
    }

    // testcase: wrong email and password.
    public function testWrongEmailAndPassword_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = 'wrong@email.com';
        $_POST['password'] = 'wrongpassword';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();
        $response = json_decode($return);

        // test data.
        $this->assertSame(Status::ACCESS_DENIED, (int)$response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame(Lang::get('validation.orbit.access.loginfailed'), $response->message);
    }

    // testcase: empty email.
    public function testEmptyEmail_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = '';
        $_POST['password'] = 'smith';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();

        // expected data.
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = Lang::get('validation.required', array('attribute' => 'email'));
        $data->data = NULL;
        $expect = json_encode($data);

        // test data.
        $this->assertSame($expect, $return);
    }

    // testcase: empty password.
    public function testEmptyPassword_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = 'smith@localhost.org';
        $_POST['password'] = '';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();

        // expected data.
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = Lang::get('validation.required', array('attribute' => 'password'));
        $data->data = NULL;
        $expect = json_encode($data);

        // test data.
        $this->assertSame($expect, $return);
    }

    // testcase: user status is blocked
    public function testUserStatusIsBlocked_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = 'optimus@localhost.org';
        $_POST['password'] = 'optimus';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();
        $response = json_decode($return);

        // test data.
        $this->assertSame(Status::ACCESS_DENIED, (int)$response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame(Lang::get('validation.orbit.access.loginfailed'), $response->message);
    }

    // testcase: user status is pending
    public function testUserStatusIsDeleted_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = 'panther@localhost.org';
        $_POST['password'] = 'panther';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();
        $response = json_decode($return);

        // test data.
        $this->assertSame(Status::ACCESS_DENIED, (int)$response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame(Lang::get('validation.orbit.access.loginfailed'), $response->message);
    }

    // testcase: user status is pending
    public function testUserStatusIsPending_POST_api_v1_login()
    {
        // mocking data.
        $url = '/api/v1/login';
        $_POST['email'] = 'ironman@localhost.org';
        $_POST['password'] = 'ironman';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $return = $this->call('POST', $url)->getContent();
        $response = json_decode($return);

        // test data.
        $this->assertSame(Status::ACCESS_DENIED, (int)$response->code);
        $this->assertSame('error', $response->status);
        $this->assertSame(Lang::get('validation.orbit.access.loginfailed'), $response->message);
    }
}
