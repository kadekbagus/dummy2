<?php
/**
 * Seeder for Banks are linked to merchant_id 2
 * the 'Lippo Mall Puri'.
 *
 * @author Tian <tian@dominopos.com>
 */
class LippoPuriBankSeeder extends Seeder
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
(2, 'ANZ', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'ARTA GRAHA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BCA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BII', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BNI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BRI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BTN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BTPN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'BUKOPIN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'CIMB NIAGA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'CITIBANK', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'COMMON WEALTH', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'DANAMON', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'DBS', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'EKONOMI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'HSBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'ICBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'INDEX', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'MANDIRI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'MASPION', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'MAYAPADA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'MEGA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'MUAMALAT', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'NOBU', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'OCBC NISP', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'PANIN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'PERMATA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'SINARMAS', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'STANDARD CHARTERED', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'UOB', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'VICTORIA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'others', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
BANK;

        $this->command->info('Seeding objects table for bank data...');
        DB::unprepared($bankObjects);
        $this->command->info('table objects for bank data seeded.');
    }

}
