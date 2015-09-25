<?php
/**
 * Seeder for new tenants
 * the 'Lippo Mall Puri'.
 *
 */
class LippoPuriCSSeeder2 extends Seeder
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
('', '', 0, '', 'CSO-{{NUMBER}}', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '', 0, '2015-08-21 16:20:00', '2015-08-21 16:20:00');

SET @tenant_id = last_insert_id();

-- Update Master Box Number (The Merchant Verification Number)
UPDATE `{$prefix}merchants` SET masterbox_number=merchant_id where merchant_id=@tenant_id;

SET @category_id = (SELECT category_id from {$prefix}categories where category_name='Customer Service' LIMIT 1);

-- Insert into category_merchants
INSERT INTO `{$prefix}category_merchant` (`category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
(@category_id, @tenant_id, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
TENANT;

        $this->command->info('Seeding CS merchants table with lippo puri tenants...');

        // Loop from 2 to 12
        for ($i=2; $i<=12; $i++) {
            $number = str_pad($i, 2, '0', STR_PAD_LEFT);

            $current_query = str_replace('{{NUMBER}}', $number, $tenants);

            DB::unprepared($current_query);
        }
        $this->command->info('CS merchants table seeded.');
    }

}
