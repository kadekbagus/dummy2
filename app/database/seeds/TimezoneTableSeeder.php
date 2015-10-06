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

        $timezones = [
            ['timezone_offset' => "GMT-12:00", 'timezone_name' => "International Date Line West"],
            ['timezone_offset' => "GMT-11:00", 'timezone_name' => "Midway Island / Samoa"],
            ['timezone_offset' => "GMT-10:00", 'timezone_name' => "Hawaii"],
            ['timezone_offset' => "GMT-09:30", 'timezone_name' => "Marquesas Islands"],
            ['timezone_offset' => "GMT-09:00", 'timezone_name' => "Alaska"],
            ['timezone_offset' => "GMT-08:00", 'timezone_name' => "Pitcairn"],
            ['timezone_offset' => "GMT-08:00", 'timezone_name' => "Pacific Time (US & Canada)"],
            ['timezone_offset' => "GMT-08:00", 'timezone_name' => "Tijuana / Baja California"],
            ['timezone_offset' => "GMT-07:00", 'timezone_name' => "Chihuahua / La Paz / Mazatlan - New"],
            ['timezone_offset' => "GMT-07:00", 'timezone_name' => "Mountain Time (US & Canada)"],
            ['timezone_offset' => "GMT-07:00", 'timezone_name' => "Arizona"],
            ['timezone_offset' => "GMT-06:00", 'timezone_name' => "Central Time (US & Canada)"],
            ['timezone_offset' => "GMT-06:00", 'timezone_name' => "Central America"],
            ['timezone_offset' => "GMT-06:00", 'timezone_name' => "Guadalajara / Mexico City / Monterrey - New"],
            ['timezone_offset' => "GMT-06:00", 'timezone_name' => "Saskatchewan"],
            ['timezone_offset' => "GMT-05:00", 'timezone_name' => "Bogota / Lima / Quito / Rio Branco"],
            ['timezone_offset' => "GMT-05:00", 'timezone_name' => "Indiana (East)"],
            ['timezone_offset' => "GMT-05:00", 'timezone_name' => "Eastern Time (US & Canada)"],
            ['timezone_offset' => "GMT-04:00", 'timezone_name' => "Caracas"],
            ['timezone_offset' => "GMT-04:00", 'timezone_name' => "Georgetown"],
            ['timezone_offset' => "GMT-04:00", 'timezone_name' => "Atlantic Time (Canada)"],
            ['timezone_offset' => "GMT-04:00", 'timezone_name' => "La Paz"],
            ['timezone_offset' => "GMT-04:00", 'timezone_name' => "Manaus"],
            ['timezone_offset' => "GMT-04:00", 'timezone_name' => "Santiago"],
            ['timezone_offset' => "GMT-03:30", 'timezone_name' => "Newfoundland"],
            ['timezone_offset' => "GMT-03:00", 'timezone_name' => "Buenos Aires"],
            ['timezone_offset' => "GMT-03:00", 'timezone_name' => "Greenland"],
            ['timezone_offset' => "GMT-03:00", 'timezone_name' => "Montevideo"],
            ['timezone_offset' => "GMT-03:00", 'timezone_name' => "Brasilia"],
            ['timezone_offset' => "GMT-02:00", 'timezone_name' => "Mid-Atlantic"],
            ['timezone_offset' => "GMT-01:00", 'timezone_name' => "Azores"],
            ['timezone_offset' => "GMT-01:00", 'timezone_name' => "Cape Verde Is."],
            ['timezone_offset' => "GMT+00:00", 'timezone_name' => "Casablanca"],
            ['timezone_offset' => "GMT+00:00", 'timezone_name' => "UTC"],
            ['timezone_offset' => "GMT+00:00", 'timezone_name' => "Dublin / Edinburgh / Lisbon / London"],
            ['timezone_offset' => "GMT+01:00", 'timezone_name' => "West Central Africa"],
            ['timezone_offset' => "GMT+01:00", 'timezone_name' => "Windhoek"],
            ['timezone_offset' => "GMT+01:00", 'timezone_name' => "Belgrade / Bratislava / Budapest / Ljubljana / Prague"],
            ['timezone_offset' => "GMT+01:00", 'timezone_name' => "Amsterdam / Berlin / Bern / Rome / Stockholm / Vienna"],
            ['timezone_offset' => "GMT+01:00", 'timezone_name' => "Brussels / Copenhagen / Madrid / Paris"],
            ['timezone_offset' => "GMT+01:00", 'timezone_name' => "Sarajevo / Skopje / Warsaw / Zagreb"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Cairo"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Harare / Pretoria"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Amman"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Beirut"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Jerusalem"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Athens / Bucharest / Istanbul"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Helsinki / Kyiv / Riga / Sofia / Tallinn / Vilnius"],
            ['timezone_offset' => "GMT+02:00", 'timezone_name' => "Minsk"],
            ['timezone_offset' => "GMT+03:00", 'timezone_name' => "Nairobi"],
            ['timezone_offset' => "GMT+03:00", 'timezone_name' => "Baghdad"],
            ['timezone_offset' => "GMT+03:00", 'timezone_name' => "Kuwait / Riyadh"],
            ['timezone_offset' => "GMT+03:00", 'timezone_name' => "Moscow / St. Petersburg / Volgograd"],
            ['timezone_offset' => "GMT+03:30", 'timezone_name' => "Tehran"],
            ['timezone_offset' => "GMT+04:00", 'timezone_name' => "Baku"],
            ['timezone_offset' => "GMT+04:00", 'timezone_name' => "Abu Dhabi / Muscat"],
            ['timezone_offset' => "GMT+04:00", 'timezone_name' => "Tbilisi"],
            ['timezone_offset' => "GMT+04:00", 'timezone_name' => "Yerevan"],
            ['timezone_offset' => "GMT+04:30", 'timezone_name' => "Kabul"],
            ['timezone_offset' => "GMT+05:00", 'timezone_name' => "Islamabad / Karachi"],
            ['timezone_offset' => "GMT+05:00", 'timezone_name' => "Tashkent"],
            ['timezone_offset' => "GMT+05:00", 'timezone_name' => "Ekaterinburg"],
            ['timezone_offset' => "GMT+05:30", 'timezone_name' => "Sri Jayawardenepura"],
            ['timezone_offset' => "GMT+05:30", 'timezone_name' => "Chennai / Kolkata / Mumbai / New Delhi"],
            ['timezone_offset' => "GMT+05:45", 'timezone_name' => "Kathmandu"],
            ['timezone_offset' => "GMT+06:00", 'timezone_name' => "Astana / Dhaka"],
            ['timezone_offset' => "GMT+06:00", 'timezone_name' => "Almaty / Novosibirsk"],
            ['timezone_offset' => "GMT+06:30", 'timezone_name' => "Yangon (Rangoon)"],
            ['timezone_offset' => "GMT+07:00", 'timezone_name' => "Bangkok / Hanoi / Jakarta"],
            ['timezone_offset' => "GMT+07:00", 'timezone_name' => "Krasnoyarsk"],
            ['timezone_offset' => "GMT+08:00", 'timezone_name' => "Beijing / Chongqing / Hong Kong / Urumqi"],
            ['timezone_offset' => "GMT+08:00", 'timezone_name' => "Irkutsk / Ulaan Bataar"],
            ['timezone_offset' => "GMT+08:00", 'timezone_name' => "Kuala Lumpur / Singapore"],
            ['timezone_offset' => "GMT+08:00", 'timezone_name' => "Taipei"],
            ['timezone_offset' => "GMT+08:00", 'timezone_name' => "Perth"],
            ['timezone_offset' => "GMT+09:00", 'timezone_name' => "Seoul"],
            ['timezone_offset' => "GMT+09:00", 'timezone_name' => "Osaka / Sapporo / Tokyo"],
            ['timezone_offset' => "GMT+09:00", 'timezone_name' => "Yakutsk"],
            ['timezone_offset' => "GMT+09:30", 'timezone_name' => "Adelaide"],
            ['timezone_offset' => "GMT+09:30", 'timezone_name' => "Darwin"],
            ['timezone_offset' => "GMT+10:00", 'timezone_name' => "Vladivostok"],
            ['timezone_offset' => "GMT+10:00", 'timezone_name' => "Brisbane"],
            ['timezone_offset' => "GMT+10:00", 'timezone_name' => "Hobart"],
            ['timezone_offset' => "GMT+10:00", 'timezone_name' => "Canberra / Melbourne / Sydney"],
            ['timezone_offset' => "GMT+10:00", 'timezone_name' => "Guam / Port Moresby"],
            ['timezone_offset' => "GMT+10:30", 'timezone_name' => "Lord Howe"],
            ['timezone_offset' => "GMT+11:00", 'timezone_name' => "Magadan / Solomon Is. / New Caledonia"],
            ['timezone_offset' => "GMT+11:30", 'timezone_name' => "Norfolk Islands"],
            ['timezone_offset' => "GMT+12:00", 'timezone_name' => "Auckland / Wellington"],
            ['timezone_offset' => "GMT+12:00", 'timezone_name' => "Fiji / Kamchatka / Marshall Is."],
            ['timezone_offset' => "GMT+13:00", 'timezone_name' => "Nuku'alof"]
        ];

        Timezone::unguard();
        foreach ($timezones as $i=>$timezone) {
            $this->command->info(sprintf('    Create record for timezone `%s %s`.', $timezone['timezone_offset'], $timezone['timezone_name']));
            $timezone['timezone_order'] = $i;
            Timezone::create($timezone);
        }

        $this->command->info('timezones table seeded.');
    }
}
