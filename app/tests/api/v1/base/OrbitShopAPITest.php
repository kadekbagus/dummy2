<?php
/**
 * Unit test for OrbitShop API version 1.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\OrbitShopAPI;

class OrbitShopAPITest extends OrbitTestCase
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

        // Insert dummy data on apikeys
        DB::statement("INSERT INTO `{$apikey_table}`
                (`apikey_id`, `api_key`, `api_secret_key`, `user_id`, `status`, `created_at`, `updated_at`)
                VALUES
                (1, 'abc123', 'abc12345678910', '1', 'deleted', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'active', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'blocked', '2014-10-19 20:02:05', '2014-10-19 20:03:05'),
                (6, 'xyz987', 'xyz98765442233', '4', 'active', '2014-10-19 20:02:06', '2014-10-19 20:03:06'),
                (7, 'pqr654', 'pqr98765456781', '5', 'active', '2014-10-19 20:02:07', '2014-10-19 20:03:07'),
                (8, 'klm543', 'klm54322113456', '10', 'active', '2014-10-19 20:02:08', '2014-10-19 20:03:08')"
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
                ('2', 'smith', '{$password['smith']}', 'smith@localhost.org', 'John', 'Smith', '2014-10-20 06:20:02', '10.10.0.12', '3', 'pending', '1', '2014-10-20 06:30:02', '2014-10-20 06:31:02'),
                ('3', 'chuck', '{$password['chuck']}', 'chuck@localhost.org', 'Chuck', 'Norris', '2014-10-20 06:20:03', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:03', '2014-10-20 06:31:03'),
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'blocked', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'deleted', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05')"
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
        $apikey_table = static::$dbPrefix . 'apikeys';
        $user_table = static::$dbPrefix . 'users';
        DB::unprepared("TRUNCATE `{$apikey_table}`;
                        TRUNCATE `{$user_table}`;");
    }

    /**
     * @expectedException   DominoPOS\OrbitAPI\v10\Exception\APIException
     * @expectedExceptionMessage The client ID does not exists
     */
    public function testExceptionKey_abc123_notFound_statusDeleted()
    {
        OrbitShopAPI::clearLookupCache('abc123');
        $api = new OrbitShopAPI('abc123');
    }

    /**
     * @expectedException   DominoPOS\OrbitAPI\v10\Exception\APIException
     * @expectedExceptionMessage You do not have permission to access the specified resource
     */
    public function testExceptionKey_efg212_found_statusBlocked()
    {
        OrbitShopAPI::clearLookupCache('efg212');
        $api = new OrbitShopAPI('efg212');
    }

    /**
     * @expectedException   DominoPOS\OrbitAPI\v10\Exception\APIException
     * @expectedExceptionMessage You do not have permission to access the specified resource
     */
    public function testExceptionKey_xyz987_active_userStatusBlocked()
    {
        OrbitShopAPI::clearLookupCache('xyz987');
        $api = new OrbitShopAPI('xyz987');
    }

    /**
     * @expectedException   DominoPOS\OrbitAPI\v10\Exception\APIException
     * @expectedExceptionMessage You do not have permission to access the specified resource
     */
    public function testExceptionKey_bcd234_active_userStatusPending()
    {
        OrbitShopAPI::clearLookupCache('bcd234');
        $api = new OrbitShopAPI('bcd234');
    }

    /**
     * @expectedException   DominoPOS\OrbitAPI\v10\Exception\APIException
     * @expectedExceptionMessage You do not have permission to access the specified resource
     */
    public function testExceptionKey_bcd234_active_userNotFound()
    {
        OrbitShopAPI::clearLookupCache('klm543');
        $api = new OrbitShopAPI('klm543');
    }

    public function testKey_cde345_userChuckNorris()
    {
        OrbitShopAPI::clearLookupCache('cde345');
        $api = new OrbitShopAPI('cde345');

        $apiKeyID = 'cde345';
        $this->assertSame($apiKeyID, $api->clientID);

        $secretKey = 'cde34567890100';
        $this->assertSame($secretKey, $api->clientSecretKey);

        $userID = 3;
        $this->assertSame($userID, (int)$api->userId);

        $this->assertSame('chuck', $api->user->username);
        $this->assertSame('chuck@localhost.org', $api->user->user_email);
    }
}
