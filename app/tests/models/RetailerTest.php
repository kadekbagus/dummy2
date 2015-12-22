<?php
/**
 * Unit test for model Retailer
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @copyright DominoPOS Ltd.
 */
class RetailerTest extends OrbitTestCase
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
        $merchant_table = static::$dbPrefix . 'merchants';

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
                ('4', 'optimus', '{$password['optimus']}', 'optimus@localhost.org', 'Optimus', 'Prime', '2014-10-20 06:20:04', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:04', '2014-10-20 06:31:04'),
                ('5', 'panther', '{$password['panther']}', 'panther@localhost.org', 'Pink', 'Panther', '2014-10-20 06:20:05', '10.10.0.13', '3', 'active', '1', '2014-10-20 06:30:05', '2014-10-20 06:31:05')"
        );

        // Insert dummy merchants
        DB::statement("INSERT INTO `{$merchant_table}`
                    (`merchant_id`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `object_type`, `parent_id`, `created_at`, `updated_at`, `modified_by`)
                    VALUES
                    ('1', '2', 'alfamer@localhost.org', 'Alfa Mer AAA', 'Super market Alfa', 'Jl. Tunjungan 01', 'Komplek B1', 'Lantai 01', '10', 'Surabaya', '62', 'Indonesia', '031-7123456', '031-712344', '2012-01-02 01:01:01', 'active', 'merchants/logo/alfamer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Murah dan Tidak Hemat', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('2', '3', 'indomer@localhost.org', 'Indo Mer BBB', 'Super market Indo', 'Jl. Tunjungan 02', 'Komplek B2', 'Lantai 02', '10', 'Surabaya', '62', 'Indonesia', '031-8123456', '031-812344', '2012-02-02 01:01:02', 'active', 'merchants/logo/indomer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Harga Kurang Pas', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('3', '2', 'mitra9@localhost.org', 'Mitra 9 CCC', 'Super market Bangunan', 'Jl. Tunjungan 03', 'Komplek B3', 'Lantai 03', '10', 'Surabaya', '62', 'Indonesia', '031-6123456', '031-612344', '2012-03-02 01:01:03', 'pending', 'merchants/logo/mitra9.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Belanja Bangunan Nyaman', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('4', '1', 'keefce@localhost.org', 'Ke Ef Ce DDD', 'Chicket Fast Food', 'Jl. Tunjungan 04', 'Komplek B4', 'Lantai 04', '10', 'Surabaya', '62', 'Indonesia', '031-5123456', '031-512344', '2012-04-02 01:01:04', 'blocked', 'merchants/logo/keefce1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Bukan Jagonya Ayam!', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('5', '1', 'mekdi@localhost.org', 'Mek Di EEE', 'Burger Fast Food', 'Jl. Tunjungan 05', 'Komplek B5', 'Lantai 05', '10', 'Surabaya', '62', 'Indonesia', '031-4123456', '031-412344', '2012-05-02 01:01:05', 'inactive', 'merchants/logo/mekdi1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'I\'m not lovit', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('6', '1', 'setarbak@localhost.org', 'Setar Bak FFF', 'Tempat Minum Kopi', 'Jl. Tunjungan 06', 'Komplek B6', 'Lantai 06', '10', 'Surabaya', '62', 'Indonesia', '031-3123456', '031-312344', '2012-06-02 01:01:06', 'deleted', 'merchants/logo/setarbak1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Coffee and TV', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('7', '3', 'matabulan@localhost.org', 'Mata Bulan GGG', 'Tempat Beli Baju', 'Jl. Tunjungan 07', 'Komplek B7', 'Lantai 07', '10', 'Surabaya', '62', 'Indonesia', '031-2123456', '031-212344', '2012-07-02 01:01:06', 'inactive', 'merchants/logo/matabulan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Sale Everyday', 'yes', 'retailer', 9, NOW(), NOW(), 1),
                    ('8', '8', 'dummy@localhost.org', 'Dummy Object HHH', 'Doom', 'Jl. Tunjungan 08', 'Komplek B8', 'Lantai 08', '10', 'Surabaya', '62', 'Indonesia', '031-1123456', '031-112344', '2012-08-02 01:01:08', 'active', 'merchants/logo/dummy1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'dummy', 9, NOW(), NOW(), 1),
                    ('9', '4', 'alfagubeng@localhost.org', 'Alfa Mer', 'Alfa Mer Pusat', 'Jl. Gubeng 09', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'active', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'merchant', 2, NOW(), NOW(), 1)"
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
        $merchant_table = static::$dbPrefix . 'merchants';
        DB::unprepared("TRUNCATE `{$user_table}`;
                        TRUNCATE `{$merchant_table}`");
    }

    public function testObjectInstance()
    {
        $expect = 'Retailer';
        $return = new Retailer();
        $this->assertInstanceOf($expect, $return);
    }

    public function testNumberOfRecords()
    {
        $expect = 7;
        $return = Retailer::count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsPlusUnknown()
    {
        $expect = 9;
        $return = Retailer::withUnknown()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsActive()
    {
        $expect = 2;
        $return = Retailer::active()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsBlocked()
    {
        $expect = 1;
        $return = Retailer::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsPending()
    {
        $expect = 1;
        $return = Retailer::pending()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsDeleted()
    {
        $expect = 1;
        $return = Retailer::withDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsInactive()
    {
        $expect = 2;
        $return = Retailer::inactive()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsExcludeDeleted()
    {
        $expect = 6;
        $return = Retailer::excludeDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testRecordNumber2()
    {
        $retailer = Retailer::with(array('user', 'merchant', 'merchant.user'))->active()->find(2);
        $this->assertSame('indomer@localhost.org', $retailer->email);
        $this->assertSame('Indo Mer BBB', $retailer->name);
        $this->assertSame('Super market Indo', $retailer->description);
        $this->assertSame('Jl. Tunjungan 02', $retailer->address_line1);
        $this->assertSame('Komplek B2', $retailer->address_line2);
        $this->assertSame('Lantai 02', $retailer->address_line3);
        $this->assertSame('10', (string)$retailer->city_id);
        $this->assertSame('Surabaya', $retailer->city);
        $this->assertSame('62', (string)$retailer->country_id);
        $this->assertSame('Indonesia', $retailer->country);
        $this->assertSame('031-8123456', $retailer->phone);
        $this->assertSame('031-812344', $retailer->fax);
        $this->assertSame('2012-02-02 01:01:02', $retailer->start_date_activity);
        $this->assertSame('active', $retailer->status);
        $this->assertSame('merchants/logo/indomer1.png', $retailer->logo);
        $this->assertSame('IDR', $retailer->currency);
        $this->assertSame('Rp', $retailer->currency_symbol);
        $this->assertSame('tx1', $retailer->tax_code1);
        $this->assertSame('tx2', $retailer->tax_code2);
        $this->assertSame('tx3', $retailer->tax_code3);
        $this->assertSame('Harga Kurang Pas', $retailer->slogan);
        $this->assertSame('yes', $retailer->vat_included);
        $this->assertSame('retailer', $retailer->object_type);
        $this->assertSame('1', (string)$retailer->modified_by);

        $this->assertSame('chuck@localhost.org', $retailer->user->user_email);
        $this->assertSame('chuck', $retailer->user->username);

        $alfagubeng = $retailer->merchant;
        $this->assertSame('alfagubeng@localhost.org', $alfagubeng->email);
        $this->assertSame('Alfa Mer', $alfagubeng->name);
        $this->assertSame('optimus@localhost.org', $alfagubeng->user->user_email);
    }

    public function testInsertRecord()
    {
        $retailer = new Retailer();
        $retailer->object_type = 'foo'; // should be forced to be retailer
        $retailer->user_id = 2;
        $retailer->email = 'texaschicken@localhost.org';
        $retailer->name = 'Texas Chicken';
        $retailer->description = 'Mantab';
        $retailer->modified_by = 1;
        $retailer->save();

        $retailer2 = Retailer::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertSame((string)$retailer->user_id, (string)$retailer2->user_id);
        $this->assertSame($retailer->email, $retailer2->email);
        $this->assertSame($retailer->name, $retailer2->name);
        $this->assertSame($retailer->description, $retailer2->description);
        $this->assertSame('retailer', $retailer2->object_type);
        $this->assertSame('1', (string)$retailer->modified_by);
    }

    public function testUpdateRecord()
    {
        $retailer = Retailer::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $retailer->object_type = 'bar'; // should be forced to be merchant
        $retailer->user_id = 2;
        $retailer->email = 'texaschicken@localhost.org';
        $retailer->name = 'Texas Chicken';
        $retailer->description = 'Mantab';
        $retailer->modified_by = 1;
        $retailer->save();

        $retailer2 = Retailer::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertSame((string)$retailer->user_id, (string)$retailer2->user_id);
        $this->assertSame($retailer->email, $retailer2->email);
        $this->assertSame($retailer->name, $retailer2->name);
        $this->assertSame($retailer->description, $retailer2->description);
        $this->assertSame('retailer', $retailer2->object_type);
        $this->assertSame('1', (string)$retailer->modified_by);
    }

    public function testSoftDeleteRecord()
    {
        $retailer = Retailer::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first()
                            ->delete();

        $retailer2 = Retailer::withDeleted()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertSame('deleted', $retailer2->status);
    }

    public function testHardDeleteRecord()
    {
        $merchant = Retailer::withDeleted()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first()
                            ->delete(TRUE);

        $merchant2 = Retailer::withDeleted()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertTrue(is_null($merchant2));
    }
}
