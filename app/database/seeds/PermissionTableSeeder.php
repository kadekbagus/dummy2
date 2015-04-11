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
        // 3. Mall*
        // 4. Merchant*
        // 5. Retailer*
        // 6. Product*
        // 7. Category*
        // 8. Promotion*
        // 9. Coupon*
        // 10. Product Attribute*
        // 11. Employee*
        // 12. Event*
        // 13. Personal Interest*
        // 14. Widget*
        // 15. POS Quick Product*
        // 16. Issued Coupon*
        // 17. Activity*
        // 18. Transaction History*
        // 19. Password*
        // 20. Tax
        $permSourceNumber = 0;
        $permissionsSource = [
            'Role'      => [
                'name'  => 'role',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'User'      => [
                'name'  => 'user',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Mall'  => [
                'name'  => 'mall',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Merchant'  => [
                'name'  => 'merchant',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Retailer'  => [
                'name'  => 'retailer',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Product'  => [
                'name'  => 'product',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Category'  => [
                'name'  => 'category',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Promotion' => [
                'name'  => 'promotion',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Coupon'    => [
                'name'  => 'coupon',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Product Attribute' => [
                'name'  => 'product_attribute',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Employee'  => [
                'name'  => 'employee',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Event'     => [
                'name'  => 'event',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Personal Interest'  => [
                'name'  => 'personal_interest',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Widget'  => [
                'name'  => 'widget',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Pos Quick Product'  => [
                'name'  => 'pos_quick_product',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Issued Coupon'  => [
                'name'  => 'issued_coupon',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Activity'  => [
                'name'  => 'activity',
                'order' => ++$permSourceNumber,
                'perm'  => ['view']
            ],
            'Transaction History'  => [
                'name'  => 'transaction_history',
                'order' => ++$permSourceNumber,
                'perm'  => ['view']
            ],
            'Password'  => [
                'name'  => 'password',
                'order' => ++$permSourceNumber,
                'perm'  => ['change']
            ],
            'Tax'  => [
                'name'  => 'tax',
                'order' => ++$permSourceNumber,
                'perm'  => ['view']
            ],
            'Setting'  => [
                'name'  => 'setting',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'News'      => [
                'name'  => 'news',
                'order' => ++$permSourceNumber,
                'perm'  => ['create', 'view', 'update', 'delete']
            ],
            'Lucky Draw' => [
                'name'  => 'lucky_draw',
                'order' => ++$permSourceNumber,
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
