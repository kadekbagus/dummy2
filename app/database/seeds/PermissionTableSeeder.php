<?php
/**
 * Seeder for Permissions
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        // Permission normally consist of four types:
        // ------------------------------------------
        // 1. Create    : Permission to create a resource
        // 2. View      : Permission to view resources
        // 3. Update    : Permission to update a resource
        // 4. Delete    : Permission to delete a resource

        // List of resources on Orbit Shop Application are:
        // ------------------------------------------------
        // 1. Role*
        // 2. User*
        // 3. Merchant*
        // 4. Retailer*
        // 5. Product*
        // 6. Category*
        // 7. Promotion*
        // 8. Coupon*
        // 9. Product Attribute*
        // 10. Employee*
        // 11. Event*
        // 12. Personal Interest*
        // 13. Widget*
        // 14. POS Quick Product*
        // 15. Issued Coupon*
        // 16. Activity*
        // 17. Transaction History*
        // 18. Password*
        // 19. Tax
        $permissionsSource = [
            'Role'      => [
                'name'  => 'role',
                'order' => 1,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'User'      => [
                'name'  => 'user',
                'order' => 2,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Merchant'  => [
                'name'  => 'merchant',
                'order' => 3,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Retailer'  => [
                'name'  => 'retailer',
                'order' => 4,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Product'  => [
                'name'  => 'product',
                'order' => 5,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Category'  => [
                'name'  => 'category',
                'order' => 6,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Promotion' => [
                'name'  => 'promotion',
                'order' => 7,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Coupon'    => [
                'name'  => 'coupon',
                'order' => 8,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Product Attribute' => [
                'name'  => 'product_attribute',
                'order' => 9,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Employee'  => [
                'name'  => 'employee',
                'order' => 10,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Event'     => [
                'name'  => 'event',
                'order' => 11,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Personal Interest'  => [
                'name'  => 'personal_interest',
                'order' => 12,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Widget'  => [
                'name'  => 'widget',
                'order' => 13,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Pos Quick Product'  => [
                'name'  => 'pos_quick_product',
                'order' => 14,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Issued Coupon'  => [
                'name'  => 'issued_coupon',
                'order' => 15,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Activity'  => [
                'name'  => 'activity',
                'order' => 16,
                'perm'  => ['view']
            ],
            'Transaction History'  => [
                'name'  => 'transaction_history',
                'order' => 17,
                'perm'  => ['view']
            ],
            'Password'  => [
                'name'  => 'password',
                'order' => 18,
                'perm'  => ['change']
            ],
            'Tax'  => [
                'name'  => 'tax',
                'order' => 19,
                'perm'  => ['view']
            ],
            'Setting'  => [
                'name'  => 'setting',
                'order' => 20,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
        ];

        $permissions = [];

        // Build the array of permission to be inserted on permissions table
        $primaryKey = 1;
        foreach ($permissionsSource as $permLabel=>$perm) {
            foreach ($perm['perm'] as $action) {
                $permissions[] = [
                        'permission_id'             => $primaryKey++,
                        'permission_name'           => sprintf('%s_%s', $action, $perm['name']),
                        'permission_label'          => $permLabel,
                        'permission_group'          => $perm['name'],
                        'permission_group_label'    => $permLabel,
                        'permission_name_order'     => $perm['order'],
                        'permission_group_order'    => $perm['order'],
                        'permission_default_value'  => 'no'
                ];
            }
        }

        $this->command->info('Seeding permissions table...');

        try {
            DB::table('permissions')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        foreach ($permissions as $permission) {
            Permission::unguard();
            Permission::create($permission);
            $this->command->info(sprintf('    Create record for %s.', $permission['permission_name']));
        }
        $this->command->info('permissions table seeded.');
    }
}
