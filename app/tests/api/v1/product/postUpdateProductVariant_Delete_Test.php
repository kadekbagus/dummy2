<?php
/**
 * Unit testing for ProductAPIController::postNewProduct() and postUpdateProduct()
 * method which lead to duplicate UPC.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\OrbitShopAPI;

class postUpdateProductVariant_Delete_Test extends OrbitTestCase
{
    protected static $merchants = [];
    protected static $retailers = [];
    protected static $products = [];
    protected static $variants = [];
    protected static $attributes = [];
    protected static $attributeValues = [];
    protected static $productVariants = [];

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
                (1, 'abc123', 'abc12345678910', '1', 'active', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'active', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'active', '2014-10-19 20:02:05', '2014-10-19 20:03:05')"
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
                ('5', 'add_product', 'Add Product', 'product', 'Product', '0', '2', '1', NOW(), NOW())"
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

        // Insert Dummy Merchants
        static::$merchants = [
            [
                'merchant_id'   => 1,
                'name'          => 'Matahari',
                'status'        => 'active',
                'object_type'   => 'merchant',
                'user_id'       => 3,
            ],
            [
                'merchant_id'   => 2,
                'name'          => 'Ace Hardware',
                'status'        => 'active',
                'object_type'   => 'merchant',
                'user_id'       => 4
            ],
        ];
        foreach (static::$merchants as $merchant) {
            DB::table('merchants')->insert($merchant);
        }

        // Insert Dummy Retailers
        static::$retailers = [
            [
                'merchant_id'   => 3,
                'name'          => 'Matahari Mall Denpasar',
                'status'        => 'active',
                'object_type'   => 'retailer',
                'user_id'       => 3,
                'parent_id'     => 1,
            ],
            [
                'merchant_id'   => 4,
                'name'          => 'Ace Hardware Sunset Road',
                'status'        => 'active',
                'object_type'   => 'retailer',
                'user_id'       => 4,
                'parent_id'     => 2,
            ],
            [
                'merchant_id'   => 5,
                'name'          => 'Matahari Sunset Road',
                'status'        => 'active',
                'object_type'   => 'retailer',
                'user_id'       => 4,
                'parent_id'     => 1,
            ],
        ];
        foreach (static::$retailers as $retailer) {
            DB::table('merchants')->insert($retailer);
        }

        // Insert dummy Product Attributes
        static::$attributes = [
            [
                'product_attribute_id'      => 1,
                'product_attribute_name'    => 'Size',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 2,
                'product_attribute_name'    => 'Color',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 3,
                'product_attribute_name'    => 'Material',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 4,
                'product_attribute_name'    => 'Class',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 5,
                'product_attribute_name'    => 'Origin',
                'merchant_id'               => 1,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 6,
                'product_attribute_name'    => 'Size',
                'merchant_id'               => 2,
                'status'                    => 'active'
            ],
            [
                'product_attribute_id'      => 7,
                'product_attribute_name'    => 'Material',
                'merchant_id'               => 2,
                'status'                    => 'active'
            ],
        ];
        foreach (static::$attributes as $attr) {
            DB::table('product_attributes')->insert($attr);
        }

        // Insert Dummy Product Attribute Value
        $attributeValues = [
            [
                'product_attribute_value_id'    => 1,
                'product_attribute_id'          => 1,
                'value'                         => '27',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 2,
                'product_attribute_id'          => 1,
                'value'                         => '28',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 3,
                'product_attribute_id'          => 1,
                'value'                         => '29',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 4,
                'product_attribute_id'          => 1,
                'value'                         => '30',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 5,
                'product_attribute_id'          => 2,
                'value'                         => 'White',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 6,
                'product_attribute_id'          => 2,
                'value'                         => 'Gray',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 7,
                'product_attribute_id'          => 2,
                'value'                         => 'Black',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 8,
                'product_attribute_id'          => 3,
                'value'                         => 'Cotton',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 9,
                'product_attribute_id'          => 3,
                'value'                         => 'Spandex',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 10,
                'product_attribute_id'          => 6,
                'value'                         => 'Small',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 11,
                'product_attribute_id'          => 6,
                'value'                         => 'Medium',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 12,
                'product_attribute_id'          => 6,
                'value'                         => 'Big',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 13,
                'product_attribute_id'          => 7,
                'value'                         => 'Iron',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 14,
                'product_attribute_id'          => 7,
                'value'                         => 'Steel',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 15,
                'product_attribute_id'          => 4,
                'value'                         => 'KW 1',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 16,
                'product_attribute_id'          => 4,
                'value'                         => 'KW 2',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 17,
                'product_attribute_id'          => 5,
                'value'                         => 'USA',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 18,
                'product_attribute_id'          => 5,
                'value'                         => 'Bandung',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 19,
                'product_attribute_id'          => 1,
                'value'                         => '14',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 20,
                'product_attribute_id'          => 1,
                'value'                         => '15',
                'status'                        => 'active'
            ],
            [
                'product_attribute_value_id'    => 21,
                'product_attribute_id'          => 1,
                'value'                         => '16',
                'status'                        => 'active'
            ],
        ];
        foreach ($attributeValues as $value) {
            DB::table('product_attribute_values')->insert($value);
        }

        // Insert dummy products
        static::$products = [
            [
                'product_id'    => 1,
                'product_name'  => 'Kemeja Mahal',
                'product_code'  => 'SKU-001',
                'upc_code'      => 'UPC-001',
                'price'         => 500000,
                'status'        => 'active',
                'merchant_id'   => 1,
            ],
            [
                'product_id'    => 2,
                'product_name'  => 'Celana Murah',
                'product_code'  => 'SKU-002',
                'upc_code'      => 'UPC-002',
                'price'         => 30000,
                'status'        => 'active',
                'merchant_id'   => 1
            ],
            [
                'product_id'    => 3,
                'product_name'  => 'Kunci Obeng',
                'product_code'  => 'SKU-001',
                'upc_code'      => 'UPC-001',
                'price'         => 125000,
                'status'        => 'active',
                'merchant_id'   => 2
            ],
        ];
        foreach (static::$products as $product) {
            DB::table('products')->insert($product);
        }
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
        $attributes_table = static::$dbPrefix . 'product_attributes';
        $attribute_values_table = static::$dbPrefix . 'product_attribute_values';
        $variants_table = static::$dbPrefix . 'product_variants';
        $products_table = static::$dbPrefix . 'products';
        $transactions_table = static::$dbPrefix . 'transactions';
        $transaction_details_table = static::$dbPrefix . 'transaction_details';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`;
                        TRUNCATE `{$merchant_table}`;
                        TRUNCATE `{$attributes_table}`;
                        TRUNCATE `{$attribute_values_table}`;
                        TRUNCATE `{$variants_table}`;
                        TRUNCATE `{$products_table}`;
                        TRUNCATE `{$transactions_table}`;
                        TRUNCATE `{$transaction_details_table}`;
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

        // Clear every event dispatcher so we get no queue event on each
        // test
        $events = array(
            'orbit.product.postnewproduct.before.auth',
            'orbit.product.postnewproduct.after.auth',
            'orbit.product.postnewproduct.before.authz',
            'orbit.product.postnewproduct.authz.notallowed',
            'orbit.product.postnewproduct.after.authz',
            'orbit.product.postnewproduct.before.validation',
            'orbit.product.postnewproduct.after.validation',
            'orbit.product.postnewproduct.before.save',
            'orbit.product.postnewproduct.after.save',
            'orbit.product.postnewproduct.after.commit',
            'orbit.product.postnewproduct.access.forbidden',
            'orbit.product.postnewproduct.invalid.arguments',
            'orbit.product.postnewproduct.general.exception',
            'orbit.product.postnewproduct.before.render'
        );
        foreach ($events as $event) {
            Event::forget($event);
        }
    }

    public function testObjectInstance()
    {
        $ctl = new ProductAPIController();
        $this->assertInstanceOf('ProductAPIController', $ctl);
    }

    /**
     * This test would produce the following variants:
     *
     * Kemeja Mahal	Size 14 (19, 1)	Color White (5,2)	Material Cotton (8,3)	Origin USA (17, 5)	Class KW 1 (15,4)
     * Kemeja Mahal	Size 15 (20, 1)	Color White (5,2)	Material Cotton (8,3)	Origin USA (17, 5)	Class KW 1 (15,4)
     * Kemeja Mahal	Size 16 (21, 1)	Color Black (7,2)	Material Cotton (8,3)	Origin USA (17, 5)	Class KW 2 (16,4)
     */
    public function testSaveProductUpdate_DeleteVariant_KemejaMahal_Size14_ColorWhite_MaterialCotton_OriginUSA_ClassKW()
    {
        // Object of first "Kemeja Mahal"
        $kemejaMahal1 = new stdClass();
        $kemejaMahal1->upc = NULL;  // Follows the parent
        $kemejaMahal1->sku = NULL;  // Follows the parent
        $kemejaMahal1->price = NULL;  // Follows the parent

        // It containts array of product_attribute_value_id
        $kemejaMahal1->attribute_values = [19, 5, 8, 17, 15];

        // Object of second "Kemeja Mahal"
        $kemejaMahal2 = new stdClass();
        $kemejaMahal2->upc = 'UPC-001-MAHAL';  // Has own UPC
        $kemejaMahal2->sku = 'SKU-001-MAHAL';  // Has own SKU
        $kemejaMahal2->price = 999000;  // Has own price

        // It containts array of product_attribute_value_id
        $kemejaMahal2->attribute_values = [20, 5, 8, 17, 15];

        // Object of third "Kemeja Mahal"
        $kemejaMahal3 = new stdClass();
        $kemejaMahal3->upc = 'UPC-001-MAHAL3';  // Has own UPC
        $kemejaMahal3->sku = 'SKU-001-MAHAL3';  // Has own SKU
        $kemejaMahal3->price = NULL;    // Follows the parent

        // It containts array of product_attribute_value_id
        $kemejaMahal3->attribute_values = [21, 7, 8, 17, 16];

        // POST data
        $_POST['product_id'] = 1;
        $_POST['product_variants'] = json_encode([$kemejaMahal1, $kemejaMahal2, $kemejaMahal3]);

        // Set the client API Keys
        $_GET['apikey'] = 'abc123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/product/update?' . http_build_query($_GET);

        $secretKey = 'abc12345678910';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);

        $product = Product::with('variantsNoDefault')->find(1);

        // The values of attribute_id1,2,3,4,5 for Product ID 1 should be
        // 1, 2, 3, 5, 4
        $this->assertSame('1', (string)$product->attribute_id1);
        $this->assertSame('2', (string)$product->attribute_id2);
        $this->assertSame('3', (string)$product->attribute_id3);
        $this->assertSame('5', (string)$product->attribute_id4);
        $this->assertSame('4', (string)$product->attribute_id5);

        // Check data for first Object
        $variant1 = $product->variantsNoDefault[0];

        $this->assertSame('SKU-001', $variant1->sku);
        $this->assertSame('UPC-001', $variant1->upc);
        $this->assertSame('500000.00', (string)$variant1->price);

        // Check data for second Object
        $variant2 = $product->variantsNoDefault[1];

        $this->assertSame('SKU-001-MAHAL', $variant2->sku);
        $this->assertSame('UPC-001-MAHAL', $variant2->upc);
        $this->assertSame('999000.00', (string)$variant2->price);

        // Check data for third Object
        $variant3 = $product->variantsNoDefault[2];

        $this->assertSame('SKU-001-MAHAL3', $variant3->sku);
        $this->assertSame('UPC-001-MAHAL3', $variant3->upc);
        $this->assertSame('500000.00', (string)$variant3->price);

        // Now Delete the variant 1
        $_GET = [];
        $_POST = [];

        // POST data
        $_POST['product_id'] = 1;
        $_POST['product_variants_delete'] = [$variant1->product_variant_id];

        // Set the client API Keys
        $_GET['apikey'] = 'abc123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/product/update?' . http_build_query($_GET);

        $secretKey = 'abc12345678910';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);

        $product = Product::with('variantsNoDefault')->find(1);
        $this->assertSame(2, count($product->variantsNoDefault));

        // Now Delete the variant 2
        $_GET = [];
        $_POST = [];

        // POST data
        $_POST['product_id'] = 1;
        $_POST['product_variants_delete'] = [$variant2->product_variant_id];

        // Set the client API Keys
        $_GET['apikey'] = 'abc123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/product/update?' . http_build_query($_GET);

        $secretKey = 'abc12345678910';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);

        $product = Product::with('variantsNoDefault')->find(1);
        $this->assertSame(1, count($product->variantsNoDefault));

        // Now Delete the variant 3
        $_GET = [];
        $_POST = [];

        // POST data
        $_POST['product_id'] = 1;
        $_POST['product_variants_delete'] = [$variant3->product_variant_id];

        // Set the client API Keys
        $_GET['apikey'] = 'abc123';
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/product/update?' . http_build_query($_GET);

        $secretKey = 'abc12345678910';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $return = $this->call('POST', $url)->getContent();

        $response = json_decode($return);
        $this->assertSame(0, (int)$response->code);
        $this->assertSame('success', $response->status);

        $product = Product::with('variantsNoDefault')->find(1);
        $this->assertSame(0, count($product->variantsNoDefault));

        // The values of attribute_id1,2,3,4,5 for Product ID 1 should be
        // 1, 2, 3, 5, 4
        $this->assertSame('', (string)$product->attribute_id1);
        $this->assertSame('', (string)$product->attribute_id2);
        $this->assertSame('', (string)$product->attribute_id3);
        $this->assertSame('', (string)$product->attribute_id4);
        $this->assertSame('', (string)$product->attribute_id5);
    }
}
