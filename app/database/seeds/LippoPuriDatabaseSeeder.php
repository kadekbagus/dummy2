<?php

class LippoPuriDatabaseSeeder extends Seeder
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

        $this->call('RoleTableSeeder');
        $this->call('PermissionTableSeeder');
        $this->call('PermissionRoleTableSeeder');
        $this->call('UserTableSeeder');
        $this->call('MerchantDataSeeder');
        $this->call('CountryTableSeeder');
        $this->call('PersonalInterestTableSeeder');
        $this->call('CategoryTableSeeder');
        $this->call('SettingTableSeeder');

        $this->call('LippoPuriTenantSeeder');
        $this->call('LippoPuriBankSeeder');
        $this->call('LippoPuriWidgetSettingTableSeeder');
        $this->call('LippoPuriTenantSeeder2');
        $this->call('LippoPuriTenantSeeder3');
        $this->call('LippoPuriTenantSeeder4');
        $this->call('LippoPuriCSSeeder');
        // $this->call('LippoPuriCSSeeder2');

        DB::connection()->getPdo()->commit();
    }
}
