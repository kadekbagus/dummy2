<?php
/**
 * Seeder for Timezone
 *
 * @author Tian <tian@dominopos.com>
 */
class TimezoneTableSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Seeding timezones table...');

        try {
            DB::table('timezones')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }


        $timezones = [];
        $timezone_identifiers = DateTimeZone::listIdentifiers();
        foreach($timezone_identifiers as $i=>$t) {
            $timezones[] = ['timezone_name' => $t, 'timezone_order' => '0'];
        }

        Timezone::unguard();
        foreach ($timezones as $i=>$timezone) {
            $this->command->info(sprintf('    Create record for timezone `%s`.', $timezone['timezone_name']));
            Timezone::create($timezone);
        }

        $this->command->info('timezones table seeded.');
    }
}
