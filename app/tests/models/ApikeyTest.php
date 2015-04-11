<?php
/**
 * Unit test for model ApiKey
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @copyright DominoPOS Ltd.
 */
class ApikeyTest extends OrbitTestCase
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
                (1, 'abc123', 'abc12345678910', '1', 'active', '2014-10-19 20:02:01', '2014-10-19 20:03:01'),
                (2, 'bcd234', 'bcd23456789010', '2', 'active', '2014-10-19 20:02:02', '2014-10-19 20:03:02'),
                (3, 'cde345', 'cde34567890100', '3', 'active', '2014-10-19 20:02:03', '2014-10-19 20:03:03'),
                (4, 'def123', 'def12345678901', '1', 'deleted', '2014-10-19 20:02:04', '2014-10-19 20:03:04'),
                (5, 'efg212', 'efg09876543212', '4', 'blocked', '2014-10-19 20:02:05', '2014-10-19 20:03:05')"
        );

        // Insert dummy data on users
        DB::statement("INSERT INTO `{$user_table}`
                (`user_id`, `username`, `user_password`, `user_email`, `user_firstname`, `user_lastname`, `user_last_login`, `user_ip`, `user_role_id`, `status`, `modified_by`, `created_at`, `updated_at`)
                VALUES
                ('1', 'john', '878758439857435', 'john@localhost.org', 'John', 'Doe', NOW(), '10.10.0.11', '1', 'active', '1', NOW(), NOW()),
                ('2', 'smith', 'fdfdsf34325435435', 'smith@localhost.org', 'John', 'Smith', NOW(), '10.10.0.12', '1', 'active', '1', NOW(), NOW())"
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
        DB::unprepared("TRUNCATE `{$apikey_table}`; TRUNCATE `{$user_table}`");
    }

    public function testObjectInstance()
    {
        $expect = 'Apikey';
        $return = new Apikey();
        $this->assertInstanceOf($expect, $return);
    }

    public function testNumberOfRecords()
    {
        $expect = 5;
        $return = Apikey::count();
        $this->assertSame($expect, $return);
    }

    public function testRecordNumber1()
    {
        $apikey = Apikey::find(2);

        // id
        $expect = '2';
        $return = (string)$apikey->apikey_id;
        $this->assertSame($expect, $return);

        // api key
        $expect = 'bcd234';
        $return = $apikey->api_key;
        $this->assertSame($expect, $return);

        // secret key
        $expect = 'bcd23456789010';
        $return = $apikey->api_secret_key;
        $this->assertSame($expect, $return);

        // user id
        $expect = '2';
        $return = (string)$apikey->user_id;
        $this->assertSame($expect, $return);

        // status
        $expect = 'active';
        $return = $apikey->status;
        $this->assertSame($expect, $return);

        // created at
        $expect = '2014-10-19 20:02:02';
        $return = (string)$apikey->created_at;
        $this->assertSame($expect, $return);

        // updated at
        $expect = '2014-10-19 20:03:02';
        $return = (string)$apikey->updated_at;
        $this->assertSame($expect, $return);
    }

    public function testRelationshipExists_user()
    {
        $apikey = new Apikey();
        $return = method_exists($apikey, 'user');
        $this->assertTrue($return);
    }

    public function testRelationshipReturn_belongsTo_user()
    {
        $apikey = new Apikey();
        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        $return = $apikey->user();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationship_userObject_user()
    {
        $apikey = Apikey::find(2);
        $expect = 'User';
        $user = $apikey->user()->first();
        $this->assertInstanceOf($expect, $user);

        // Email should be 'smith@localhost.org'
        $expect = 'smith@localhost.org';
        $this->assertSame($expect, $user->user_email);
    }

    public function testScopeActive()
    {
        $expect = 3;
        $return = Apikey::active()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeBlocked()
    {
        $expect = 1;
        $return = Apikey::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeDeleted()
    {
        $expect = 1;
        $return = Apikey::withDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeMakeRecord2_BecomeBlocked()
    {
        // Let's change the status to blocked
        Apikey::find(2)->makeBlocked()->save();

        $expect = 'blocked';
        $return = Apikey::find(2)->status;
        $this->assertSame($expect, $return);

        // Number of active records should be decreased by one
        $expect = 2;
        $return = Apikey::active()->count();
        $this->assertSame($expect, $return);

        // Number of blocked records should be increased by one
        $expect = 2;
        $return = Apikey::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testScopeMakeRecord2_BecomeDeleted()
    {
        // Let's change the status to blocked
        Apikey::find(2)->delete();

        $expect = 'deleted';
        $return = Apikey::find(2)->status;
        $this->assertSame($expect, $return);

        // Number of deleted records should be increased by one
        $expect = 2;
        $return = Apikey::withDeleted()->count();
        $this->assertSame($expect, $return);

        $expect = 5;
        $return = Apikey::count();
        $this->assertSame($expect, $return);
    }

    public function testScopeExcludeDeleted()
    {
        $expect = 3;
        $return = Apikey::excludeDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testDestroyRecordNumber1()
    {
        Apikey::destroy(1);

        // Should be 3, since destroy internally calls delete()
        $expect = 3;
        $return = Apikey::withDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testForceDeleteRecordNumber2()
    {
        Apikey::find(2)->delete(TRUE);

        // Should be 2, since record number 2 has been wiped from database
        $expect = 2;
        $return = Apikey::withDeleted()->count();
        $this->assertSame($expect, $return);

        // Total record should be 4
        $expect = 4;
        $return = Apikey::count();
        $this->assertSame($expect, $return);
    }

    public function testGenerateApiKey()
    {
        $john = User::find(1);

        // Generate 10 randoms keys for user john
        $keys = array();
        for ($i=0; $i<10; $i++) {
            $keys[] = Apikey::genApiKey($john);
        }

        // All the keys should not same each other
        $unique = array_unique($keys);

        // Number of unique should 10
        $this->assertSame(10, count($unique));

        // The length of the string should be 40
        foreach ($keys as $key) {
            $this->assertSame(40, strlen($key));
        }
    }

    public function testGenerateAPISecretKey()
    {
        $john = User::find(1);

        // Generate 10 randoms secret keys for user john
        $keys = array();
        for ($i=0; $i<10; $i++) {
            $keys[] = Apikey::genSecretKey($john);
        }

        // All the keys should not same each other
        $unique = array_unique($keys);

        // Number of unique should 10
        $this->assertSame(10, count($unique));

        // The length of the string should be 64
        foreach ($keys as $key) {
            $this->assertSame(64, strlen($key));
        }
    }
}
