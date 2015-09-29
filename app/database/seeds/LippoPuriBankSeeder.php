<?php
/**
 * Seeder for Banks are linked to merchant_id '{$mall_id}
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
        $mall_id = MerchantDataSeeder::MALL_ID;
        // seeding bank object
        $bankObjects = <<<BANK
DELETE FROM `{$prefix}objects` WHERE `merchant_id` = '{$mall_id}' AND `object_type` = 'bank';

INSERT INTO `{$prefix}objects` (`merchant_id`, `object_name`, `object_type`, `status`, `created_at`, `updated_at`) VALUES
('{$mall_id}', 'ANZ', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'ARTA GRAHA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BCA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BII', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BNI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BRI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BTN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BTPN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'BUKOPIN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'CIMB NIAGA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'CITIBANK', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'COMMON WEALTH', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'DANAMON', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'DBS', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'EKONOMI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'HSBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'ICBC', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'INDEX', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'MANDIRI', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'MASPION', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'MAYAPADA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'MEGA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'MUAMALAT', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'NOBU', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'OCBC NISP', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'PANIN', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'PERMATA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'SINARMAS', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'STANDARD CHARTERED', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'UOB', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'VICTORIA', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('{$mall_id}', 'others', 'bank', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
BANK;

        $this->command->info('Seeding objects table for bank data...');
        DB::unprepared($bankObjects);
        $this->command->info('table objects for bank data seeded.');
    }

}
