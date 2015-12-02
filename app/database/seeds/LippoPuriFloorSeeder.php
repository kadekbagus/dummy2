<?php
use Orbit\Database\ObjectID;
/**
 * Seeder for Floors are linked to merchant_id {$merchantId}
 * the 'Lippo Puri Shopping Center'.
 *
 */
class LippoPuriFloorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $pdo = DB::connection()->getPdo();
        $prefix = DB::getTablePrefix();
        $merchantId = $pdo->quote(MerchantDataSeeder::MALL_ID);
        // seeding floor object
        $floorObjects = <<<FLOOR
DELETE FROM `{$prefix}objects` WHERE `merchant_id` = {$merchantId} AND `object_type` = 'floor';

INSERT INTO `{$prefix}objects` (`object_id`, `merchant_id`, `object_name`, `object_type`, `object_order`, `status`, `created_at`, `updated_at`) VALUES
({$pdo->quote($this->generateID())}, {$merchantId}, 'LG', 'floor', '1', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$pdo->quote($this->generateID())}, {$merchantId}, 'G', 'floor', '2', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$pdo->quote($this->generateID())}, {$merchantId}, 'UG', 'floor', '3', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$pdo->quote($this->generateID())}, {$merchantId}, 'L1', 'floor', '4', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$pdo->quote($this->generateID())}, {$merchantId}, 'L2', 'floor', '5', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
FLOOR;

        $this->command->info('Seeding objects table for floor data...');
        DB::unprepared($floorObjects);
        $this->command->info('table objects for floor data seeded.');
    }

    private function generateID()
    {
        return ObjectID::make();
    }

}
