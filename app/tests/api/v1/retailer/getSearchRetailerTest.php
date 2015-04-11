<?php
/**
 * Unit testing for RetailerAPIController::getSearchRetailer() method.
 *
 * @author Tian <tian@dominopos.com>
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
 *
 * If the consumer request using some `with[]=retailers`, then we should return
 * it like this one:
 * {
 *      "code": CODE,
 *      "status": STATUS,
 *      "message": MESSAGE,
 *      "data":
 *      {
 *          "total_records": NUMBER_OF_TOTAL_RECORDS,
 *          "returned_records": NUMBER_OF_RETURNED_RECORDS,
 *          "records": [
 *              {
 *                  "merchant_id": 1,
 *                  ....some_attr.....
 *                  "retailers": [
 *                          {
 *                              "merchant_id": 10,
 *                              "parent_id": 1,
 *                              ....some_attr.....
 *                          },
 *                          {
 *                              "merchant_id": 11,
 *                              "parent_id": 1,
 *                              ....some_attr.....
 *                          }
 *                  ]
 *                  "retailers_count": 2
 *              }
 *          ]
 *      }
 * }
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class getSearchRetailerTest extends OrbitTestCase
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
        $merchant_table = static::$dbPrefix . 'merchants';

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
                ('4', 'Retailer', '1', NOW(), NOW()),
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

        // Insert dummy merchants and retailers
        $now = time();
        $aday = 24 * 3600;
        $anhour = 3600;
        $yesterday = $now - $aday;
        $aweekago = $now - ($aday * 7);
        $created_at = array(
                'R21' => date('Y-m-d H:i:s', $now),                     // 1
                'R23' => date('Y-m-d H:i:s', $now),                     // 1
                'R22' => date('Y-m-d H:i:s', $now - ($anhour * 2)),     // 2
                'R09' => date('Y-m-d H:i:s', $yesterday),               // 3
                'R25' => date('Y-m-d H:i:s', $now - ($aday * 3)),       // 4
                'R24' => date('Y-m-d H:i:s', $aweekago)                 // 5
        );
        DB::statement("INSERT INTO `{$merchant_table}`
                (`merchant_id`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `object_type`, `parent_id`, `created_at`, `updated_at`, `modified_by`, `omid`, `orid`)
                VALUES
                ('1', '2', 'alfamer@localhost.org', 'Alfa Mer', 'Super market Alfa', 'Jl. Tunjungan 01', 'Komplek B1', 'Lantai 01', '10', 'Surabaya', '62', 'Indonesia', '031-7123456', '031-712344', '2012-01-02 01:01:01', 'active', 'merchants/logo/alfamer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Murah dan Tidak Hemat', 'yes', 'merchant', NULL, '2014-11-20 06:30:01', NOW(), 1, 'M01', ''),
                ('2', '3', 'indomer@localhost.org', 'Indo Mer', 'Super market Indo', 'Jl. Tunjungan 02', 'Komplek B2', 'Lantai 02', '10', 'Surabaya', '62', 'Indonesia', '031-8123456', '031-812344', '2012-02-02 01:01:02', 'active', 'merchants/logo/indomer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Harga Kurang Pas', 'yes', 'merchant', NULL, '2014-11-20 06:30:02', NOW(), 1, 'M02', ''),

                ('9', '4', 'alfagubeng@localhost.org', 'Alfa Mer Gubeng Pojok', 'Alfa Mer which near Gubeng Station Surabaya', 'Jl. Gubeng 09', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'active', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 2, '{$created_at['R09']}', NOW(), 1, '', 'R09'),
                ('21', '7', 'alfagwalk@localhost.org', 'Alfa Mer Gwalk', 'Alfa Mer near GWalk Food Court', 'Jl. Citraland', 'Komplek G', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-21-02 01:01:09', 'active', 'merchants/logo/alfamer-gwalk.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '{$created_at['R21']}', NOW(), 1, '', 'R21'),
                ('22', '7', 'alfatp@localhost.org', 'Alfa Mer Tunjungan', 'Alfa Mer near Tunjungan Plaza', 'Jl. Tunjungan', 'Komplek TP', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-22-02 01:01:09', 'active', 'merchants/logo/alfamer-tunjungan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '{$created_at['R22']}', NOW(), 1, '', 'R22'),
                ('23', '7', 'alfapangsud@localhost.org', 'Alfa Mer Pangsud', 'Alfa Mer near Panglima Sudirman', 'Jl. Palinglima Sudirman', 'Komplek PS', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-22-02 01:01:09', 'deleted', 'merchants/logo/alfamer-tunjungan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '{$created_at['R23']}', NOW(), 1, '', 'R23'),
                ('24', '7', 'alfamayjend@localhost.org', 'Alfa Mer Mayjend', 'Alfa Mer near Mayjend Sungkono', 'Jl. Mayjend. Sungkono', 'Komplek MJ', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-22-02 01:01:09', 'active', 'merchants/logo/alfamer-mayjend.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '{$created_at['R24']}', NOW(), 1, '', 'R24'),
                ('25', '7', 'alfaayani@localhost.org', 'Alfa Mer A. Yani', 'Alfa Mer near Ahmad Yani', 'Jl. Ahmad Yani', 'Komplek AY', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-22-02 01:01:09', 'active', 'merchants/logo/alfamer-ayani.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '{$created_at['R25']}', NOW(), 1, '', 'R25')"
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
        $merchant_table = static::$dbPrefix . 'merchants';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`;
                        TRUNCATE `{$merchant_table}`");
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
            'orbit.retailer.getsearchretailer.before.auth',
            'orbit.retailer.getsearchretailer.after.auth',
            'orbit.retailer.getsearchretailer.before.authz',
            'orbit.retailer.getsearchretailer.authz.notallowed',
            'orbit.retailer.getsearchretailer.after.authz',
            'orbit.retailer.getsearchretailer.before.validation',
            'orbit.retailer.getsearchretailer.after.validation',
            'orbit.retailer.getsearchretailer.access.forbidden',
            'orbit.retailer.getsearchretailer.invalid.arguments',
            'orbit.retailer.getsearchretailer.general.exception',
            'orbit.retailer.getsearchretailer.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new RetailerAPIController();
        $this->assertInstanceOf('RetailerAPIController', $ctl);
    }

    public function testNoAuthData_GET_api_v1_retailer_search()
    {
        $url = '/api/v1/retailer/search';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_GET_api_v1_retailer_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
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

    public function testSignatureExpire_GET_api_v1_retailer_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
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

    public function testAccessForbidden_GET_api_v1_retailer_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $viewMerchantLang = Lang::get('validation.orbit.actionlist.view_retailer');
        $message = Lang::get('validation.orbit.access.forbidden',
                             array('action' => $viewMerchantLang));

        $data = new stdclass();
        $data->code = Status::ACCESS_DENIED;
        $data->status = 'error';
        $data->message = $message;
        $data->data = null;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);

        // Add new permission name 'view_retailer'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'view_retailer';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));
    }

    public function testOK_NoArgumentGiven_GET_api_v1_retailer_search()
    {
        // Data
        // No argument given at all, show all users
        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);
        Config::set('orbit.pagination.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        // It is ordered by registered date by default so
        // 'R21'
        // 'R23' --> deleted
        // 'R22'

        $expect = array(
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_NoArgumentGiven_MaxRecordMoreThenRecords_GET_api_v1_retailer_search()
    {
        // Data
        // No argument given at all, show all users
        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);
        Config::set('orbit.pagination.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(5, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(5, count($response->data->records));

        // It is ordered by registered date by default so
        // 'R21'
        // 'R23' --> deleted
        // 'R22'
        // 'R09'
        // 'R25'
        // 'R24'

        $expect = array(
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testInvalidSortBy_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'dummy';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('validation.orbit.empty.retailer_sortby');
        $this->assertSame(Status::INVALID_ARGUMENT, (int)$response->code);
        $this->assertSame('error', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue(is_null($response->data->records));
    }

    public function testOK_OrderByRegisteredDateDESC_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(5, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(5, count($response->data->records));

        // It is ordered by registered date by default so
        // 'R21'
        // 'R23' --> deleted
        // 'R22'
        // 'R09'
        // 'R25'
        // 'R24'

        $expect = array(
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByRegisteredDateASC_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(5, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(5, count($response->data->records));

        // It is ordered by registered date by default so
        // 'R21'
        // 'R23' --> deleted
        // 'R22'
        // 'R09'
        // 'R25'
        // 'R24'

        // Registered by date asc
        // R24
        // R25
        // R09
        // R22
        // R21

        $expect = array(
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByNameASC_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'retailer_name';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(5, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(5, count($response->data->records));

        // Registered by name asc
        // R25
        // R09
        // R21
        // R24
        // R22

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByEmailASC_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'retailer_email';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(5, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(5, count($response->data->records));

        // Registered by name asc
        // R25
        // R09
        // R21
        // R24
        // R22

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByOridDESC_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'orid';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(5, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(5, count($response->data->records));

        // Registered by orid desc
        // R25
        // R24
        // R22
        // R21
        // R09

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
            array(
                'merchant_id'         => '21',
                'user_id'             => '7',
                'email'               => 'alfagwalk@localhost.org',
                'name'                => 'Alfa Mer Gwalk',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R21',
            ),
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByOridDESC_TakeOne_SkipFour_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'orid';
        $_GET['sortmode'] = 'desc';
        $_GET['take'] = 1;
        $_GET['skip'] = 4;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(1, count($response->data->records));

        // Registered by orid asc limit 4,1
        // R09

        $expect = array(
            array(
                'merchant_id'         => '9',
                'user_id'             => '4',
                'email'               => 'alfagubeng@localhost.org',
                'name'                => 'Alfa Mer Gubeng Pojok',
                'object_type'         => 'retailer',
                'parent_id'           => '2',
                'orid'                => 'R09',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByOridDESC_TakeThree_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'orid';
        $_GET['sortmode'] = 'desc';
        $_GET['take'] = 3;

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(3, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(3, count($response->data->records));

        // Registered by orid desc
        // R25
        // R24
        // R22

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);
        }
    }

    public function testOK_OrderByOridDESC_TakeThree_WithMerchantRelationShip_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'orid';
        $_GET['sortmode'] = 'desc';
        $_GET['take'] = 3;
        $_GET['with'] = array('merchant');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(3, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(3, count($response->data->records));

        // Registered by orid desc
        // R25
        // R24
        // R22

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
                'omid'                => 'M01',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
                'omid'                => 'M01',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
                'omid'                => 'M01',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);

            $this->assertTrue( property_exists($return, 'merchant') );
            $this->assertSame((string)$expect[$index]['omid'], $return->merchant->omid);
        }
    }

    public function testOK_OrderByOridDESC_TakeThree_WithMerchantAndUserRelationShip_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'orid';
        $_GET['sortmode'] = 'desc';
        $_GET['take'] = 3;
        $_GET['with'] = array('merchant', 'user');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(3, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(3, count($response->data->records));

        // Registered by orid desc
        // R25
        // R24
        // R22

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
                'omid'                => 'M01',
                'username'            => 'catwoman'
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
                'omid'                => 'M01',
                'username'            => 'catwoman'
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
                'omid'                => 'M01',
                'username'            => 'catwoman'
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);

            $this->assertTrue( property_exists($return, 'merchant') );
            $this->assertSame((string)$expect[$index]['omid'], $return->merchant->omid);

            $this->assertTrue( property_exists($return, 'user') );
            $this->assertSame((string)$expect[$index]['username'], $return->user->username);
        }
    }


    public function testOK_OrderByOridDESC_TakeThree_WithCountMerchantRelationShip_GET_api_v1_retailer_search()
    {
        // Data
        $_GET['sortby'] = 'orid';
        $_GET['sortmode'] = 'desc';
        $_GET['take'] = 3;
        $_GET['with'] = array('merchant');
        $_GET['with_count'] = array('merchant');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/retailer/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 5, exclude deleted merchants.
        $this->assertSame(5, (int)$response->data->total_records);
        $this->assertSame(3, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(3, count($response->data->records));

        // Registered by orid desc
        // R25
        // R24
        // R22

        $expect = array(
            array(
                'merchant_id'         => '25',
                'user_id'             => '7',
                'email'               => 'alfaayani@localhost.org',
                'name'                => 'Alfa Mer A. Yani',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R25',
                'omid'                => 'M01',
            ),
            array(
                'merchant_id'         => '24',
                'user_id'             => '7',
                'email'               => 'alfamayjend@localhost.org',
                'name'                => 'Alfa Mer Mayjend',
                'object_type'         => 'retailer',
                'parent_id'           => '7',
                'orid'                => 'R24',
                'omid'                => 'M01',
            ),
            array(
                'merchant_id'         => '22',
                'user_id'             => '7',
                'email'               => 'alfatp@localhost.org',
                'name'                => 'Alfa Mer Tunjungan',
                'object_type'         => 'retailer',
                'parent_id'           => '1',
                'orid'                => 'R22',
                'omid'                => 'M01',
            ),
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['orid'], (string)$return->orid);

            $this->assertTrue( property_exists($return, 'merchant') );
            $this->assertSame((string)$expect[$index]['omid'], $return->merchant->omid);
            $this->assertSame('1', (string)$return->merchant_number->count);
        }
    }
}
