<?php
/**
 * Seeder for Roles
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class RoleTableSeeder extends Seeder
{
    public function run()
    {
        // Role for Orbit Application should be:
        // -------------------------------------
        // 1. Super Admin
        // 2. Administrator
        // 3. Consumer
        // 4. Merchant Owner
        // 5. Retailer Owner
        // 6. Manager
        // 8. Supervisor
        // 9. Cashier
        // 10. Guest
        $roles = [
            'Super Admin' => [
                'role_id'       => 1,
                'role_name'     => 'Super Admin',
                'role_order'    => 1,
            ],
            'Administrator' => [
                'role_id'       => 2,
                'role_name'     => 'Administrator',
                'role_order'    => 2,
            ],
            'Consumer' => [
                'role_id'       => 3,
                'role_name'     => 'Consumer',
                'role_order'    => 3,
            ],
            'Merchant Owner' => [
                'role_id'       => 4,
                'role_name'     => 'Merchant Owner',
                'role_order'    => 4,
            ],
            'Retailer Owner' => [
                'role_id'       => 5,
                'role_name'     => 'Retailer Owner',
                'role_order'    => 5,
            ],
            'Manager' => [
                'role_id'       => 6,
                'role_name'     => 'Manager',
                'role_order'    => 6,
            ],
            'Supervisor' => [
                'role_id'       => 7,
                'role_name'     => 'Supervisor',
                'role_order'    => 7,
            ],
            'Cashier' => [
                'role_id'       => 8,
                'role_name'     => 'Cashier',
                'role_order'    => 8,
            ],
            'Guest' => [
                'role_id'       => 9,
                'role_name'     => 'Guest',
                'role_order'    => 9,
            ],
        ];

        $this->command->info('Seeding roles table...');

        try {
            DB::table('roles')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        foreach ($roles as $role) {
            Role::unguard();
            Role::create($role);
            $this->command->info(sprintf('    Create record for %s.', $role['role_name']));
        }
        $this->command->info('roles table seeded.');
    }
}
