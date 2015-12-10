<?php
/**
 * Seeder for User
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class UserTableSeeder extends Seeder
{
    public function run()
    {
        // Super Admin account
        // -------------------
        // Password taken from environment variables
        if (! isset($_SERVER['ORBIT_SUPER_ADMIN_PASSWORD'])) {
            throw new Exception('Missing ORBIT_SUPER_ADMIN_PASSWORD environment variable.');
        }

        $password = $_SERVER['ORBIT_SUPER_ADMIN_PASSWORD'];
        $superAdminData = [
            'user_id'           => 1,
            'username'          => 'orbitadmin',
            'user_email'        => 'orbitadmin@myorbit.com',
            'user_password'     => Hash::make($password),
            'user_firstname'    => 'Orbit',
            'user_lastname'     => 'Admin',
            'status'            => 'active',
            'user_role_id'      => 1 // => Super Admin
        ];

        $this->command->info('Seeding users, user_details, and apikeys table...');
        try {
            DB::table('users')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        try {
            DB::table('user_details')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        User::unguard();
        $superAdmin = User::create($superAdminData);
        $this->command->info(sprintf('    Create Super Admin record username: %s.', $superAdminData['username']));

        // Record for user_details table
        $superAdminDetail = [
            'user_detail_id'    => 1,
            'user_id'           => 1
        ];
        UserDetail::unguard();
        UserDetail::create($superAdminDetail);
        $this->command->info('    Create Super Admin record on user_details.');

        // Record for apikeys table
        $superAdmin->createApiKey();
        $this->command->info('    Create Api Key record for Super Admin.');
        $this->command->info('users, user_details, and apikeys table seeded.');

        $this->command->info('Deleting password file...');
        if (! @unlink($passwordFile)) {
            $this->command->info('Failed to delete password file.');
        } else {
            $this->command->info('Password file has been deleted.');
        }
    }
}
