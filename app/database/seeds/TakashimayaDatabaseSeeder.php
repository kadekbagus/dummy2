<?php
class TakashimayaDatabaseSeeder extends Seeder
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
        $this->call('CountryTableSeeder');
        $this->call('LanguageSeeder'); // done
        $this->call('TakashimayaMerchantSeeder'); // done
        $this->call('TakashimayaLanguageSeeder'); // done
        $this->call('PersonalInterestTableSeeder');
        $this->call('TakashimayaCategorySeeder'); // done?
        $this->call('SettingTableSeeder');
        $this->call('TakashimayaTenantSeeder'); // done - cs?
        $this->call('TakashimayaBankSeeder'); // done - limited number of banks

        DB::connection()->getPdo()->commit();
    }
}
