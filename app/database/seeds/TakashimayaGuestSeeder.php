<?php
/**
 * Seeder for User Guest
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
class TakashimayaGuestSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('role_name', 'Guest')->first();

        $this->command->info('Seeding users, user_details, and apikeys table...');

        // delete previous guest user
        try {
            $oldGuestUser = User::where('user_role_id', $role->role_id)->first();
            if (is_object($oldGuestUser)) {
                $oldGuestUserDetail = UserDetail::where('user_id', $oldGuestUser->user_id)->first();
                if (is_object($oldGuestUser)) {
                    $oldGuestUserDetail->delete(TRUE);
                }

                $oldGuestUser->delete(TRUE);
            }
        } catch (Illuminate\Database\QueryException $e) {
        }

        // Record for users table
        $password = 'Guest123';
        $guestData = [
            
            'username'          => 'guestuser',
            'user_email'        => 'guest@myorbit.com',
            'user_password'     => Hash::make($password),
            'user_firstname'    => 'Guest',
            'user_lastname'     => 'User',
            'status'            => 'active',
            'user_role_id'      => $role->role_id // => Guest
        ];

        $this->command->info('Seeding guest user');

        User::unguard();
        $guestUser = User::create($guestData);
        $this->command->info(sprintf('    Create Guest User record username: %s.', $guestData['username']));

        // Record for user_details table
        $guestDetail = [
            'user_id'    => $guestUser->user_id
        ];
        UserDetail::unguard();
        UserDetail::create($guestDetail);
        $this->command->info('    Create Guest User record on user_details.');

        // Record for apikeys table
        $guestUser->createApiKey();
        $this->command->info('    Create Api Key record for Guest User.');
        $this->command->info('users, user_details, and apikeys table seeded.');

    }
}
