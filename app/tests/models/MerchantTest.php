<?php
/**
 * Unit test for model Merchant model
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @copyright DominoPOS Ltd.
 */
class MerchantTest extends OrbitTestCase
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
                    (`merchant_id`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `city_id`, `city`, `country_id`, `country`,
                    `phone`, `fax`, `start_date_activity`, `status`, `logo`,
                    `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `object_type`, `parent_id`,
                    `created_at`, `updated_at`, `modified_by`, `postal_code`, `contact_person_firstname`,
                    `contact_person_position`, `contact_person_phone`, `sector_of_activity`,
                    `url`, `end_date_activity`, `masterbox_number`, `slavebox_number`,
                    `contact_person_lastname`, `contact_person_phone2`, `contact_person_email`
                    )
                    VALUES
                    ('1', '2', 'alfamer@localhost.org', 'Alfa Mer', 'Super market Alfa', 'Jl. Tunjungan 01', 'Komplek B1', 'Lantai 01', '10', 'Surabaya', '62', 'Indonesia', '031-7123456', '031-712344', '2012-01-02 01:01:01', 'active', 'merchants/logo/alfamer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Murah dan Tidak Hemat', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 621234, 'Cak Lontong 1', 'Jual Lontong 1', '0123-3456789', 'Retail', 'http://localhost.one/', '2015-02-01 00:00:00', 'M111', 'S111', 'Satu', '2123-3456789', 'lontong1@localhost.org'),
                    ('2', '3', 'indomer@localhost.org', 'Indo Mer', 'Super market Indo', 'Jl. Tunjungan 02', 'Komplek B2', 'Lantai 02', '10', 'Surabaya', '62', 'Indonesia', '031-8123456', '031-812344', '2012-02-02 01:01:02', 'active', 'merchants/logo/indomer1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Harga Kurang Pas', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 622234, 'Cak Lontong 2', 'Jual Lontong 2', '0123-3456789', 'Retail Lontong 2', 'http://localhost.two/', '2015-02-02 00:00:00', 'M222', 'S222', 'Dua', '2223-3456789', 'lontong2@localhost.org'),
                    ('3', '2', 'mitra9@localhost.org', 'Mitra 9', 'Super market Bangunan', 'Jl. Tunjungan 03', 'Komplek B3', 'Lantai 03', '10', 'Surabaya', '62', 'Indonesia', '031-6123456', '031-612344', '2012-03-02 01:01:03', 'pending', 'merchants/logo/mitra9.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Belanja Bangunan Nyaman', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 623234, 'Cak Lontong 3', 'Jual Lontong 3', '0123-3456789', 'Retail', 'http://localhost.three/', '2015-02-03 00:00:00', 'M333', 'S333', 'Tiga', '2323-3456789', 'lontong3@localhost.org'),
                    ('4', '1', 'keefce@localhost.org', 'Ke Ef Ce', 'Chicket Fast Food', 'Jl. Tunjungan 04', 'Komplek B4', 'Lantai 04', '10', 'Surabaya', '62', 'Indonesia', '031-5123456', '031-512344', '2012-04-02 01:01:04', 'blocked', 'merchants/logo/keefce1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Bukan Jagonya Ayam!', 'yes', 'merchant', NOW(), NULL, NOW(), 1, 624234, 'Cak Lontong 4', 'Jual Lontong 4', '0123-3456789', 'Retail', 'http://localhost.four/', '2015-02-04 00:00:00', 'M444', 'S444', 'Satu', '2423-3456789', 'lontong4@localhost.org'),
                    ('5', '1', 'mekdi@localhost.org', 'Mek Di', 'Burger Fast Food', 'Jl. Tunjungan 05', 'Komplek B5', 'Lantai 05', '10', 'Surabaya', '62', 'Indonesia', '031-4123456', '031-412344', '2012-05-02 01:01:05', 'inactive', 'merchants/logo/mekdi1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'I\'m not lovit', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 625234, 'Cak Lontong 5', 'Jual Lontong 5', '0123-3456789', 'Retail', 'http://localhost.five/', '2015-02-05 00:00:00', 'M555', 'S555', 'Lima', '2523-3456789', 'lontong5@localhost.org'),
                    ('6', '1', 'setarbak@localhost.org', 'Setar Bak', 'Tempat Minum Kopi', 'Jl. Tunjungan 06', 'Komplek B6', 'Lantai 06', '10', 'Surabaya', '62', 'Indonesia', '031-3123456', '031-312344', '2012-06-02 01:01:06', 'deleted', 'merchants/logo/setarbak1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Coffee and TV', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 626234, 'Cak Lontong 6', 'Jual Lontong 6', '0123-3456789', 'Retail', 'http://localhost.six/', '2015-02-07 00:00:00', 'M666', 'S666', 'Enam', '2623-3456789', 'lontong6@localhost.org'),
                    ('7', '3', 'matabulan@localhost.org', 'Mata Bulan', 'Tempat Beli Baju', 'Jl. Tunjungan 07', 'Komplek B7', 'Lantai 07', '10', 'Surabaya', '62', 'Indonesia', '031-2123456', '031-212344', '2012-07-02 01:01:06', 'inactive', 'merchants/logo/matabulan.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Sale Everyday', 'yes', 'merchant', NULL, NOW(), NOW(), 1, 627234, 'Cak Lontong 7', 'Jual Lontong 7', '0123-3456789', 'Retail', 'http://localhost.seven/', '2015-02-07 00:00:00', 'M777', 'S777', 'Tujuh', '2723-3456789', 'lontong7@localhost.org'),
                    ('8', '8', 'dummy@localhost.org', 'Dummy Object', 'Doom', 'Jl. Tunjungan 08', 'Komplek B8', 'Lantai 08', '10', 'Surabaya', '62', 'Indonesia', '031-1123456', '031-112344', '2012-08-02 01:01:08', 'active', 'merchants/logo/dummy1.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'dummy', NULL, NOW(), NOW(), 1, 628234, 'Cak Lontong 8', 'Jual Lontong 8', '0123-3456789', 'Retail', 'http://localhost.eight/', '2015-02-08 00:00:00', 'M888', 'S888', 'Delapan', '2823-3456789', 'lontong8@localhost.org'),
                    ('9', '4', 'alfagubeng@localhost.org', 'Alfa Mer Gubeng Pojok', 'Alfa Mer which near Gubeng Station Surabaya', 'Jl. Gubeng 09', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'active', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 2, NOW(), NOW(), 1, 629234, 'Cak Lontong 9', 'Jual Lontong 9', '0123-3456789', 'Retail', 'http://localhost.nine/', '2015-02-09 00:00:00', 'M999', 'S999', 'Sembilan', '2923-3456789', 'lontong9@localhost.org'),
                    ('10', '4', 'alfagubengX@localhost.org', 'Alfa Mer Gubeng PojokX', 'Alfa Mer which near Gubeng Station SurabayaX', 'Jl. Gubeng 09X', 'Komplek B9', 'Lantai 09', '10', 'Surabaya', '62', 'Indonesia', '031-1923456', '031-192344', '2012-09-02 01:01:09', 'deleted', 'merchants/logo/alfamer-gubeng.png', 'IDR', 'Rp', 'tx1', 'tx2', 'tx3', 'Big Doom', 'yes', 'retailer', 2, NOW(), NOW(), 1, 629234, 'Cak Lontong 9X', 'Jual Lontong 9X', '0123-3456789', 'Retail', 'http://localhost.nine/', '2015-02-09 00:00:00', 'M999X', 'S999X', 'SembilanX', '2923-3456789X', 'lontong9@localhost.orgx')"
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
        $expect = 'Merchant';
        $return = new Merchant();
        $this->assertInstanceOf($expect, $return);
    }

    public function testNumberOfRecords()
    {
        $expect = 7;
        $return = Merchant::count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsPlusUnknown()
    {
        $expect = 10;
        $return = Merchant::withUnknown()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsActive()
    {
        $expect = 2;
        $return = Merchant::active()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsBlocked()
    {
        $expect = 1;
        $return = Merchant::blocked()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsPending()
    {
        $expect = 1;
        $return = Merchant::pending()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsDeleted()
    {
        $expect = 1;
        $return = Merchant::withDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsInactive()
    {
        $expect = 2;
        $return = Merchant::inactive()->count();
        $this->assertSame($expect, $return);
    }

    public function testNumberOfRecordsExcludeDeleted()
    {
        $expect = 6;
        $return = Merchant::excludeDeleted()->count();
        $this->assertSame($expect, $return);
    }

    public function testRecordNumber2()
    {
        $merchant = Merchant::with('user', 'retailers', 'retailers.user', 'retailersNumber')->active()->find(2);
        $this->assertSame('indomer@localhost.org', $merchant->email);
        $this->assertSame('Indo Mer', $merchant->name);
        $this->assertSame('Super market Indo', $merchant->description);
        $this->assertSame('Jl. Tunjungan 02', $merchant->address_line1);
        $this->assertSame('Komplek B2', $merchant->address_line2);
        $this->assertSame('Lantai 02', $merchant->address_line3);
        $this->assertSame('10', (string)$merchant->city_id);
        $this->assertSame('Surabaya', $merchant->city);
        $this->assertSame('62', (string)$merchant->country_id);
        $this->assertSame('Indonesia', $merchant->country);
        $this->assertSame('031-8123456', $merchant->phone);
        $this->assertSame('031-812344', $merchant->fax);
        $this->assertSame('2012-02-02 01:01:02', $merchant->start_date_activity);
        $this->assertSame('active', $merchant->status);
        $this->assertSame('merchants/logo/indomer1.png', $merchant->logo);
        $this->assertSame('IDR', $merchant->currency);
        $this->assertSame('Rp', $merchant->currency_symbol);
        $this->assertSame('tx1', $merchant->tax_code1);
        $this->assertSame('tx2', $merchant->tax_code2);
        $this->assertSame('tx3', $merchant->tax_code3);
        $this->assertSame('Harga Kurang Pas', $merchant->slogan);
        $this->assertSame('yes', $merchant->vat_included);
        $this->assertSame('merchant', $merchant->object_type);
        $this->assertSame('1', (string)$merchant->modified_by);
        $this->assertSame('622234', (string)$merchant->postal_code);
        $this->assertSame('Cak Lontong 2', (string)$merchant->contact_person_firstname);
        $this->assertSame('Dua', (string)$merchant->contact_person_lastname);
        $this->assertSame('Jual Lontong 2', (string)$merchant->contact_person_position);
        $this->assertSame('0123-3456789', (string)$merchant->contact_person_phone);
        $this->assertSame('2223-3456789', (string)$merchant->contact_person_phone2);
        $this->assertSame('lontong2@localhost.org', (string)$merchant->contact_person_email);
        $this->assertSame('Retail Lontong 2', (string)$merchant->sector_of_activity);
        $this->assertSame('http://localhost.two/', $merchant->url);
        $this->assertSame('2015-02-02 00:00:00', $merchant->end_date_activity);
        $this->assertSame('M222', $merchant->masterbox_number);
        $this->assertSame('S222', $merchant->slavebox_number);

        $this->assertSame('chuck@localhost.org', $merchant->user->user_email);
        $this->assertSame('chuck', $merchant->user->username);

        $alfagubeng = $merchant->retailers->first();
        $this->assertSame('alfagubeng@localhost.org', $alfagubeng->email);
        $this->assertSame('Alfa Mer Gubeng Pojok', $alfagubeng->name);
        $this->assertSame('optimus@localhost.org', $alfagubeng->user->user_email);

        // Check number of retailers associated with this merchant
        $this->assertSame('1', (string)$merchant->retailersCount);
    }

    public function testInsertRecord()
    {
        $merchant = new Merchant();
        $merchant->object_type = 'foo'; // should be forced to be merchant
        $merchant->user_id = 2;
        $merchant->email = 'texaschicken@localhost.org';
        $merchant->name = 'Texas Chicken';
        $merchant->description = 'Mantab';
        $merchant->modified_by = 1;
        $merchant->contact_person_firstname = 'Brudin';
        $merchant->contact_person_lastname = 'Cool';
        $merchant->contact_person_position = 'Joker';
        $merchant->contact_person_phone = '777|#|71717171';
        $merchant->contact_person_phone2 = '999|#|9191919';
        $merchant->contact_person_email = 'brudin@localhost.org';
        $merchant->sector_of_activity = 'Entertainment';
        $merchant->url = 'http://localhost.ten/';
        $merchant->end_date_activity = '2015-02-10 00:00:00';
        $merchant->masterbox_number = 'M101010';
        $merchant->slavebox_number = 'S101010';
        $merchant->save();

        $merchant2 = Merchant::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertSame((string)$merchant->user_id, (string)$merchant2->user_id);
        $this->assertSame($merchant->email, $merchant2->email);
        $this->assertSame($merchant->name, $merchant2->name);
        $this->assertSame($merchant->description, $merchant2->description);
        $this->assertSame('merchant', $merchant2->object_type);
        $this->assertSame('1', (string)$merchant->modified_by);
        $this->assertSame('Brudin', $merchant->contact_person_firstname);
        $this->assertSame('Cool', $merchant->contact_person_lastname);
        $this->assertSame('Joker', $merchant->contact_person_position);
        $this->assertSame('777|#|71717171', $merchant->contact_person_phone);
        $this->assertSame('999|#|9191919', $merchant->contact_person_phone2);
        $this->assertSame('brudin@localhost.org', $merchant->contact_person_email);
        $this->assertSame('Entertainment', $merchant->sector_of_activity);
        $this->assertSame('http://localhost.ten/', $merchant->url);
        $this->assertSame('2015-02-10 00:00:00', $merchant->end_date_activity);
        $this->assertSame('M101010', $merchant->masterbox_number);
        $this->assertSame('S101010', $merchant->slavebox_number);
    }

    public function testUpdateRecord()
    {
        $merchant = Merchant::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $merchant->object_type = 'bar'; // should be forced to be merchant
        $merchant->user_id = 2;
        $merchant->email = 'texaschicken@localhost.org';
        $merchant->name = 'Texas Chicken';
        $merchant->description = 'Mantab';
        $merchant->modified_by = 1;
        $merchant->sector_of_activity = 'Sports';
        $merchant->phone = '031|#|74123456';
        $merchant->url = 'http://localhost.10/';
        $merchant->save();

        $merchant2 = Merchant::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertSame((string)$merchant->user_id, (string)$merchant2->user_id);
        $this->assertSame($merchant->email, $merchant2->email);
        $this->assertSame($merchant->name, $merchant2->name);
        $this->assertSame($merchant->description, $merchant2->description);
        $this->assertSame('merchant', $merchant2->object_type);
        $this->assertSame('Sports', $merchant2->sector_of_activity);
        $this->assertSame('031|#|74123456', $merchant2->phone);
        $this->assertSame('http://localhost.10/', $merchant2->url);
        $this->assertSame('1', (string)$merchant->modified_by);
    }

    public function testDisplayPhoneNumber()
    {
        $merchant = Merchant::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $area = '031';
        $this->assertSame($area, $merchant->getPhoneCodeArea());

        $phone = '74123456';
        $this->assertSame($phone, $merchant->getPhoneNumber());

        $expect = '031 74123456';
        $separator = '|#|';
        $concat = ' ';
        $this->assertSame($expect, $merchant->getFullPhoneNumber($separator, $concat));
    }

    public function testSoftDeleteRecord()
    {
        $merchant = Merchant::active()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first()
                            ->delete();

        $merchant2 = Merchant::withDeleted()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertSame('deleted', $merchant2->status);
    }

    public function testHardDeleteRecord()
    {
        $merchant = Merchant::withDeleted()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first()
                            ->delete(TRUE);

        $merchant2 = Merchant::withDeleted()
                            ->where('email', 'texaschicken@localhost.org')
                            ->first();

        $this->assertTrue(is_null($merchant2));
    }
}
