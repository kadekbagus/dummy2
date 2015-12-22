<?php
/**
 * Unit testing for MerchantAPIController::getSearchMerchant() method.
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

class getSearchMerchantTest extends OrbitTestCase
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

        // Insert dummy merchants
        DB::statement("INSERT INTO `{$merchant_table}`
                (`merchant_id`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `object_type`, `parent_id`, `created_at`, `updated_at`, `modified_by`, `omid`, `orid`)
                VALUES
                ('1', '2', 'alfamer@localhost.org', 'Alfa Mer', 'Super market Alfa', 'Jl. Tunjungan 01', 'Komplek B1', 'Lantai 01', '10', 'Surabaya', '62', 'Indonesia', '031-7123456', '031-712344', '2012-01-02 01:01:01', 'active', 'merchants/logo/alfamer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Murah dan Tidak Hemat', 'yes', 'merchant', NULL, '2014-11-20 06:30:01', NOW(), 1, 'M01', ''),
                ('2', '3', 'indomer@localhost.org', 'Indo Mer', 'Super market Indo', 'Jl. Tunjungan 02', 'Komplek B2', 'Lantai 02', '10', 'Surabaya', '62', 'Indonesia', '031-8123456', '031-812344', '2012-02-02 01:01:02', 'active', 'merchants/logo/indomer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Harga Kurang Pas', 'yes', 'merchant', NULL, '2014-11-20 06:30:02', NOW(), 1, 'M02', ''),
                ('3', '2', 'mitra9@localhost.org', 'Mitra 9', 'Super market Bangunan', 'Jl. Tunjungan 03', 'Komplek B3', 'Lantai 03', '10', 'Surabaya', '62', 'Indonesia', '031-6123456', '031-612344', '2012-03-02 01:01:03', 'pending', 'merchants/logo/mitra9.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Belanja Bangunan Nyaman', 'yes', 'merchant', NULL, '2014-11-20 06:30:03', NOW(), 1, 'M03', ''),
                ('4', '1', 'keefce@localhost.org', 'Ke Ef Ce', 'Chicket Fast Food', 'Jl. Tunjungan 04', 'Komplek B4', 'Lantai 04', '10', 'Surabaya', '62', 'Indonesia', '031-5123456', '031-512344', '2012-04-02 01:01:04', 'blocked', 'merchants/logo/keefce1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Bukan Jagonya Ayam!', 'yes', 'merchant', NULL, '2014-11-20 06:30:04', NOW(), 1, 'M04', ''),
                ('5', '1', 'mekdi@localhost.org', 'Mek Di', 'Burger Fast Food', 'Jl. Tunjungan 05', 'Komplek B5', 'Lantai 05', '10', 'Surabaya', '62', 'Indonesia', '031-4123456', '031-412344', '2012-05-02 01:01:05', 'inactive', 'merchants/logo/mekdi1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'I\'m not lovit', 'yes', 'merchant', NULL, '2014-11-20 06:30:05', NOW(), 1, 'M05', ''),
                ('6', '1', 'setarbak@localhost.org', 'Setar Bak', 'Tempat Minum Kopi', 'Jl. Tunjungan 06', 'Komplek B6', 'Lantai 06', '10', 'Surabaya', '62', 'Indonesia', '031-3123456', '031-312344', '2012-06-02 01:01:06', 'deleted', 'merchants/logo/setarbak1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Coffee and TV', 'yes', 'merchant', NULL, '2014-11-20 06:30:06', NOW(), 1, 'M06', ''),
                ('7', '3', 'matabulan@localhost.org', 'Mata Bulan', 'Tempat Beli Baju', 'Jl. Tunjungan 07', 'Komplek B7', 'Lantai 07', '10', 'Surabaya', '62', 'Indonesia', '031-2123456', '031-212344', '2012-07-02 01:01:06', 'inactive', 'merchants/logo/matabulan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Sale Everyday', 'yes', 'merchant', NULL, '2014-11-20 06:30:07', NOW(), 1, 'M07', ''),
                ('8', '8', 'dummy@localhost.org', 'Dummy Object', 'Doom', 'Jl. Tunjungan 08', 'Komplek B8', 'Lantai 08', '10', 'Surabaya', '62', 'Indonesia', '031-1123456', '031-112344', '2012-08-02 01:01:08', 'active', 'merchants/logo/dummy1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'dummy', NULL, '2014-11-20 06:30:08', NOW(), 1, 'M08', ''),

                ('9', '4', 'alfagubeng@localhost.org', 'Alfa Mer Gubeng Pojok', 'Alfa Mer which near Gubeng Station Surabaya', 'Jl. Gubeng 09', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'active', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 2, '2014-11-20 06:30:09', NOW(), 1, '', 'R09'),
                ('21', '7', 'alfagwalk@localhost.org', 'Alfa Mer Gwalk', 'Alfa Mer near GWalk Food Court', 'Jl. Citraland', 'Komplek G', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-21-02 01:01:09', 'active', 'merchants/logo/alfamer-gwalk.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '2014-11-21 06:30:09', NOW(), 1, '', 'R21'),
                ('22', '7', 'alfatp@localhost.org', 'Alfa Mer Tunjungan', 'Alfa Mer near Tunjungan Plaza', 'Jl. Tunjungan', 'Komplek TP', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-22-02 01:01:09', 'active', 'merchants/logo/alfamer-tunjungan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '2014-11-21 06:30:09', NOW(), 1, '', 'R22'),
                ('23', '7', 'alfapangsud@localhost.org', 'Alfa Mer Pangsud', 'Alfa Mer near Panglima Sudirman', 'Jl. Palinglima Sudirman', 'Komplek PS', 'Lantai 1', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-22-02 01:01:09', 'deleted', 'merchants/logo/alfamer-tunjungan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 1, '2014-11-21 06:30:09', NOW(), 1, '', 'R23')"
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
            'orbit.merchant.getsearchmerchant.before.auth',
            'orbit.merchant.getsearchmerchant.after.auth',
            'orbit.merchant.getsearchmerchant.before.authz',
            'orbit.merchant.getsearchmerchant.authz.notallowed',
            'orbit.merchant.getsearchmerchant.after.authz',
            'orbit.merchant.getsearchmerchant.before.validation',
            'orbit.merchant.getsearchmerchant.after.validation',
            'orbit.merchant.getsearchmerchant.access.forbidden',
            'orbit.merchant.getsearchmerchant.invalid.arguments',
            'orbit.merchant.getsearchmerchant.general.exception',
            'orbit.merchant.getsearchmerchant.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new MerchantAPIController();
        $this->assertInstanceOf('MerchantAPIController', $ctl);
    }

    public function testNoAuthData_GET_api_v1_merchant_search()
    {
        $url = '/api/v1/merchant/search';

        $data = new stdclass();
        $data->code = Status::CLIENT_ID_NOT_FOUND;
        $data->status = 'error';
        $data->message = Status::CLIENT_ID_NOT_FOUND_MSG;
        $data->data = NULL;

        $expect = json_encode($data);
        $return = $this->call('GET', $url)->getContent();
        $this->assertSame($expect, $return);
    }

    public function testInvalidSignature_GET_api_v1_merchant_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

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

    public function testSignatureExpire_GET_api_v1_merchant_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time() - 3600;  // an hour ago

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

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

    public function testAccessForbidden_GET_api_v1_merchant_search()
    {
        // Set the client API Keys
        $_GET['apikey'] = 'cde345';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'cde34567890100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        // Error message when access is forbidden
        $viewMerchantLang = Lang::get('validation.orbit.actionlist.view_merchant');
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

        // Add new permission name 'view_merchant'
        $chuck = User::find(3);
        $permission = new Permission();
        $permission->permission_name = 'view_merchant';
        $permission->save();

        $chuck->permissions()->attach($permission->permission_id, array('allowed' => 'yes'));
    }

    public function testOK_NoArgumentGiven_GET_api_v1_merchant_search()
    {
        // Data
        // No argument given at all, show all merchants
        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 2;
        Config::set('orbit.pagination.max_record', $max_record);
        Config::set('orbit.pagination.per_page', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records should be 6 and returned records 2
        $this->assertSame(6, (int)$response->data->total_records); //exclude deleted merchants.
        $this->assertSame(2, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(2, count($response->data->records));

        $expect = array(
            array(
                'merchant_id'               => '7',
                'user_id'                   => '3',
                'email'                     => 'matabulan@localhost.org',
                'name'                      => 'Mata Bulan',
                'description'               => 'Tempat Beli Baju',
                'address_line1'             => 'Jl. Tunjungan 07',
                'address_line2'             => 'Komplek B7',
                'address_line3'             => 'Lantai 07',
                'postal_code'               => NULL,
                'city_id'                   => '10',
                'city'                      => 'Surabaya',
                'country_id'                => '62',
                'country'                   => 'Indonesia',
                'phone'                     => '031-2123456',
                'fax'                       => '031-212344',
                'start_date_activity'       => '2012-07-02 01:01:06',
                'status'                    => 'inactive',
                'logo'                      => 'merchants/logo/matabulan.png',
                'currency'                  => 'IDR',
                'currency_symbol'           => 'Rp',
                'tax_code1'                 => 'tx1',
                'tax_code2'                 => 'tx2',
                'tax_code3'                 => 'tx3',
                'slogan'                    => 'Big Sale Everyday',
                'vat_included'              => 'yes',
                'contact_person_firstname'  => NULL,
                'contact_person_position'   => NULL,
                'contact_person_phone'      => NULL,
                'sector_of_activity'        => NULL,
                'object_type'               => 'merchant',
                'parent_id'                 => NULL,
                'modified_by'               => '1',
                'created_at'                => '2014-11-20 06:30:07'
            ),
            array(
                'merchant_id'               => '5',
                'user_id'                   => '1',
                'email'                     => 'mekdi@localhost.org',
                'name'                      => 'Mek Di',
                'description'               => 'Burger Fast Food',
                'address_line1'             => 'Jl. Tunjungan 05',
                'address_line2'             => 'Komplek B5',
                'address_line3'             => 'Lantai 05',
                'postal_code'               => NULL,
                'city_id'                   => '10',
                'city'                      => 'Surabaya',
                'country_id'                => '62',
                'country'                   => 'Indonesia',
                'phone'                     => '031-4123456',
                'fax'                       => '031-412344',
                'start_date_activity'       => '2012-05-02 01:01:05',
                'status'                    => 'inactive',
                'logo'                      => 'merchants/logo/mekdi1.png',
                'currency'                  => 'IDR',
                'currency_symbol'           => 'Rp',
                'tax_code1'                 => 'tx1',
                'tax_code2'                 => 'tx2',
                'tax_code3'                 => 'tx3',
                'slogan'                    => 'I\'m not lovit',
                'vat_included'              => 'yes',
                'contact_person_firstname'  => NULL,
                'contact_person_position'   => NULL,
                'contact_person_phone'      => NULL,
                'sector_of_activity'        => NULL,
                'object_type'               => 'merchant',
                'parent_id'                 => NULL,
                'modified_by'               => '1',
                'created_at'                => '2014-11-20 06:30:05'
            )
        );

        // It is ordered by registered date by default so 1) Mata Bulan 2) Mek Di
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['description'], (string)$return->description);
            $this->assertSame((string)$expect[$index]['address_line1'], (string)$return->address_line1);
            $this->assertSame((string)$expect[$index]['address_line2'], (string)$return->address_line2);
            $this->assertSame((string)$expect[$index]['address_line3'], (string)$return->address_line3);
            $this->assertSame((string)$expect[$index]['postal_code'], (string)$return->postal_code);
            $this->assertSame((string)$expect[$index]['city_id'], (string)$return->city_id);
            $this->assertSame((string)$expect[$index]['city'], (string)$return->city);
            $this->assertSame((string)$expect[$index]['country_id'], (string)$return->country_id);
            $this->assertSame((string)$expect[$index]['country'], (string)$return->country);
            $this->assertSame((string)$expect[$index]['phone'], (string)$return->phone);
            $this->assertSame((string)$expect[$index]['fax'], (string)$return->fax);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['status'], (string)$return->status);
            $this->assertSame((string)$expect[$index]['logo'], (string)$return->logo);
            $this->assertSame((string)$expect[$index]['currency'], (string)$return->currency);
            $this->assertSame((string)$expect[$index]['currency_symbol'], (string)$return->currency_symbol);
            $this->assertSame((string)$expect[$index]['tax_code1'], (string)$return->tax_code1);
            $this->assertSame((string)$expect[$index]['tax_code2'], (string)$return->tax_code2);
            $this->assertSame((string)$expect[$index]['tax_code3'], (string)$return->tax_code3);
            $this->assertSame((string)$expect[$index]['slogan'], (string)$return->slogan);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['contact_person_firstname'], (string)$return->contact_person_firstname);
            $this->assertSame((string)$expect[$index]['contact_person_position'], (string)$return->contact_person_position);
            $this->assertSame((string)$expect[$index]['contact_person_phone'], (string)$return->contact_person_phone);
            $this->assertSame((string)$expect[$index]['contact_person_position'], (string)$return->contact_person_position);
            $this->assertSame((string)$expect[$index]['sector_of_activity'], (string)$return->sector_of_activity);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
            $this->assertSame((string)$expect[$index]['modified_by'], (string)$return->modified_by);
            $this->assertSame((string)$expect[$index]['created_at'], (string)$return->created_at);
        }
    }

    public function testOK_NoArgumentGiven_MaxRecordMoreThenRecords_GET_api_v1_merchant_search()
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
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total and returned records should be 6, exclude deleted merchants.
        $this->assertSame(6, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'merchant_id'         => '7',
                'user_id'             => '3',
                'email'               => 'matabulan@localhost.org',
                'name'                => 'Mata Bulan',
                'start_date_activity' => '2012-07-02 01:01:06',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '5',
                'user_id'             => '1',
                'email'               => 'mekdi@localhost.org',
                'name'                => 'Mek Di',
                'start_date_activity' => '2012-05-02 01:01:05',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '4',
                'user_id'             => '1',
                'email'               => 'keefce@localhost.org',
                'name'                => 'Ke Ef Ce',
                'start_date_activity' => '2012-04-02 01:01:04',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '3',
                'user_id'             => '2',
                'email'               => 'mitra9@localhost.org',
                'name'                => 'Mitra 9',
                'start_date_activity' => '2012-03-02 01:01:03',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '2',
                'user_id'             => '3',
                'email'               => 'indomer@localhost.org',
                'name'                => 'Indo Mer',
                'start_date_activity' => '2012-02-02 01:01:02',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '1',
                'user_id'             => '2',
                'email'               => 'alfamer@localhost.org',
                'name'                => 'Alfa Mer',
                'start_date_activity' => '2012-01-02 01:01:01',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            )
        );

        // It is ordered by registered date by default so
        // 7-Mata Bulan, 5-Mek Di, 4-Ke Ef Ce, 3-Mitra 9, 2-Indo Mer, 1-Alfa Mer
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
        }
    }

    public function testInvalidSortBy_GET_api_v1_merchant_search()
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

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('validation.orbit.empty.merchant_sortby');
        $this->assertSame(Status::INVALID_ARGUMENT, (int)$response->code);
        $this->assertSame('error', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue(is_null($response->data->records));
    }

    public function testOK_OrderByRegisteredDateDESC_GET_api_v1_merchant_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records and returned should be 6, exclude deleted merchants.
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'merchant_id'         => '7',
                'user_id'             => '3',
                'email'               => 'matabulan@localhost.org',
                'name'                => 'Mata Bulan',
                'start_date_activity' => '2012-07-02 01:01:06',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '5',
                'user_id'             => '1',
                'email'               => 'mekdi@localhost.org',
                'name'                => 'Mek Di',
                'start_date_activity' => '2012-05-02 01:01:05',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '4',
                'user_id'             => '1',
                'email'               => 'keefce@localhost.org',
                'name'                => 'Ke Ef Ce',
                'start_date_activity' => '2012-04-02 01:01:04',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '3',
                'user_id'             => '2',
                'email'               => 'mitra9@localhost.org',
                'name'                => 'Mitra 9',
                'start_date_activity' => '2012-03-02 01:01:03',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '2',
                'user_id'             => '3',
                'email'               => 'indomer@localhost.org',
                'name'                => 'Indo Mer',
                'start_date_activity' => '2012-02-02 01:01:02',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '1',
                'user_id'             => '2',
                'email'               => 'alfamer@localhost.org',
                'name'                => 'Alfa Mer',
                'start_date_activity' => '2012-01-02 01:01:01',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            )
        );

        // It is ordered by registered date by default so
        // 7-Mata Bulan, 5-Mek Di, 4-Ke Ef Ce, 3-Mitra 9, 2-Indo Mer, 1-Alfa Mer
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
        }
    }

    public function testOK_OrderByRegisteredDateASC_GET_api_v1_merchant_search()
    {
        // Data
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 6;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame(Status::OK_MSG, (string)$response->message);

        // Number of total records and returned should be 6, exclude deleted merchants.
        $this->assertSame($max_record, (int)$response->data->total_records);
        $this->assertSame(6, (int)$response->data->returned_records);

        // The records attribute should be array
        $this->assertTrue(is_array($response->data->records));
        $this->assertSame(6, count($response->data->records));

        $expect = array(
            array(
                'merchant_id'         => '1',
                'user_id'             => '2',
                'email'               => 'alfamer@localhost.org',
                'name'                => 'Alfa Mer',
                'start_date_activity' => '2012-01-02 01:01:01',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '2',
                'user_id'             => '3',
                'email'               => 'indomer@localhost.org',
                'name'                => 'Indo Mer',
                'start_date_activity' => '2012-02-02 01:01:02',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '3',
                'user_id'             => '2',
                'email'               => 'mitra9@localhost.org',
                'name'                => 'Mitra 9',
                'start_date_activity' => '2012-03-02 01:01:03',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '4',
                'user_id'             => '1',
                'email'               => 'keefce@localhost.org',
                'name'                => 'Ke Ef Ce',
                'start_date_activity' => '2012-04-02 01:01:04',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '5',
                'user_id'             => '1',
                'email'               => 'mekdi@localhost.org',
                'name'                => 'Mek Di',
                'start_date_activity' => '2012-05-02 01:01:05',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '7',
                'user_id'             => '3',
                'email'               => 'matabulan@localhost.org',
                'name'                => 'Mata Bulan',
                'start_date_activity' => '2012-07-02 01:01:06',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            )
        );

        // It is ordered by registered date ASC, so
        // 1-Alfa Mer, 2-Indo Mer, 3-Mitra 9, 4-Ke Ef Ce, 5-Mek Di, 7-Mata Bulan
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
        }
    }

    public function testOK_SearchName_GET_api_v1_merchant_search()
    {
        // Data
        // Should be ordered by registered date desc if not specified
        $_GET['name'] = array('Indo Mer', 'Mek Di');
        $_GET['sortby'] = 'merchant_name';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();
        $_GET['sortby'] = 'registered_date';
        $_GET['sortmode'] = 'desc';

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
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
                'merchant_id'         => '5',
                'user_id'             => '1',
                'email'               => 'mekdi@localhost.org',
                'name'                => 'Mek Di',
                'start_date_activity' => '2012-05-02 01:01:05',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '2',
                'user_id'             => '3',
                'email'               => 'indomer@localhost.org',
                'name'                => 'Indo Mer',
                'start_date_activity' => '2012-02-02 01:01:02',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            )
        );

        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
        }
    }

    public function testOK_SearchName_OrderByNameASC_GET_api_v1_merchant_search()
    {
        // set merchant name, sortby, and sort mode.
        $_GET['name'] = array('Indo Mer', 'Mek Di');
        $_GET['sortby'] = 'merchant_name';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
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
                'merchant_id'         => '2',
                'user_id'             => '3',
                'email'               => 'indomer@localhost.org',
                'name'                => 'Indo Mer',
                'start_date_activity' => '2012-02-02 01:01:02',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            ),
            array(
                'merchant_id'         => '5',
                'user_id'             => '1',
                'email'               => 'mekdi@localhost.org',
                'name'                => 'Mek Di',
                'start_date_activity' => '2012-05-02 01:01:05',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            )
        );

        // checking data.
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
        }
    }

    public function testOK_SearchById_WithRetailersRelationship_OrderByOmidASC_GET_api_v1_merchant_search()
    {
        // set merchant name, sortby, and sort mode.
        $_GET['merchant_id'] = array(1, 2);
        $_GET['with'] = array('retailers');
        $_GET['with_count'] = array('retailers');
        $_GET['sortby'] = 'merchant_omid';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
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
                'merchant_id'         => '1',
                'user_id'             => '2',
                'email'               => 'alfamer@localhost.org',
                'name'                => 'Alfa Mer',
                'start_date_activity' => '2012-01-02 01:01:01',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL,
                'retailers_number'    => 2,
            ),
            array(
                'merchant_id'         => '2',
                'user_id'             => '3',
                'email'               => 'indomer@localhost.org',
                'name'                => 'Indo Mer',
                'start_date_activity' => '2012-02-02 01:01:02',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL,
                'retailers_number'    => 1,
            )
        );

        // checking data.
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
            $this->assertSame((string)$expect[$index]['retailers_number'], (string)$return->retailers_number->count);
            $this->assertTrue( is_array($return->retailers) && (count($return->retailers) == $expect[$index]['retailers_number']));
        }
    }

    public function testOK_SearchNameLike_OrderByNameASC_GET_api_v1_merchant_search()
    {
        // Data
        $_GET['name_like'] = 'tra'; // from merchant name 'Mitra 9'.
        $_GET['sortby'] = 'merchant_name';
        $_GET['sortmode'] = 'asc';

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
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
                'merchant_id'         => '3',
                'user_id'             => '2',
                'email'               => 'mitra9@localhost.org',
                'name'                => 'Mitra 9',
                'start_date_activity' => '2012-03-02 01:01:03',
                'vat_included'        => 'yes',
                'object_type'         => 'merchant',
                'parent_id'           => NULL
            )
        );

        // checking data.
        foreach ($response->data->records as $index=>$return)
        {
            $this->assertSame((string)$expect[$index]['merchant_id'], (string)$return->merchant_id);
            $this->assertSame((string)$expect[$index]['user_id'], (string)$return->user_id);
            $this->assertSame((string)$expect[$index]['email'], (string)$return->email);
            $this->assertSame((string)$expect[$index]['name'], (string)$return->name);
            $this->assertSame((string)$expect[$index]['start_date_activity'], (string)$return->start_date_activity);
            $this->assertSame((string)$expect[$index]['vat_included'], (string)$return->vat_included);
            $this->assertSame((string)$expect[$index]['object_type'], (string)$return->object_type);
            $this->assertSame((string)$expect[$index]['parent_id'], (string)$return->parent_id);
        }
    }

    public function testOK_SearchName_NotFound_GET_api_v1_merchant_search()
    {
        // Data
        $_GET['name'] = array('not-exists');

        // It should read from config named 'orbit.pagination.max_record'
        // It should fallback to whathever you like when the config is not exists
        $max_record = 10;
        Config::set('orbit.pagination.max_record', $max_record);

        // Set the client API Keys
        $_GET['apikey'] = 'def123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/merchant/search?' . http_build_query($_GET);

        $secretKey = 'def12345678901';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        $message = Lang::get('statuses.orbit.nodata.merchant');

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame('success', (string)$response->status);
        $this->assertSame($message, (string)$response->message);
        $this->assertSame(0, (int)$response->data->total_records);
        $this->assertSame(0, (int)$response->data->returned_records);
        $this->assertTrue( is_null($response->data->records) );
    }


}
