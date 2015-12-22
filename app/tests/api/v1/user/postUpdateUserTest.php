<?php
/**
 * Unit testing for UserAPIController::postUpdateUser() method.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class postUpdateUserTest extends OrbitTestCase
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
            'panther'   => Hash::make('panther'),
            'droopy'   => Hash::make('droopy')
        );

        // Insert dummy data on users
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '{$password['john']}', 'john@localhost.org', 'John', 'Doe', '2014-10-20 06:20:01', '10.10.0.11', '1', 'active', '1', '2014-10-20 06:30:01', '2014-10-20 06:31:01'),
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'active', '1', '2014-10-20 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05'),
                ('6', 'droopy', '{$password['droopy']}', 'droopy@localhost.org', 'Droopy', 'Dog', '2014-10-20 06:20:06', '10.10.0.14', '3', 'pending', '1', '2014-10-20 06:30:06', '2014-10-20 06:31:06')"
        );

        // Insert dummy data on user_details
        DB::statement("INSERT INTO `{$user_detail_table}`
                    (user_detail_id, user_id, merchant_id, merchant_acquired_date, address_line1, address_line2, address_line3, postal_code, city_id, city, province_id, province, country_id, country, currency, currency_symbol, birthdate, gender, relationship_status, phone, photo, number_visit_all_shop, amount_spent_all_shop, average_spent_per_month_all_shop, last_visit_any_shop, last_visit_shop_id, last_purchase_any_shop, last_purchase_shop_id, last_spent_any_shop, last_spent_shop_id, modified_by, created_at, updated_at)
                    VALUES
                    ('1', '1', '1', '2014-10-21 06:20:01', 'Jl. Raya Semer', 'Kerobokan', 'Near Airplane Statue', '60219', '1', 'Denpasar', '1', 'Bali', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-02', 'm', 'single',       '081234567891', 'images/customer/01.png', '10', '8100000.00', '1100000.00', '2014-05-21 12:12:11', '1', '2014-10-16 12:12:12', '1', '1100000.00', '1', '1', '2014-10-11 06:20:01', '2014-10-11 06:20:01'),
                    ('2', '2', '2', '2014-10-21 06:20:02', 'Jl. Raya Semer2', 'Kerobokan2', 'Near Airplane Statue2', '60229', '2', 'Denpasar2', '2', 'Bali2', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-02', 'm', 'single',  '081234567892', 'images/customer/02.png', '11', '9000000.00', '9200000.00', '2014-02-21 12:12:12', '2', '2014-10-17 12:12:12', '2', '1500000.00', '2', '1', '2014-10-12 06:20:01', '2014-10-12 06:20:02'),
                    ('3', '3', '5', '2014-10-21 06:20:03', 'Jl. Raya Semer3', 'Kerobokan3', 'Near Airplane Statue3', '60239', '3', 'Denpasar3', '3', 'Bali3', '62', 'Indonesia', 'EUR', '€', '1980-04-03', 'm', 'married',   '081234567893', 'images/customer/03.png', '12', '8300000.00', '5300000.00', '2014-01-21 12:12:13', '3', '2014-10-18 12:12:12', '3', '1400000.00', '3', '1', '2014-10-13 06:20:01', '2014-10-13 06:20:03'),
                    ('4', '4', '4', '2014-10-21 06:20:04', 'Jl. Raya Semer4', 'Kerobokan4', 'Near Airplane Statue4', '60249', '4', 'Denpasar4', '4', 'Bali4', '62', 'Indonesia', 'IDR', 'Rp', '1987-04-04', 'm', 'married',  '081234567894', 'images/customer/04.png', '13', '8400000.00', '1400000.00', '2014-10-21 12:12:14', '4', '2014-10-19 12:12:12', '4', '1300000.00', '4', '1', '2014-10-14 06:20:04', '2014-10-14 06:20:04'),
                    ('5', '5', '5', '2014-10-21 06:20:05', 'Jl. Raya Semer5', 'Kerobokan5', 'Near Airplane Statue5', '60259', '5', 'Denpasar5', '5', 'Bali5', '62', 'Indonesia', 'IDR', 'Rp', '1975-02-05', 'm', 'single',  '081234567895', 'images/customer/05.png', '14', '8500000.00', '1500000.00', '2014-10-29 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05'),
                    ('6', '6', '5', '2014-10-21 06:20:06', 'Orchard Road', 'Orchard6', 'Near Airplane Statue6', '60259', '6', 'Singapore6', '20', 'Singapore6', '61', 'Singapore', 'SGD', 'SG', '1987-02-05', 'm', 'single',  '081234567896', 'images/customer/06.png', '15', '8600000.00', '1500000.00', '2014-11-21 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05')"
        );

        // Insert dummy data on roles
        DB::statement("INSERT INTO `{$role_table}`
                (`role_id`, `role_name`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'Super Admin', '1', NOW(), NOW()),
                ('2', 'Guest', '1', NOW(), NOW()),
                ('3', 'Customer', '1', NOW(), NOW()),
                ('4', 'Merchant', '1', NOW(), NOW()),
                ('5', 'Retailer', '1', NOW(), NOW())"
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
            'orbit.user.postupdateuser.before.auth',
            'orbit.user.postupdateuser.after.auth',
            'orbit.user.postupdateuser.before.authz',
            'orbit.user.postupdateuser.authz.notallowed',
            'orbit.user.postupdateuser.after.authz',
            'orbit.user.postupdateuser.before.validation',
            'orbit.user.postupdateuser.after.validation',
            'orbit.user.postupdateuser.before.save',
            'orbit.user.postupdateuser.after.save',
            'orbit.user.postupdateuser.after.commit',
            'orbit.user.postupdateuser.access.forbidden',
            'orbit.user.postupdateuser.invalid.arguments',
            'orbit.user.postupdateuser.general.exception',
            'orbit.user.postupdateuser.before.render'
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

    public function testNoAuthData_POST_api_v1_user_update()
    {
        $url = '/api/v1/user/update';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_POST_api_v1_user_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

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

    public function testSignatureExpire_POST_api_v1_user_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/user/update?' . http_build_query($_GET);

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

    public function testAccessForbidden_POST_api_v1_new_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $updateUserLang = Lang::get('validation.orbit.actionlist.update_user');
        $message = Lang::get('validation.orbit.access.forbidden',
                             array('action' => $updateUserLang));

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = $message;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);

        // Add new permission name 'edit_user'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'edit_user';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));
    }

    public function testMissingUserId_POST_api_v1_user_update()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Add new permission name 'update_user'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'update_user';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));

        $message = Lang::get('validation.required', array('attribute' => 'user id'));
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidEmailFormat_POST_api_v1_dummy_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 3;
        $_POST['email'] = 'wrong-format@localhost';
        $_POST['username'] = 'snoopy';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

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

    public function testEmailAlreadExists_POST_api_v1_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['email'] = 'droopy@localhost.org';
        $_POST['username'] = 'snoopy';
        $_POST['role_id'] = 4;
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

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

    public function testUsernameAlreadExists_POST_api_v1_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['username'] = 'chuck';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.exists.username');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testRoleIdNotExists_POST_api_v1_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['role_id'] = 99;
        $_POST['username'] = 'snoopy';
        $_POST['email'] = 'snoopy@localhost.org';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

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

    public function testStatusNotExists_POST_api_v1_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['status'] = 'dummy';
        $_POST['username'] = 'snoopy';
        $_POST['email'] = 'snoopy@localhost.org';
        $_POST['role_id'] = 3;

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $message = Lang::get('validation.orbit.empty.user_status');
        $data = new stdclass();
        $data->code = Status::INVALID_ARGUMENT;
        $data->status = 'error';
        $data->message = $message;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testReqOK_SameEmail_POST_api_v1_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['email'] = 'smith@localhost.org';
        $_POST['role_id'] = 4;  // Merchant
        $_POST['status'] = 'blocked';
        $_POST['username'] = 'iansmith';
        $_POST['firstname'] = 'Ian';
        $_POST['lastname'] = 'Smith Jr.';
        $_POST['birthdate'] = '1985-12-05';
        $_POST['gender'] = 'm';
        $_POST['address_line1'] = 'Banjar Semer';
        $_POST['city'] = 'Surabaya';
        $_POST['country'] = 'Indonesia';
        $_POST['province'] = 'Jawa Timur';
        $_POST['postal_code'] = '612345';
        $_POST['phone'] = '031-7412345';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();
        $_GET['apiver'] = '1.0';

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);

        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);

        $smith = User::with(array('userdetail', 'apikey'))->find(2);
        $this->assertSame('iansmith', $smith->username);
        $this->assertSame('Ian', $smith->user_firstname);
        $this->assertSame('Smith Jr.', $smith->user_lastname);
        $this->assertSame('smith@localhost.org', $smith->user_email);
        $this->assertSame('4', (string)$smith->user_role_id);
        $this->assertSame('3', (string)$smith->modified_by);
        $this->assertSame('blocked', (string)$smith->status);
        $this->assertTrue(property_exists($response->data, 'user_id'));

        // userdetail relationship property
        $this->assertTrue(property_exists($response->data, 'userdetail'));

        // apikey relationship property
        $this->assertTrue(property_exists($response->data, 'apikey'));

        // Check the user detail on database, it should be exists also
        $details = UserDetail::where('user_id', $response->data->user_id)->first();
        $this->assertInstanceOf('UserDetail', $details);
        $this->assertSame((string)$response->data->user_id, (string)$details->user_id);

        // Check the api keys on database, it should be blocked by now
        $apikey = Apikey::where('user_id', $response->data->user_id)->first();
        $this->assertInstanceOf('Apikey', $apikey);
        $this->assertSame((string)$response->data->user_id, (string)$apikey->user_id);
        $this->assertSame('blocked', (string)$apikey->status);
    }

    public function testReqOK_DifferentEmail_PendingToActive_POST_api_v1_user_update()
    {
        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['email'] = 'tom@localhost.org';
        $_POST['role_id'] = 4;  // Merchant
        $_POST['status'] = 'active';
        $_POST['username'] = 'iansmith2';
        $_POST['firstname'] = 'Ian';
        $_POST['lastname'] = 'Smith Jr.';
        $_POST['birthdate'] = '1985-12-05';
        $_POST['gender'] = 'm';
        $_POST['address_line1'] = 'Banjar Semer';
        $_POST['city'] = 'Surabaya';
        $_POST['country'] = 'Indonesia';
        $_POST['province'] = 'Jawa Timur';
        $_POST['postal_code'] = '612345';
        $_POST['phone'] = '031-7412345';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();
        $_GET['apiver'] = '1.0';

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);

        $smith = User::with(array('userdetail', 'apikey'))->find(2);
        $this->assertSame('iansmith2', $smith->username);
        $this->assertSame('Ian', $smith->user_firstname);
        $this->assertSame('Smith Jr.', $smith->user_lastname);
        $this->assertSame('tom@localhost.org', $smith->user_email);
        $this->assertSame('4', (string)$smith->user_role_id);
        $this->assertSame('3', (string)$smith->modified_by);
        $this->assertSame('active', (string)$smith->status);
        $this->assertTrue(property_exists($response->data, 'user_id'));

        // userdetail relationship property
        $this->assertTrue(property_exists($response->data, 'userdetail'));

        // apikey relationship property
        $this->assertTrue(property_exists($response->data, 'apikey'));

        // Check the user detail on database, it should be exists also
        $details = UserDetail::where('user_id', $response->data->user_id)->first();
        $this->assertInstanceOf('UserDetail', $details);
        $this->assertSame((string)$response->data->user_id, (string)$details->user_id);

        // Check the api keys on database, it should be active by now
        $apikey = Apikey::where('user_id', $response->data->user_id)->first();
        $this->assertInstanceOf('Apikey', $apikey);
        $this->assertSame((string)$response->data->user_id, (string)$apikey->user_id);
        $this->assertSame('active', (string)$apikey->status);
    }

    public function testSavedThenRollback_POST_api_v1_user_update()
    {
        // Register an event on 'orbit.user.postupdateuser.after.save'
        // and thrown some exception so the data that has been saved
        // does not commited
        Event::listen('orbit.user.postupdateuser.after.save', function($controller, $user)
        {
            throw new Exception('This is bad bro!', 99);
        });

        // Data to be post
        $_POST['user_id'] = 2;
        $_POST['email'] = 'george@localhost.org';
        $_POST['role_id'] = 4;  // Merchant
        $_POST['status'] = 'blocked';
        $_POST['username'] = 'smith2';
        $_POST['firstname'] = 'Tom';
        $_POST['lastname'] = 'Smith';
        $_POST['birthdate'] = '1985-12-05';
        $_POST['gender'] = 'm';
        $_POST['address_line1'] = 'Banjar Semer';
        $_POST['city'] = 'Surabaya';
        $_POST['country'] = 'Indonesia';
        $_POST['province'] = 'Jawa Timur';
        $_POST['postal_code'] = '612345';
        $_POST['phone'] = '031-7412345';

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();
        $_GET['apiver'] = '1.0';

        $url = '/api/v1/user/update?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '10.10.0.99';

        $user = new stdclass();
        $user->username = $_POST['email'];
        $user->user_email = $_POST['email'];
        $user->status = 'pending';

        $data = new stdclass();
        $data->code = 99;
        $data->status = 'error';
        $data->message = 'This is bad bro!';
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('POST', $url)->getContent();
        $this->assertSame($expect, $return);

        // The data should remain the same as the old one
        $smith = User::with(array('userdetail', 'apikey'))->find(2);
        $this->assertSame('iansmith2', $smith->username);
        $this->assertSame('Ian', $smith->user_firstname);
        $this->assertSame('Smith Jr.', $smith->user_lastname);
        $this->assertSame('tom@localhost.org', $smith->user_email);
        $this->assertSame('4', (string)$smith->user_role_id);
        $this->assertSame('3', (string)$smith->modified_by);
        $this->assertSame('active', (string)$smith->status);

        $this->assertSame('active', $smith->apikey->status);
    }
}
