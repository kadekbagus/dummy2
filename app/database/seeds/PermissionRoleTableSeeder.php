<?php
/**
 * Seeder for Permission Role
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PermissionRoleTableSeeder extends Seeder
{
    public function run()
    {
        // Super admin does not need any records it automatically always allowed
        // to do anything. So, we start from second role which is 'Administrator'
        $permissionRoleSource = [
            'Administator'  =>  [
                // 1
                'role'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 2
                'user'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 5
                'product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 6
                'category'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 10
                'employee'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 11
                'event'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 13
                'widget'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'yes',
                ],
                // 19
                'tax'   => [
                    'view'  => 'yes',
                ],
            ],

            'Consumer'  =>  [
                // 1
                'role'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 2
                'user'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 5
                'product'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 6
                'category'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 10
                'employee'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 11
                'event'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 13
                'widget'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'no',
                ],
                // 19
                'tax'  => [
                    'view'  => 'yes',
                ],
            ],

            'Merchant Owner'  =>  [
                // 1
                'role'  => [
                    'create'    => 'no',
                    'view'      => 'no',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 2
                'user'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 5
                'product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 6
                'category'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 10
                'employee'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 11
                'event'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 13
                'widget'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'no',
                ],
                // 19
                'tax'   => [
                    'view'  => 'yes',
                ],
                // 20
                'setting'   => [
                    'view'  => 'yes',
                ],
            ],

            'Retailer Owner'  =>  [
                // 1
                'role'  => [
                    'create'    => 'no',
                    'view'      => 'no',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 2
                'user'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 5
                'product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 6
                'category'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 10
                'employee'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 11
                'event'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 13
                'widget'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'no',
                ],
                // 19
                'tax'   => [
                    'view'  => 'yes',
                ],
            ],

            'Manager'  =>  [
                // 1
                'role'  => [
                    'create'    => 'no',
                    'view'      => 'no',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 2
                'user'  => [
                    'create'    => 'no',
                    'view'      => 'no',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 5
                'product'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 6
                'category'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 10
                'employee'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 11
                'event'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 13
                'widget'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'yes'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'no',
                ],
                // 19
                'tax'   => [
                    'view'  => 'yes',
                ],
            ],

            'Supervisor'  =>  [
                // 1
                'role'  => [
                    'create'    => 'no',
                    'view'      => 'no',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 2
                'user'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 5
                'product'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 6
                'category'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 10
                'employee'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 11
                'event'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 13
                'widget'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'yes',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'yes',
                    'delete'    => 'no'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'no',
                ],
                // 19
                'tax'   => [
                    'view'  => 'yes',
                ],
            ],

            'Cashier'  =>  [
                // 1
                'role'  => [
                    'create'    => 'no',
                    'view'      => 'no',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 2
                'user'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 3
                'merchant'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 4
                'retailer'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 5
                'product'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 6
                'category'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 7
                'promotion'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 8
                'coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 9
                'product_attribute'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 10
                'employee'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 11
                'event'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 12
                'personal_interest'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 13
                'widget'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 14
                'pos_quick_product'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 15
                'issued_coupon'  => [
                    'create'    => 'no',
                    'view'      => 'yes',
                    'update'    => 'no',
                    'delete'    => 'no'
                ],
                // 16
                'activity'  => [
                    'view'      => 'yes',
                ],
                // 17
                'transaction_history'  => [
                    'view'      => 'yes',
                ],
                // 18
                'password'  => [
                    'change'      => 'no',
                ],
                // 19
                'tax'   => [
                    'view'  => 'yes',
                ],
            ],
        ];

        $permissionRoles = [];

        $roleId = 2;
        foreach ($permissionRoleSource as $roleName=>$permissions) {
            $permissionId = 1;
            foreach ($permissions as $resource=>$resources) {
                foreach ($resources as $action=>$yesNo) {
                    $actionName = sprintf('%s_%s', $action, $resource);
                    $permissionRoles[$roleName . '.' . $actionName] = [
                        'role_id'       => $roleId,
                        'permission_id' => $permissionId++,
                        'allowed'       => $yesNo
                    ];
                }
            }
            $roleId++;
        }

        $this->command->info('Seeding permission_role table...');

        try {
            DB::table('permission_role')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }
        foreach ($permissionRoles as $rolePerm=>$permissionRole) {
            PermissionRole::unguard();
            PermissionRole::create($permissionRole);
            $this->command->info(sprintf('    Create record for %s.', $rolePerm));
        }
        $this->command->info('permission_role table seeded.');
    }
}
