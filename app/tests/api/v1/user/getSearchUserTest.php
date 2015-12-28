<?php
/**
 * Unit testing for UserAPIController::getSearchUser() method.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @specs:
 * The default return value for this API are:
 * {
 *      "code": CODE,
 *      "status": STATUS,
 *      "message": MESSAGE,
 *      "data":
 *      {
 *          "total_records": NUMBER_OF_TOTAL_RECORDS,
 *          "returned_records": NUMBER_OF_RETURNED_RECORDS,
 *          "records": []
 *      }
 * }
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class getSearchUserTest extends OrbitTestCase
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
            'droopy'    => Hash::make('droopy'),
            'catwoman'  => Hash::make('catwoman'),
        );

        // Insert dummy data on users
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '{$password['john']}', 'john@localhost.org', 'John', 'Doe', '2014-10-20 06:20:01', '10.10.0.11', '1', 'active', '1',                  '2014-10-05 06:30:01', '2014-10-20 06:31:01'),
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'active', '1',             '2014-10-25 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1',           '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1',   '2014-10-01 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1',    '2014-10-20 06:30:05', '2014-10-20 06:31:05'),
                ('6', 'droopy', '{$password['droopy']}', 'droopy@localhost.org', 'Droopy', 'Dog', '2014-10-20 06:20:06', '10.10.0.14', '3', 'pending', '1',         '2014-10-22 06:30:06', '2014-10-05 06:31:06'),
                ('7', 'catwoman', '{$password['catwoman']}', 'catwoman@localhost.org', 'Cat', 'Woman', '2014-10-20 06:20:07', '10.10.0.17', '4', 'active', '1',     '2014-10-20 06:30:07', '2014-10-20 06:31:07')"
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
                    ('6', '6', '5', '2014-10-21 06:20:06', 'Orchard Road', 'Orchard6', 'Near Airplane Statue6', '60259', '6', 'Singapore6', '20', 'Singapore6', '61', 'Singapore', 'SGD', 'SG', '1987-02-05', 'm', 'single',  '081234567896', 'images/customer/06.png', '15', '8600000.00', '1500000.00', '2014-11-21 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05'),
                    ('7', '7', '10', '2014-10-21 06:20:06', 'Jl. Pahlawan7', 'Gubeng7', 'Sebelah Tugu Pahlawan7', '60259', '7', 'Surabaya7', '17', 'Jawa Timur', '62', 'Indonesia', 'IDR', 'Rp', '1980-10-05', 'f', 'single',  '081234567897', 'images/customer/07.png', '20', '8700000.00', '1500000.00', '2014-08-21 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05')"
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
              $_SERVER['REQUEST_URI']
        );

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

    public function testNoAuthData_GET_api_v1_user_search()
    {
        $url = '/api/v1/user/search';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_GET_api_v1_user_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

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
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testSignatureExpire_GET_api_v1_user_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/user/search?' . http_build_query($_GET);

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

    public function testAccessForbidden_GET_api_v1_user_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $viewUserLang = Lang::get('validation.orbit.actionlist.view_user');
        $message = Lang::get('validation.orbit.access.forbidden',
                             array('action' => $viewUserLang));

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = $message;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);

        // Add new permission name 'view_user'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'view_user';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));
    }

    public function testOK_NoArgumentGiven_GET_api_v1_user_search()
    {
        // Data
        // No argument given at all, show all users
        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);
        Config::set('orbit.pagination.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 6 and returned records 2
        $this->assertSame(6, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active',
                'addr'              => 'Jl. Raya Semer2'
            ),
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending',
                'addr'              => 'Orchard Road'
            )
        );

        // It is ordered by registered date by default so 1) smith 2) droopy
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);

            // User Details
            $this->assertSame($expect[$index]['id'], (string)$return->userdetail->user_id);
            $this->assertSame($expect[$index]['addr'], (string)$return->userdetail->address_line1);
        }
    }

    public function testOK_NoArgumentGiven_MaxRecordMoreThenRecords_GET_api_v1_user_search()
    {
        // Data
        // No argument given at all, show all users
        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);
        Config::set('orbit.pagination.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 6, exlcude pink panther
        $this->assertSame(6, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            )
        );

        // It is ordered by registered date by default so
        // 2-smith, 6-droopy, 7-catwoan, 3-chuck, 1-john, 4-optimus
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testInvalidSortBy_GET_api_v1_user_search()
    {
        // Data
        $_GET['sortby'] = 'dummy';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('validation.orbit.empty.user_sortby');
        $this->assertSame(Status::INVALID_ARGUMENT, (int)$response->code);
        $this->assertSame('error', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue(is_null($response->data->records));
    }

    public function testOK_OrderByRegisteredDateDESC_GET_api_v1_user_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records and returned should be 6, exlcude pink panther
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            )
        );

        // It is ordered by registered date by default so
        // 2-smith, 6-droopy, 7-catwoan, 3-chuck, 1-john, 4-optimus
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_OrderByRegisteredDateASC_GET_api_v1_user_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records and returned should be 6, exlcude pink panther
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            )
        );

        // It is ordered by registered date ASC, so
        // 4-optimus, 1-john, 3-chuck, 7-catwoman, 6-droopy, 2-smith
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchUsername_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['username'] = array('chuck', 'john');
        $_GET['sortby'] = 'username';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchUsername_OrderByUsernameASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['username'] = array('chuck', 'john');
        $_GET['sortby'] = 'username';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            )
        );

        // It is ordered by registered date by default so 1) smith 2) droopy
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchUsernameLike_OrderByUsernameASC_GET_api_v1_user_search()
    {
        // Data
        $_GET['username_like'] = 'smi';
        $_GET['sortby'] = 'username';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 1;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            )
        );

        // It is ordered by registered date by default so 1) smith 2) droopy
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchUsername_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['username'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchFirstName_OrderByFirstNameASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['firstname'] = array('Cat', 'Chuck');
        $_GET['sortby'] = 'firstname';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchFirstName_OrderByFirstNameDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['firstname'] = array('Cat', 'Chuck');
        $_GET['sortby'] = 'firstname';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchFirstName_Like_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['firstname_like'] = 'Droo';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 1;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchFirstName_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['firstname'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchLastName_OrderByLastNameASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['lastname'] = array('Woman', 'Norris');
        $_GET['sortby'] = 'lastname';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchLastName_OrderByLastNameDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['lastname'] = array('Woman', 'Norris');
        $_GET['sortby'] = 'lastname';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchLastNameLike_OrderByLastNameDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['lastname_like'] = 'Do';
        $_GET['sortby'] = 'lastname';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchLastName_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['lastname'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchEmail_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['email'] = array('catwoman@localhost.org', 'chuck@localhost.org');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchEmail_OrderByEmailDESC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['email'] = array('catwoman@localhost.org', 'chuck@localhost.org');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchEmailLike_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['email_like'] = '@localhost.org';
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 6 and returned records 6
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame($expect[$index]['id'], (string)$return->user_id);
            $this->assertSame($expect[$index]['username'], $return->username);
            $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
            $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
            $this->assertSame($expect[$index]['email'], $return->user_email);
            $this->assertSame($expect[$index]['status'], $return->status);
        }
    }

    public function testOK_SearchEmail_NotFound_GET_api_v1_user_search()
    {
        // Data
        $_GET['email'] = array('not-exists@localhost.org');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchStatusActive_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('active');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 4 and returned records 4
        $this->assertSame(4, (int)$response->data->total_records);
        $this->assertSame(4, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(4, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        // catwoman, chuck, john, smith

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(4, $matches);
    }

    public function testOK_SearchStatusBlocked_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('blocked');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '4',
                'username'          => 'optimus',
                'firstname'         => 'Optimus',
                'lastname'          => 'Prime',
                'email'             => 'optimus@localhost.org',
                'status'            => 'blocked'
            ),
        );

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }

    public function testOK_SearchStatusPending_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('pending');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '6',
                'username'          => 'droopy',
                'firstname'         => 'Droopy',
                'lastname'          => 'Dog',
                'email'             => 'droopy@localhost.org',
                'status'            => 'pending'
            ),
        );

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }

    public function testOK_SearchStatusDeleted_NoDataShouldReturned_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('deleted');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.user');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }

    public function testOK_SearchStatusActive_OrderByEmailASC_Take2_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('active');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 3;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 4;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 4 and returned records 3
        $this->assertSame(4, (int)$response->data->total_records);
        $this->assertSame(3, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(3, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '3',
                'username'          => 'chuck',
                'firstname'         => 'Chuck',
                'lastname'          => 'Norris',
                'email'             => 'chuck@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
        );

        // catwoman, chuck, john

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(3, $matches);
    }

    public function testOK_SearchStatusActive_OrderByEmailASC_Take2_Skip2_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['status'] = array('active');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 2;
        $_GET['skip'] = 2;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 4;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 4 and returned records 2
        $this->assertSame(4, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        // john, smith

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(2, $matches);
    }

    public function testOK_SearchUserId_OrderByEmailASC_Take2_Skip0_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['user_id'] = array(1, 2);
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 2;
        $_GET['skip'] = 0;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 4;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 2 and returned records 2
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'id'                => '1',
                'username'          => 'john',
                'firstname'         => 'John',
                'lastname'          => 'Doe',
                'email'             => 'john@localhost.org',
                'status'            => 'active'
            ),
            array(
                'id'                => '2',
                'username'          => 'smith',
                'firstname'         => 'John',
                'lastname'          => 'Smith',
                'email'             => 'smith@localhost.org',
                'status'            => 'active'
            ),
        );

        // catwoman, chuck, john, smith

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(2, $matches);
    }

    public function testOK_SearchRoleId_OrderByEmailASC_GET_api_v1_user_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['role_id'] = array('4');
        $_GET['sortby'] = 'email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/user/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 1 and returned records 1
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        $expect = array(
            array(
                'id'                => '7',
                'username'          => 'catwoman',
                'firstname'         => 'Cat',
                'lastname'          => 'Woman',
                'email'             => 'catwoman@localhost.org',
                'status'            => 'active'
            ),
        );

        $matches = 0;
        foreach ($response->data->records as $index=>$return)
        {
            if ((string)$return->user_id === $expect[$index]['id'])
            {
                $this->assertSame($expect[$index]['id'], (string)$return->user_id);
                $this->assertSame($expect[$index]['username'], $return->username);
                $this->assertSame($expect[$index]['firstname'], $return->user_firstname);
                $this->assertSame($expect[$index]['lastname'], $return->user_lastname);
                $this->assertSame($expect[$index]['email'], $return->user_email);
                $this->assertSame($expect[$index]['status'], $return->status);
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }
}
