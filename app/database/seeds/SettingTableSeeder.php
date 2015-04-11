<?php
/**
 * Seeder for Setting
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class SettingTableSeeder extends Seeder
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
            'setting_value' => '2',
            'status'        => 'active'
        ];
        Setting::create($record);
        $this->command->info(sprintf('    Create record `current_retailer` set to %s.', 2));
        $this->command->info('settings table seeded.');
    }
}
