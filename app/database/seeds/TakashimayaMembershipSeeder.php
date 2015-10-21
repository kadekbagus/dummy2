<?php
use Orbit\Database\ObjectID;
/**
 * Seeder for Memberships are linked to merchant_id {$merchantId}
 * the 'Takashimaya Shopping Center'.
 *
 */
class TakashimayaMembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $prefix = DB::getTablePrefix();
        $generatedIDs = [
            'membership' => []
        ];
        $pdo = DB::connection()->getPdo();
        $merchantId = $pdo->quote(TakashimayaMerchantSeeder::MALL_ID);

        $generateID = function ($group, $sequence) use (&$generatedIDs, $pdo)
        {
                $id = $this->generateID();
                $id = $pdo->quote($id);
                $generatedIDs[$group] [$sequence] = $id;
                return $id;
        };

        // delete all memberships data
        DB::table('memberships')->truncate();

        // seeding memberships
        $memberships = <<<MEMBERSHIPS
INSERT INTO `{$prefix}memberships` (`membership_id`, `merchant_id`, `membership_name`, `description`, `status`, `created_at`, `updated_at`) VALUES
({$generateID('membership', 1)}, {$merchantId}, 'Classic Memberships', 'Membership type for classic only', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('membership', 2)}, {$merchantId}, 'Premium Memberships', 'Membership type for premium only', 'inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('membership', 3)}, {$merchantId}, 'Platinum Memberships', 'Membership type for platinum only', 'inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
MEMBERSHIPS;

        $this->command->info('Seeding memberships table...');
        DB::unprepared($memberships);
        $this->command->info('Memberships table seeded.');
    }

    private function generateID()
    {
        return ObjectID::make();
    }

}
