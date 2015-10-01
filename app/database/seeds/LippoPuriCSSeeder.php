<?php
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

        $tenants = <<<TENANT
INSERT INTO `{$prefix}merchants` (`omid`, `orid`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `postal_code`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `end_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `contact_person_firstname`, `contact_person_lastname`, `contact_person_position`, `contact_person_phone`, `contact_person_phone2`, `contact_person_email`, `sector_of_activity`, `object_type`, `parent_id`, `is_mall`, `url`, `masterbox_number`, `slavebox_number`, `mobile_default_language`, `pos_language`, `ticket_header`, `ticket_footer`, `floor`, `unit`, `modified_by`, `created_at`, `updated_at`) VALUES
('', '', 0, '', 'Customer Service Counter', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '', 0, '2015-08-21 16:20:00', '2015-08-21 16:20:00');

SET @tenant_id = last_insert_id();

-- Update Master Box Number (The Merchant Verification Number)
UPDATE `{$prefix}merchants` SET masterbox_number=merchant_id where merchant_id=@tenant_id;
TENANT;

        $this->command->info('Seeding merchants table with lippo puri tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $categories = <<<CATEGORY
INSERT INTO `{$prefix}categories` (`merchant_id`, `category_name`, `category_level`, `category_order`, `status`, `created_at`, `updated_at`) VALUES
('2', 'Customer Service', 1, 0, 'active', '2015-08-21 16:20:00', '2015-08-21 16:20:00');

SET @category_id = last_insert_id();
CATEGORY;

        $this->command->info('Seeding categories table with lippo puri tenants...');
        DB::unprepared($categories);
        $this->command->info('categories table seeded.');

        $categoryMerchant = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
(@category_id, @tenant_id, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
CAT;

        $this->command->info('Seeding category_merchant table with lippo puri tenants...');
        DB::unprepared($categoryMerchant);
        $this->command->info('table category_merchant seeded.');

    }

}
