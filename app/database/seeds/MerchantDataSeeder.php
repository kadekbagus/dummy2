<?php
/**
 * Seeder for Mall
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class MerchantDataSeeder extends Seeder
{
    public function run()
    {
        // Mall account but this on the database is recorded as "Merchant"
        $passwordMall = 'mastermall2015';
        $role = Role::where('role_name', 'Mall Owner')->first();
        $merchantUserData = [
            'user_id'           => 2,
            'username'          => 'mastermall',
            'user_email'        => 'mastermall@myorbit.com',
            'user_password'     => Hash::make($passwordMall),
            'user_firstname'    => 'Orbit',
            'user_lastname'     => 'Master Mall',
            'status'            => 'active',
            'user_role_id'      => $role->role_id
        ];

        // Mall location account but this on the database is recorded as "Retailer"
        $passwordRetailer = 'mastermall2015';
        $retailerUserData = [
            'user_id'           => 3,
            'username'          => 'mall',
            'user_email'        => 'mall@myorbit.com',
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
            'user_detail_id'    => 2,
            'user_id'           => 2
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
            'user_detail_id'    => 3,
            'user_id'           => 3
        ];
        UserDetail::unguard();
        UserDetail::create($retailerUserDetail);
        $this->command->info('    Create retailer record on user_details.');

        // Record for apikeys table
        $merchantUser->createApiKey();

        // Data for merchant
        $merchantData = [
            'merchant_id'   => 1,
            'omid'          => 'ORBIT-MERCHANT-01',
            'user_id'       => 2,
            'email'         => 'mastermall@myorbit.com',
            'name'          => 'Orbit Master Mall',
            'description'   => 'Dummy Master mall for Orbit',
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
            'sector_of_activity'        => '',
            'url'                       => 'myorbit.com',
            'modified_by'               => 0,
        ];

        // Data for retailer
        $retailerData = [
            'merchant_id'   => 2,
            'omid'          => 'ORBIT-RETAILER-01',
            'user_id'       => 3,
            'email'         => 'mall@myorbit.com',
            'name'          => 'Orbit Mall',
            'description'   => 'Dummy mall location for Orbit',
            'status'        => 'active',
            'parent_id'     => 1,
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
            'modified_by'   => 0
        ];

        // ------- MERCHANT DATA
        Merchant::unguard();
        $merchant = Merchant::create($merchantData);
        $this->command->info(sprintf('    Create record on merchants table, name: %s.', $merchantData['name']));

        // ------- RETAILER DATA
        Retailer::unguard();
        $retailer = Retailer::create($retailerData);
        $this->command->info(sprintf('    Create record on retailers table, name: %s.', $retailerData['name']));

        $this->command->info('Merchant and retailer data seeded.');
    }
}
