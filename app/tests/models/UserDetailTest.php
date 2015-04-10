<?php
/**
 * Unit test for model UserDetail
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @copyright DominoPOS Ltd.
 */
class UserDetailTest extends OrbitTestCase
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
                ('6', 'droopy', '{$password['droopy']}', 'droopy@localhost.org', 'Droopy', 'Cool', '2014-10-20 06:20:06', '10.10.0.14', '3', 'pending', '1', '2014-10-20 06:30:06', '2014-10-20 06:31:06')"
        );

        // Insert dummy data on user_details
        DB::statement("INSERT INTO `{$user_detail_table}`
                    (user_detail_id, user_id, merchant_id, merchant_acquired_date, address_line1, address_line2, address_line3, postal_code, city_id, city, province_id, province, country_id, country, currency, currency_symbol, birthdate, gender, relationship_status, phone, photo, number_visit_all_shop, amount_spent_all_shop, average_spent_per_month_all_shop, last_visit_any_shop, last_visit_shop_id, last_purchase_any_shop, last_purchase_shop_id, last_spent_any_shop, last_spent_shop_id, modified_by, created_at, updated_at)
                    VALUES
                    ('1', '1', '1', '2014-10-21 06:20:01', 'Jl. Raya Semer', 'Kerobokan', 'Near Airplane Statue', '60219', '1', 'Denpasar', '1', 'Bali', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-01', 'm', 'single',       '081234567891', 'images/customer/01.png', '10', '8100000.00', '1100000.00', '2014-10-21 12:12:11', '1', '2014-10-16 12:12:12', '1', '1100000.00', '1', '1', '2014-10-11 06:20:01', '2014-10-11 06:20:01'),
                    ('2', '2', '2', '2014-10-21 06:20:02', 'Jl. Raya Semer2', 'Kerobokan2', 'Near Airplane Statue2', '60229', '2', 'Denpasar2', '2', 'Bali2', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-02', 'm', 'single',  '081234567892', 'images/customer/02.png', '11', '8200000.00', '1200000.00', '2014-10-21 12:12:12', '2', '2014-10-17 12:12:12', '2', '1500000.00', '2', '1', '2014-10-12 06:20:01', '2014-10-12 06:20:02'),
                    ('3', '3', '3', '2014-10-21 06:20:03', 'Jl. Raya Semer3', 'Kerobokan3', 'Near Airplane Statue3', '60239', '3', 'Denpasar3', '3', 'Bali3', '62', 'Indonesia', 'EUR', '€', '1980-04-03', 'm', 'single',   '081234567893', 'images/customer/03.png', '12', '8300000.00', '1300000.00', '2014-10-21 12:12:13', '3', '2014-10-18 12:12:12', '3', '1400000.00', '3', '1', '2014-10-13 06:20:01', '2014-10-13 06:20:03'),
                    ('4', '4', '4', '2014-10-21 06:20:04', 'Jl. Raya Semer4', 'Kerobokan4', 'Near Airplane Statue4', '60249', '4', 'Denpasar4', '4', 'Bali4', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-04', 'm', 'single',  '081234567894', 'images/customer/04.png', '13', '8400000.00', '1400000.00', '2014-10-21 12:12:14', '4', '2014-10-19 12:12:12', '4', '1300000.00', '4', '1', '2014-10-14 06:20:04', '2014-10-14 06:20:04'),
                    ('5', '$', '4', '2014-10-21 06:20:05', 'Jl. Raya Semer5', 'Kerobokan5', 'Near Airplane Statue5', '60259', '5', 'Denpasar5', '5', 'Bali5', '62', 'Indonesia', 'IDR', 'Rp', '1980-04-05', 'm', 'single',  '081234567895', 'images/customer/05.png', '14', '8500000.00', '1500000.00', '2014-10-21 12:12:15', '5', '2014-10-20 12:12:12', '5', '1200000.00', '5', '1', '2014-10-15 06:20:05', '2014-10-15 06:20:05')"
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
        DB::unprepared("TRUNCATE `{$user_table}`;
                        TRUNCATE `{$user_detail_table}`;");
    }

    public function testObjectInstance()
    {
        $expect = 'UserDetail';
        $return = new UserDetail();
        $this->assertInstanceOf($expect, $return);
    }

    public function testNumberOfRecords()
    {
        $expect = 5;
        $return = UserDetail::count();
        $this->assertSame($expect, $return);
    }

    public function xtestRelationshipExists_user_andReturn_BelongsTo()
    {
        $detail = new UserDetail();
        $return = method_exists($detail, 'user');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        $return = $detail->user();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRecordNumber3()
    {
        $detail = UserDetail::whereUserId(3)->first();
        $this->assertSame('3', (string)$detail->user_detail_id);
        $this->assertSame('3', (string)$detail->user_id);
        $this->assertSame('3', (string)$detail->merchant_id);
        $this->assertSame('2014-10-21 06:20:03', (string)$detail->merchant_acquired_date);
        $this->assertSame('Jl. Raya Semer3', (string)$detail->address_line1);
        $this->assertSame('Kerobokan3', (string)$detail->address_line2);
        $this->assertSame('Near Airplane Statue3', (string)$detail->address_line3);
        $this->assertSame('60239', (string)$detail->postal_code);
        $this->assertSame('3', (string)$detail->city_id);
        $this->assertSame('Denpasar3', (string)$detail->city);
        $this->assertSame('3', (string)$detail->province_id);
        $this->assertSame('Bali3', (string)$detail->province);
        $this->assertSame('62', (string)$detail->country_id);
        $this->assertSame('Indonesia', (string)$detail->country);
        $this->assertSame('EUR', (string)$detail->currency);
        // \342\202\254 is euro simbol character €
        $this->assertSame("\342\202\254", (string)$detail->currency_symbol);
        $this->assertSame('1980-04-03', (string)$detail->birthdate);
        $this->assertSame('m', (string)$detail->gender);
        $this->assertSame('single', (string)$detail->relationship_status);
        $this->assertSame('081234567893', (string)$detail->phone);
        $this->assertSame('images/customer/03.png', (string)$detail->photo);
        $this->assertSame('12', (string)$detail->number_visit_all_shop);
        $this->assertSame('8300000.00', (string)$detail->amount_spent_all_shop);
        $this->assertSame('1300000.00', (string)$detail->average_spent_per_month_all_shop);
        $this->assertSame('2014-10-21 12:12:13', (string)$detail->last_visit_any_shop);
        $this->assertSame('3', (string)$detail->last_visit_shop_id);
        $this->assertSame('2014-10-18 12:12:12', (string)$detail->last_purchase_any_shop);
        $this->assertSame('3', (string)$detail->last_purchase_shop_id);
        $this->assertSame('1400000.00', (string)$detail->last_spent_any_shop);
        $this->assertSame('3', (string)$detail->last_spent_shop_id);
        $this->assertSame('1', (string)$detail->modified_by);
    }

    public function testInsertOneRecord()
    {
        $detail = new UserDetail();
        $detail->user_id = 6;
        $detail->merchant_id = 6;
        $detail->merchant_acquired_date = DB::raw('NOW()');
        $detail->address_line1 = 'Jl. Raya Semer6';
        $detail->modified_by = 1;
        $detail->save();

        // total number of records shoulb be 6
        $expect = 6;
        $return = UserDetail::count();
        $this->assertSame($expect, $return);
    }

    public function testRelationshipExists_user_andReturn_BelongsTo()
    {
        $detail = new UserDetail();
        $return = method_exists($detail, 'modifier');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        $return = $detail->user();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationshipData_user_ChuckNorris()
    {
        $detail = UserDetail::with(array('user'))->whereUserId(3)->first();
        $this->assertSame('1', (string)$detail->modifier->user_id);
        $this->assertSame('chuck', $detail->user->username);
        $this->assertSame('chuck@localhost.org', $detail->user->user_email);
    }

    public function testRelationshipEmptyData_user_PinkPanther_statusDeleted()
    {
        $detail = UserDetail::with(array('user'))->whereUserId(5)->first();
        $return = is_null($detail);
        $this->assertTrue($return);
    }

    public function testRelationshipExists_modifier_andReturn_BelongsTo()
    {
        $detail = new UserDetail();
        $return = method_exists($detail, 'modifier');
        $this->assertTrue($return);

        $expect = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        $return = $detail->modifier();
        $this->assertInstanceOf($expect, $return);
    }

    public function testRelationshipData_modifier_recordNumber3()
    {
        $detail = UserDetail::with(array('modifier'))->whereUserId(3)->first();
        $this->assertSame('1', (string)$detail->modifier->user_id);
        $this->assertSame('john', $detail->modifier->username);
        $this->assertSame('john@localhost.org', $detail->modifier->user_email);
    }

    public function testScopeActive()
    {
        // The active status should be retrieved through join with table
        // `user` on the _user_ belongsTo relationship
        $expect = 3;
        $return = UserDetail::active()->count();
        $this->assertSame($expect, $return);
    }
}
