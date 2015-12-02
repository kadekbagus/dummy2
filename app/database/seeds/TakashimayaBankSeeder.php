<?php
/**
 * Seeder for Banks are linked to merchant_id {$merchantId}
 * the 'Takashimaya Shopping Center'.
 *
 */
class TakashimayaBankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $prefix = DB::getTablePrefix();
        $merchantId = DB::connection()->getPdo()->quote(TakashimayaMerchantSeeder::MALL_ID);
        // seeding bank object
        $bankObjects = <<<BANK
DELETE FROM `{$prefix}objects` WHERE `merchant_id` = {$merchantId} AND `object_type` = 'bank';

INSERT INTO `{$prefix}objects` (`merchant_id`, `object_name`, `object_type`, `status`, `created_at`, `updated_at`) VALUES
({$merchantId}, 'DBS', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'OCBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'UOB', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'HSBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'MAYBANK', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'CITIBANK', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'STANDARD CHARTERED', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'ABN AMRO', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$merchantId}, 'others', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
BANK;

        $this->command->info('Seeding objects table for bank data...');
        DB::unprepared($bankObjects);
        $this->command->info('table objects for bank data seeded.');
    }

}
