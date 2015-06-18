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

        // seeding bank object
        $settingObjects = <<<SETTING
INSERT INTO `{$prefix}settings` (`setting_name`, `setting_value`, `object_id`, `object_type`, `status`, `created_at`, `updated_at`) VALUES
('enable_membership_id', 'false', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_coupon', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_coupon_widget', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_lucky_draw', 'false', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
('enable_lucky_draw_widget', 'true', '2', 'merchant', 'active', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
SETTING;

        $this->command->info('Seeding objects table for setting data...');
        DB::unprepared($settingObjects);
        $this->command->info('table objects for setting data seeded.');
    }

}
