<?php
/**
 * Seeder for Banks are linked to merchant_id 2
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

        // seeding bank object
        $bankObjects = <<<BANK
DELETE FROM `{$prefix}objects` WHERE `merchant_id` = 2 AND `object_type` = 'bank';

INSERT INTO `{$prefix}objects` (`merchant_id`, `object_name`, `object_type`, `status`, `created_at`, `updated_at`) VALUES
(2, 'DBS', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'OCBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'UOB', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'HSBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'MAYBANK', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'CITIBANK', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'STANDARD CHARTERED', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'ABN AMRO', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'others', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
BANK;

        $this->command->info('Seeding objects table for bank data...');
        DB::unprepared($bankObjects);
        $this->command->info('table objects for bank data seeded.');
    }

}
