<?php

class LippoMallDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Eloquent::unguard();

        DB::connection()->getPdo()->beginTransaction();

        $this->call('LippoPuriBankSeeder');
        $this->call('LippoPuriTenantSeeder');
        $this->call('LippoPuriTenantSeeder2');
        $this->call('LippoPuriWidgetSettingTableSeeder');

        DB::connection()->getPdo()->commit();
    }
}
