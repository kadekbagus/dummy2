<?php
/**
 * Seeder for Setting linked to merchant_id 2
 * the 'Lippo Mall Puri'.
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
class LippoPuriWidgetSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $prefix = DB::getTablePrefix();
        $merchantId = DB::connection()->getPdo()->quote(MerchantDataSeeder::MALL_ID);

        // seeding bank object
        $settingObjects = <<<SETTING
DELETE FROM {$prefix}settings where (setting_name = 'enable_coupon' OR setting_name = 'enable_coupon_widget'
OR setting_name='enable_lucky_draw' OR setting_name='enable_lucky_draw_widget') AND object_id={$merchantId} AND object_type='merchant';

INSERT INTO `{$prefix}settings` (`setting_name`, `setting_value`, `object_id`, `object_type`, `status`, `created_at`, `updated_at`) VALUES
('enable_coupon', 'true',{$merchantId} 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_coupon_widget', 'true',{$merchantId} 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_lucky_draw', 'true',{$merchantId} 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_lucky_draw_widget', 'true',{$merchantId} 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
SETTING;

        $this->command->info('Seeding objects table for setting data...');
        DB::unprepared($settingObjects);
        $this->command->info('table objects for setting data seeded.');
    }

}
