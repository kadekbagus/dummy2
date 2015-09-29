<?php
/**
 * Seeder for Mall
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class MerchantDataSeeder extends Seeder
{
    const MALL_GROUP_ID = "EWv3-LMP--------";
    const MALL_ID = "EXs5F-LMP-------";

    public function run()
    {
        // Mall account but this on the database is recorded as "Merchant"
        $passwordMall = 'lippomall';
        $role = Role::where('role_name', 'Mall Owner')->first();
        $merchantUserData = [
            'username'          => 'lippomall',
            'user_email'        => 'lippomall@myorbit.com',
            'user_password'     => Hash::make($passwordMall),
            'user_firstname'    => 'Lippo',
            'user_lastname'     => 'Mall',
            'status'            => 'active',
            'user_role_id'      => $role->role_id
        ];

        // Mall location account but this on the database is recorded as "Retailer"
        $passwordRetailer = 'lippomallpuri';
        $retailerUserData = [
            'username'          => 'lippomallpuri',
            'user_email'        => 'lippomallpuri@myorbit.com',
            'user_password'     => Hash::make($passwordRetailer),
            'user_firstname'    => 'Orbit',
            'user_lastname'     => 'Mall',
            'status'            => 'active',
            'user_role_id'      => $role->role_id
        ];

        // ------- MERCHANT USER
        $this->command->info('Seeding merchant and retailer data...');
        try {
            DB::table('merchants')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        User::unguard();

        $merchantUser = User::create($merchantUserData);
        $this->command->info(sprintf('    Create users record for merchant, username: %s.', $merchantUserData['username']));

        // Record for user_details table
        $merchantUserDetail = [
            'user_id'           => $merchantUser->user_id
        ];
        UserDetail::unguard();
        UserDetail::create($merchantUserDetail);
        $this->command->info('    Create merchant record on user_details.');

        // Record for apikeys table
        $merchantUser->createApiKey();

        // ------- RETAILER USER
        $retailerUser = User::create($retailerUserData);
        $this->command->info(sprintf('    Create users record for retailer, username: %s.', $retailerUserData['username']));

        // Record for user_details table
        $retailerUserDetail = [
            'user_id'           => $retailerUser->user_id
        ];
        UserDetail::unguard();
        UserDetail::create($retailerUserDetail);
        $this->command->info('    Create retailer record on user_details.');

        // Record for apikeys table
        $retailerUser->createApiKey();

        // Data for merchant
        $merchantData = [
            'merchant_id'   => static::MALL_GROUP_ID,
            'omid'          => 'LIPPO-MALL',
            'user_id'       => $merchantUser->user_id,
            'email'         => 'lippomall@myorbit.com',
            'name'          => 'Lippo Mall',
            'description'   => 'Lippo Mall',
            'status'        => 'active',
            'start_date_activity'   => date('Y-m-d 00:00:00'),
            'postal_code'           => '60123',
            'city_id'               => 0,
            'city'                  => 'Jakarta',
            'country_id'            => 101,
            'country'               => 'Indonesia',
            'phone'                 => '62|#|21|#|987654321',
            'currency'              => 'USD',
            'currency_symbol'       => '$',
            'vat_included'          => 'no',
            'contact_person_firstname'  => 'John',
            'contact_person_lastname'   => 'Doe',
            'contact_person_position'   => 'Marketing',
            'contact_person_phone'      => '62|#||#|812345678',
            'contact_person_email'      => 'john-doe@myorbit.com',
            'sector_of_activity'        => 'Retail',
            'url'                       => 'www.lippomalls.com',
            'is_mall'                   => 'yes',
            'modified_by'               => 0,
        ];

        // Data for retailer
        $retailerData = [
            'merchant_id'   => static::MALL_ID,
            'omid'          => 'LIPPO-MALL-PURI-01',
            'user_id'       => $merchantUser->user_id,
            'email'         => 'lippomallpuri@myorbit.com',
            'name'          => 'Lippo Mall Puri',
            'description'   => 'Lippo Mall Puri',
            'status'        => 'active',
            'parent_id'     => static::MALL_GROUP_ID,
            'start_date_activity'   => date('Y-m-d 00:00:00'),
            'postal_code'           => '60123',
            'city_id'               => 0,
            'city'                  => 'Jakarta',
            'country_id'            => 101,
            'country'               => 'Indonesia',
            'phone'                 => '62|#|21|#|987654321',
            'currency'              => 'USD',
            'currency_symbol'       => '$',
            'vat_included'          => 'no',
            'contact_person_firstname'  => 'John',
            'contact_person_lastname'   => 'Smith',
            'contact_person_position'   => 'Marketing',
            'contact_person_phone'      => '62|#||#|812345679',
            'contact_person_email'      => 'john-smith@myorbit.com',
            'sector_of_activity'        => 'Retail',
            'is_mall'                   => 'yes',
            'url'                       => 'www.lippomallpuri.com',
            'modified_by'               => 0
        ];

        // ------- MERCHANT DATA
        MallGroup::unguard();
        $merchant = MallGroup::create($merchantData);
        $this->command->info(sprintf('    Create record on merchants table, name: %s.', $merchantData['name']));

        // ------- RETAILER DATA
        Mall::unguard();
        $retailer = Mall::create($retailerData);
        $this->command->info(sprintf('    Create record on retailers table, name: %s.', $retailerData['name']));

        $this->command->info('MallGroup and retailer data seeded.');
    }
}
