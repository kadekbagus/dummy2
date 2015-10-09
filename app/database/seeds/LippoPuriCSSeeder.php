<?php
use Orbit\Database\ObjectID;
/**
 * Seeder for new tenants
 * the 'Lippo Mall Puri'.
 *
 */
class LippoPuriCSSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $prefix = DB::getTablePrefix();

        $pdo = DB::connection()->getPdo();
        $mall_id = $pdo->quote(MerchantDataSeeder::MALL_ID);

        $generateID = function ($group, $sequence) use (&$generatedIDs, $pdo)
        {
                $id = $this->generateID();
                $id = $pdo->quote($id);
                $generatedIDs[$group] [$sequence] = $id;
                return $id;
        };

        $tenant_id = $pdo->quote($this->generateID());

        $tenants = <<<TENANT
INSERT INTO `{$prefix}merchants` (`merchant_id`, `omid`, `orid`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `postal_code`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `end_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `contact_person_firstname`, `contact_person_lastname`, `contact_person_position`, `contact_person_phone`, `contact_person_phone2`, `contact_person_email`, `sector_of_activity`, `object_type`, `parent_id`, `is_mall`, `url`, `masterbox_number`, `slavebox_number`, `mobile_default_language`, `pos_language`, `ticket_header`, `ticket_footer`, `floor`, `unit`, `modified_by`, `created_at`, `updated_at`) VALUES
({$tenant_id}, '', '', 0, '', 'Customer Service Counter', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', '', {$tenant_id}, NULL, NULL, NULL, NULL, NULL, 'LG', '', 0, '2015-08-21 16:20:00', '2015-08-21 16:20:00');
TENANT;

        $this->command->info('Seeding merchants table with lippo puri tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $category_id = $pdo->quote($this->generateID());
        $categories = <<<CATEGORY
INSERT INTO `{$prefix}categories` (`category_id`, `merchant_id`, `category_name`, `category_level`, `category_order`, `status`, `created_at`, `updated_at`) VALUES
({$category_id}, {$mall_id}, 'Customer Service', 1, 0, 'active', '2015-08-21 16:20:00', '2015-08-21 16:20:00');
CATEGORY;

        $this->command->info('Seeding categories table with lippo puri tenants...');
        DB::unprepared($categories);
        $this->command->info('categories table seeded.');

        $categoryMerchant = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_merchant_id`, `category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
({$generateID('category_merchant', 191)}, {$category_id}, {$tenant_id}, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
CAT;

        $this->command->info('Seeding category_merchant table with lippo puri tenants...');
        DB::unprepared($categoryMerchant);
        $this->command->info('table category_merchant seeded.');

    }

    private function generateID()
    {
        return ObjectID::make();
    }

}
