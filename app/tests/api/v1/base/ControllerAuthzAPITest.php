<?php
/**
 * Unit test for DummyAPIController.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class ControllerAuthzAPITest extends OrbitTestCase
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
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`");
    }

    public function testObjectInstance()
    {
        $ctl = new DummyAPIController();
        $this->assertInstanceOf('DummyAPICOntroller', $ctl);
    }

    public function tearDown()
    {
        unset($_GET);
        unset($_POST);
        $_GET = array();
        $_POST = array();

        unset($_SERVER['HTTP_X_ORBIT_SIGNATURE'],
              $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI']);

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
            'orbit.dummy.gethisname.before.render',
            'orbit.dummy.postreguser.before.auth',
            'orbit.dummy.postreguser.after.auth',
            'orbit.dummy.postreguser.before.authz',
            'orbit.dummy.postreguser.authz.notallowed',
            'orbit.dummy.postreguser.after.authz',
            'orbit.dummy.postreguser.before.validation',
            'orbit.dummy.postreguser.after.validation',
            'orbit.dummy.postreguser.before.save',
            'orbit.dummy.postreguser.after.save',
            'orbit.dummy.postreguser.after.commit',
            'orbit.dummy.postreguser.access.forbidden',
            'orbit.dummy.postreguser.invalid.arguments',
            'orbit.dummy.postreguser.general.exception',
            'orbit.dummy.postreguser.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testGET_api_v1_dummy_hisname()
    {
        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        $expect = json_encode($data);
        $return = $this->call('GET', '/api/v1/dummy/hisname')->getContent();
        $this->assertSame($expect, $return);
    }

    public function testGET_EventFired_api_v1_dummy_hisname()
    {
        $path = app_path();
        require $path . DS . 'events' . DS . 'enabled' . DS . '99-dummy.php';

        $name = new stdclass();
        $name->first_name = 'Chuck';
        $name->last_name = 'Norris';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        // Pass query string
        $_GET['call'] = 'chuck';

        $expect = json_encode($data);
        $return = $this->call('GET', '/api/v1/dummy/hisname')->getContent();
        $this->assertSame($expect, $return);

        unset($_GET['call']);
    }

    public function testNoAuthData_GET_api_v1_dummy_hisname_auth()
    {
        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', '/api/v1/dummy/hisname/auth')->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_GET_api_v1_dummy_hisname_auth()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/hisname/auth?' . http_build_query($_GET);

        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = 'random-data';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;

        $data = new stdclass();
        $data->code = Status::INVALID_SIGNATURE;
        $data->status = 'error';
        $data->message = Status::INVALID_SIGNATURE_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_GET_api_v1_dummy_hisname_auth()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/hisname/auth?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_GET_api_v1_dummy_hisname_auth_longExpiresTime()
    {
        // Set expires config to 3 hours
        $_3hours = 3600 * 3;
        $_2hours = 3600 * 2;
        $oldExpires = Config::get('orbit.api.signature.expiration');
        Config::set('orbit.api.signature.expiration', $_3hours);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - $_2hours;

        $url = '/api/v1/dummy/hisname/auth?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);

        Config::set('orbit.api.signature.expiration', $oldExpires);
    }

    public function testReqSignatureExpires_GET_api_v1_dummy_hisname_auth_longExpiresTime()
    {
        // Set expires config to 3 hours
        $_3hours = 3600 * 3;
        $oldExpires = Config::get('orbit.api.signature.expiration');
        Config::set('orbit.api.signature.expiration', $_3hours);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - ($_3hours + 5);

        $url = '/api/v1/dummy/hisname/auth?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::REQUEST_EXPIRED;
        $data->status = 'error';
        $data->message = Status::REQUEST_EXPIRED_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);

        Config::set('orbit.api.signature.expiration', $oldExpires);
    }

    public function testAccessForbidden_GET_api_v1_dummy_hisname_authz()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/hisname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = 'You do not have permission to say his name';
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testSignatureExpire_GET_api_v1_dummy_hisname_authz()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/dummy/hisname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::REQUEST_EXPIRED;
        $data->status = 'error';
        $data->message = Status::REQUEST_EXPIRED_MSG;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_GET_api_v1_dummy_hisname_authz()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';     // Chuck Norris
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/hisname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        // Add new permission name 'say_his_name'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'say_his_name';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testPOST_api_v1_dummy_myname()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        $expect = json_encode($data);
        $return = $this->call('POST', '/api/v1/dummy/myname')->getContent();
        $this->assertSame($expect, $return);
    }

    public function testNoAuthData_api_v1_dummy_myname_auth()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', '/api/v1/dummy/myname/auth')->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_POST_api_v1_dummy_myname_auth()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/myname/auth?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $name = new stdclass();
        $name->first_name = 'John';
        $name->last_name = 'Smith';

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_POST_api_v1_dummy_myname_authz()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/myname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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

    public function testSignatureExpire_POST_api_v1_dummy_myname_authz()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/dummy/myname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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

    public function testAccessForbidden_POST_api_v1_dummy_myname_authz()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/myname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = 'You do not have permission to say your name';
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_POST_api_v1_dummy_myname_authz()
    {
        // Simulate POST
        $_POST['firstname'] = 'John';
        $_POST['lastname'] = 'Smith';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/myname/authz?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Add new permission name 'say_my_name'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'say_my_name';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));

        $name = new stdclass();
        $name->first_name = $_POST['firstname'];
        $name->last_name = $_POST['lastname'];

        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $name;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testMissingEmail_POST_api_v1_dummy_user_new()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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
        $_POST['email'] = 'dummy@localhost';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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

    public function testEmailAlreadExists_POST_api_v1_dummy_user_new()
    {
        // Data to be post
        $_POST['email'] = 'chuck@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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

    public function testMissingPassword_POST_api_v1_dummy_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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

    public function testPasswordLessThen5_POST_api_v1_dummy_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = '123';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.min.string', array(
            'min' => '5',
            'attribute' => 'password'
        ));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testPasswordNotMatch_POST_api_v1_dummy_user_new()
    {
        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = '123456';
        $_POST['password_confirmation'] = '12345A';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
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

    public function testReqOK_POST_api_v1_dummy_user_new()
    {
        // Number of user account before this operation
        $numBefore = User::count();

        // Data to be post
        $_POST['email'] = 'george@localhost.org';
        $_POST['password'] = 'cool-password';
        $_POST['password_confirmation'] = 'cool-password';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $user = new stdclass();
        $user->username = $_POST['email'];
        $user->user_email = $_POST['email'];
        $user->status = 'pending';

        $message = Lang::get('validation.confirmed', array('attribute' => 'password'));
        $data = new stdclass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = $user;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);

        $numAfter = User::count();
        $this->assertSame($numBefore + 1, $numAfter);
    }

    public function testSavedThenRollback_POST_api_v1_dummy_user_new()
    {
        // Register an event on 'orbit.dummy.postreguser.after.save'
        // and thrown some exception so the data that has been saved
        // does not commited
        Event::listen('orbit.dummy.postreguser.after.save', function($controller, $user)
        {
            throw new Exception('This is bad bro!', 99);
        });

        // Number of user account before this operation
        $numBefore = User::count();

        // Data to be post
        $_POST['email'] = 'george2@localhost.org';
        $_POST['password'] = 'cool-password';
        $_POST['password_confirmation'] = 'cool-password';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/dummy/user/new?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

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
