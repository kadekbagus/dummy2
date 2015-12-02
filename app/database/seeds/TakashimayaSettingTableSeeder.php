<?php
/**
 * Seeder for Setting
 *
 */
class TakashimayaSettingTableSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Seeding settings table...');

        try {
            DB::table('settings')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        Setting::unguard();

        $record = [
            'setting_name'  => 'current_retailer',
            'setting_value' => TakashimayaMerchantSeeder::MALL_ID,
            'status'        => 'active'
        ];
        Setting::create($record);
        $this->command->info(sprintf('    Create record `current_retailer` set to %s.', TakashimayaMerchantSeeder::MALL_ID));
        $this->command->info('settings table seeded.');
    }
}
