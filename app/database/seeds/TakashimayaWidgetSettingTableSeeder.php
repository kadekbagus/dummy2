<?php
/**
 * Seeder for Setting linked to merchant_id 2
 * the 'Takashimaya Shopping Center'.
 *
 */
class TakashimayaWidgetSettingTableSeeder extends Seeder
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
        $settingObjects = <<<SETTING
DELETE FROM {$prefix}settings where (setting_name = 'enable_coupon' OR setting_name = 'enable_coupon_widget'
OR setting_name='enable_lucky_draw' OR setting_name='enable_lucky_draw_widget') AND object_id=2 AND object_type='merchant';

INSERT INTO `{$prefix}settings` (`setting_name`, `setting_value`, `object_id`, `object_type`, `status`, `created_at`, `updated_at`) VALUES
('enable_coupon', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_coupon_widget', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_lucky_draw', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_lucky_draw_widget', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
SETTING;

        $this->command->info('Seeding objects table for setting data...');
        DB::unprepared($settingObjects);
        $this->command->info('table objects for setting data seeded.');
    }

}
