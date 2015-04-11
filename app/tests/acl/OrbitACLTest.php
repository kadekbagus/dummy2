<?php
/**
 * Unit test for OrbitACL library
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @copyright DominoPOS Ltd.
 */
use DominoPOS\OrbitACL\ACL;

class OrbitACLTest extends OrbitTestCase
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
        $user_table = static::$dbPrefix . 'users';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';

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
                ('5', '3', '4', 'yes', NOW(), NOW()),
                ('6', '3', '5', 'no', NOW(), NOW())"
        );

        // Insert dummy data on custom_permission
        // view_user set to 'yes' for user 'ironman'
        DB::statement("INSERT INTO `{$custom_permission_table}`
                (`custom_permission_id`, `user_id`, `permission_id`, `allowed`, `created_at`, `updated_at`)
                VALUES
                ('1', '3', '2', 'yes', NOW(), NOW())"
        );
    }

    /**
     * Executed only once at the end of the test.
     */
    public static function tearDownAfterClass()
    {
        // do nothing
    }

    /**
     * Clear all data that has been inserted.
     */
    public static function truncateData()
    {
        $user_table = static::$dbPrefix . 'users';
        $user_detail_table = static::$dbPrefix . 'user_details';
        $role_table = static::$dbPrefix . 'roles';
        $permission_table = static::$dbPrefix . 'permissions';
        $permission_role_table = static::$dbPrefix . 'permission_role';
        $custom_permission_table = static::$dbPrefix . 'custom_permission';
        DB::unprepared("TRUNCATE `{$user_table}`;
                        TRUNCATE `{$role_table}`;
                        TRUNCATE `{$custom_permission_table}`;
                        TRUNCATE `{$permission_role_table}`;
                        TRUNCATE `{$permission_table}`");
    }

    public function testObjectInstance()
    {
        $expect = 'DominoPOS\OrbitACL\ACL';
        $return = new ACL();
        $this->assertInstanceOf($expect, $return);
    }

    public function testObjectInstanceFromStatic()
    {
        $expect = 'DominoPOS\OrbitACL\ACL';
        $return = ACL::create();
        $this->assertInstanceOf($expect, $return);
    }

    public function testUserJohnDoe_SuperAdmin()
    {
        $user = User::find(1);
        $acl = new ACL($user);

        // All the five permission should yes
        $this->assertTrue($acl->isAllowed('login'));
        $this->assertTrue($acl->isAllowed('view_user'));
        $this->assertTrue($acl->isAllowed('create_user'));
        $this->assertTrue($acl->isAllowed('view_product'));
        $this->assertTrue($acl->isAllowed('add_product'));
    }

    public function testUserChuckNorris_Customer()
    {
        $user = User::find(3);
        $acl = new ACL($user);

        // All the five permission should yes
        $this->assertTrue($acl->isAllowed('login'));
        $this->assertTrue($acl->isAllowed('view_user'));
        $this->assertFalse($acl->isAllowed('create_user'));
        $this->assertTrue($acl->isAllowed('view_product'));
        $this->assertFalse($acl->isAllowed('add_product'));
    }

    public function testUserChuckNorris_Customer_CustomAddProduct_Yes()
    {
        $user = User::find(3);

        // Add custom permission 'add_product' => yes to user chuck
        $permission = $user->permissions()->attach(5, array('allowed' => 'yes'));

        $acl = new ACL($user);

        $this->assertTrue($acl->isAllowed('login'));
        $this->assertTrue($acl->isAllowed('view_user'));
        $this->assertFalse($acl->isAllowed('create_user'));
        $this->assertTrue($acl->isAllowed('view_product'));
        $this->assertTrue($acl->isAllowed('add_product'));
    }

    public function testUserJohnDoe_SuperAdmin_newDeleteProductGlobalPermission_Allowed()
    {
        $user = User::find(3);

        // Add permission 'delete_product' which does not exists yet on both
        // Role Permission and Custom Permission
        $permission = new Permission();
        $permission->permission_name = 'delete_product';
        $permission->save();

        $acl = new ACL($user);

        // All the five permission should yes
        $this->assertTrue($acl->isAllowed('login'));
        $this->assertTrue($acl->isAllowed('view_user'));
        $this->assertFalse($acl->isAllowed('create_user'));
        $this->assertTrue($acl->isAllowed('view_product'));
        $this->assertTrue($acl->isAllowed('add_product'));
        $this->assertFalse($acl->isAllowed('delete_product'));

        $permission->delete();
    }

    public function testUserChuckNorris_Customer_newDeleteProductGlobalPermission_Denied()
    {
        $user = User::find(3);

        // Add permission 'delete_product' which does not exists yet on both
        // Role Permission and Custom Permission
        $permission = new Permission();
        $permission->permission_name = 'delete_product';
        $permission->save();

        $acl = new ACL($user);

        $this->assertTrue($acl->isAllowed('login'));
        $this->assertTrue($acl->isAllowed('view_user'));
        $this->assertFalse($acl->isAllowed('create_user'));
        $this->assertTrue($acl->isAllowed('view_product'));
        $this->assertTrue($acl->isAllowed('add_product'));
        $this->assertFalse($acl->isAllowed('delete_product'));

        $permission->delete();
    }

    public function testUserChuckNorris_Customer_newDeleteProductGlobalPermission_Allowed()
    {
        $user = User::find(3);

        // Add permission 'delete_product' which does not exists yet on both
        // Role Permission and Custom Permission
        // Set default value to 'yes'
        $permission = new Permission();
        $permission->permission_name = 'delete_product';
        $permission->permission_default_value = 'yes';
        $permission->save();

        $acl = new ACL($user);

        $this->assertTrue($acl->isAllowed('login'));
        $this->assertTrue($acl->isAllowed('view_user'));
        $this->assertFalse($acl->isAllowed('create_user'));
        $this->assertTrue($acl->isAllowed('view_product'));
        $this->assertTrue($acl->isAllowed('add_product'));
        $this->assertTrue($acl->isAllowed('delete_product'));

        $permission->delete();
    }

    public function testUserOptimusPrime_CustomerBlocked_allDenied()
    {
        $user = User::find(4);
        $acl = new ACL($user);

        // All the five permission should false
        $this->assertFalse($acl->isAllowed('login'));
        $this->assertFalse($acl->isAllowed('view_user'));
        $this->assertFalse($acl->isAllowed('create_user'));
        $this->assertFalse($acl->isAllowed('view_product'));
        $this->assertFalse($acl->isAllowed('add_product'));
    }

    public function testJohnDoe_SuperAdmin_unknownPermission()
    {
        $user = User::find(1);
        $acl = new ACL($user);
        $this->assertTrue($acl->isAllowed('some_dummy'));
    }

    public function testUserChuckNorris_Customer_unknownPermission()
    {
        $user = User::find(3);
        $acl = new ACL($user);
        $this->assertFalse($acl->isAllowed('some_dummy'));
    }

    /**
     * @expectedException   DominoPOS\OrbitACL\Exception\ACLForbiddenException
     * @expectedExceptionMessage You do not have permission to access the specified resource
     * @expectedExceptionCode 13
     */
    public function testException_ACLForbiddenEx_NoArguments()
    {
        $user = User::find(3);
        $acl = new ACL($user);
        if (! $acl->isAllowed('some_dummy')) {
            $acl->throwAccessForbidden();
        }
    }

    /**
     * @expectedException   DominoPOS\OrbitACL\Exception\ACLForbiddenException
     * @expectedExceptionMessage You do not have access to "Some Dummy" resource
     * @expectedExceptionCode 13
     */
    public function testException_ACLForbiddenEx_withMessageArgument()
    {
        $user = User::find(3);
        $acl = new ACL($user);
        if (! $acl->isAllowed('some_dummy')) {
            $acl->throwAccessForbidden('You do not have access to "Some Dummy" resource');
        }
    }
}
