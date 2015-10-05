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
        try {
            $this->doRun();
        } catch (Exception $e) {
            $this->command->info($e);
        };
    }
    public function doRun()
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
        $this->call('TakashimayaSettingTableSeeder');
        $this->call('TakashimayaTenantSeeder'); // done - cs?
        $this->call('TakashimayaBankSeeder'); // done - limited number of banks

        DB::connection()->getPdo()->commit();
    }
}
