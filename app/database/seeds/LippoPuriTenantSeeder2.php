<?php
/**
 * Seeder for new tenants
 * the 'Lippo Mall Puri'.
 *
 */
class LippoPuriTenantSeeder2 extends Seeder
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
INSERT INTO `{$prefix}merchants` (`merchant_id`, `omid`, `orid`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `postal_code`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `end_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `contact_person_firstname`, `contact_person_lastname`, `contact_person_position`, `contact_person_phone`, `contact_person_phone2`, `contact_person_email`, `sector_of_activity`, `object_type`, `parent_id`, `is_mall`, `url`, `masterbox_number`, `slavebox_number`, `mobile_default_language`, `pos_language`, `ticket_header`, `ticket_footer`, `floor`, `unit`, `modified_by`, `created_at`, `updated_at`) VALUES
(116, '', '', 0, '', 'PAXI BARBERSHOP', 'For a no fuss, in and out as fast as you can kind of cut, we recommend PAXI. It’s a typical barbershop and specialises in more common men’s hairstyles. Available in most malls around town so pop in for a wash, trim, hair colouring, reflexology, or shave.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 730', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '221', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(117, '', '', 0, '', "CARL'S JR.", "Los Angeles, 1941. Young Carl N. Karcher and his wife, Margaret, make a leap of faith and borrow $311 on their Plymouth automobile, add $15 in savings and purchase a hot dog cart. One cart grows to four, and in less than five years, Carl's Drive-In Barbecue opens with hamburgers on the menu. The brand continues its growth with an emphasis on quality, service and cleanliness, pioneering concepts such as partial table service and self-serve beverage bars.", NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 720', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.carlsjr.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '12', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(118, '', '', 0, '', 'MAXX COFFEE', 'Maxx Coffee is a cafe. You can drink a cup of coffee and have some snacks here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '77', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(119, '', '', 0, '', 'VNC', 'Simple things make a big difference. Attention to detail that powerfully transforms a woman’s total image. VNC is a fashion store. You can buy woman''s outfit here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 267', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '37', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(120, '', '', 0, '', 'JAVA JAZZ COFFEE', 'Java Jazz Coffee is a cafe. You can drink a cup of coffee and have some snacks here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '72', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(121, '', '', 0, '', 'OSAKAMARU', 'Osakamaru is a Japanese Restaurant. You can have your meals here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 272', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '52', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(122, '', '', 0, '', 'WATCH ENGINE', 'Watch Engine is a watch store. You can buy any watches here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '38', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(123, '', '', 0, '', 'URBAN ICON', 'Urban Icon is an ultimate accessories store presenting various brands of watches and leather goods in Indonesia. Customers can find urban lifestyle brands in our store such as Fossil, Liebeskind Berlin, Diesel, DKNY, Marc by Marc Jacobs, Michael Kors, Karl Lagerfeld, Emporio Armani, Rip Curl, Melie Bianco, and Desigual. These variety of brands have different style that represent our customer''s preference, but all have the same values of delivering trendy high street style and top notch quality.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.urbanicon.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '16', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(124, '', '', 0, '', 'METAPHOR', 'Metaphor is a shoes store. You can buy any shoes here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '12', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(125, '', '', 0, '', 'BEST DENKI', 'By leveraging our strengths in business development Best Denki aims to be the one-stop retailer for a total home electronics lifestyle In the home appliance and consumer electronics retail industry Best Denki continues to expand into a range of business areas centered on our top-class domestic and international network of stores and after-sales services.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.bestdenki.ne.jp', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '66', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(126, '', '', 0, '', 'WARNA', 'Warna adalah toko yang menjual berbagai macam aksesoris lucu dan unik. Warna melayani pembelian barang seperti ikat rambut, gelang, cincin, kalung, dll. Melalui toko aksesoris ini, Anda dapat menambah koleksi pribadi untuk mempercantik diri. Andapun tak perlu khawatir akan kualitas yang diberikan Warna. Karena di Warna ini kami mengikuti alur lifestyle yang ada serta selalu ingin menjadikan suatu momen belanja aksesoris lebih fun dan mudah.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '30', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(127, '', '', 0, '', 'SPORTS STATION', 'Sport Station is a sportswear store. You can buy any sports outfit here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '35', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(128, '', '', 0, '', 'LORENZA', 'lorenza seeks to promote a quality lifestyle for discerning customer. We always aspired to develop comfortable, quality and trendy furniture to meet the needs of the people. Our focus is on interpreting the following principles-customer satisfaction, pure comfort and simple luxury, which have become the basis of lorenza''s name recognition within the industry and beyond for the last 15 years.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 717', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.lorenza.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '10', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(129, '', '', 0, '', 'JOHNNY ANDREAN', 'Johnny Andrean is a saloon, hair stylist and beauty center. You can take care of your beauty here.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'johnnyandrean.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '16', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(130, '', '', 0, '', 'PPP LASER CLINIC', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 283', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '5', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00');

-- Update Master Box Number (The Merchant Verification Number)
UPDATE `{$prefix}merchants` SET masterbox_number=merchant_id;
TENANT;

        $this->command->info('Seeding merchants table with lippo puri tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $categories = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_merchant_id`, `category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
(148, 13, 116, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(149, 7, 117, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(150, 7, 118, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(151, 6, 119, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(152, 7, 120, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(153, 7, 121, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(154, 12, 122, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(155, 15, 122, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(156, 7, 124, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(157, 5, 125, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(158, 6, 126, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(159, 6, 127, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(160, 11, 128, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(161, 13, 129, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(162, 9, 130, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
CAT;

        $this->command->info('Seeding category_merchant table with lippo puri tenants...');
        DB::unprepared($categories);
        $this->command->info('table category_merchant seeded.');

    }

}
