<?php
/**
 * Seeder for new tenants
 * the 'Lippo Mall Puri'.
 *
 */
class LippoPuriTenantSeeder3 extends Seeder
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
(131, '', '', 0, '', 'GUARDIAN', 'Guardian is one of business units under Hero Group, engaged in a modern pharmacy in the form of health and beauty store. Guardian started its business in Indonesia since 1990. As a pioneer in the industry of health and beauty, Guardian is establish to answer the need of consumers toward a closer, easy-to-reach drugstore.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 741 / 2', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.guardianindonesia.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '97', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(132, '', '', 0, '', 'Baskin Robbins', 'Baskin Robbins Ice Cream has many tempting flavors. Prospective buyers are also given the opportunity to taste the first taste of ice cream before ordering. Ice Cream choices ranging from single scoop, scoop up a triple double scoop. Here buyers are free to combine flavors of ice cream into cones, or Freshpack Cup.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'baskinrobbins.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', 'K08', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(133, '', '', 0, '', 'Pigeon', 'Hanya Pigeon yang terpercaya mendampingi mulai dari bayi, anak, remaja hingga para ibu. Pigeon menjadikan 3S Philosophy-nya (Study, Safety, Satisfaction) sebagai dasar dalam pengembangan produk yang berkualitas dan memberikan rasa nyaman.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 746', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.pigeon.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '57', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(134, '', '', 0, '', 'Starbucks', "We make sure everything we do honours that connection – from our commitment to the highest quality coffee in the world, to the way we engage with our customers and communities to do business responsibly. From our beginnings as a single store nearly forty years ago, in every place that we’ve been, and every place that we touch, we've tried to make it a little better than we found it.", NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 250', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.starbucks.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '26', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(135, '', '', 0, '', 'Eric Kayser', 'French Artisan Bakers - Respectful of the French artisan baking tradition, Eric Kayser and his bakers, with their creative spirits and the best quality ingredients, make innovative recipes and partner up creative bakers from all over the world.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 723', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'erickayser.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '93', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(136, '', '', 0, '', 'WATCH ZONE', 'Established in 2009, this multi-brand retail store houses some of the world’s most popular lifestyle timepieces. The first WatchZone store opened at Senayan City on 22 July 2009, and the second one at Grand Indonesia in January 2010. In April 2012, WatchZone did a “facelift” on their design concept, which transform its young, dynamic and energetic looks into a more chick and elegant style. WatchZone offers its customer various renown brand such as Guess watches, Gc watches, Victorinox Swiss Army, Swarovski, and Nautica watches . In the end of 2013, WatchZone add its collection with two iconic brands from the UK, Superdry watches and Rotary.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.facebook.com/pages/Watchzone-Indonesia/430302523719806', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '57', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(137, '', '', 0, '', 'Traffic Room', 'Existed since in 2008 with the name of TRAFFIC, however in 2010 the name itself officially changed to be TRAFFIC ROOM with the first store is located at Pondok Indah Mall. Available products from TRAFFIC ROOM are Unisex T-shirt, accessories, caps, and also bags. Vintage is the new me its TRAFFIC ROOM tagline because the fact that the concept of TRAFFIC ROOM itself which offering classic, comfortable, long-lasting style which can be seen from the vintage designs such as classic automotive, legendary bands, legendary stars, vintage photography, England & american vintage stuff. All of that can be obtained in affordable prices. TRAFFIC ROOM as a local brand that is not inferior to the quality of international brand, all of that can be seen from the pattern cutting, fabric material which made from best quality and designed with results which are unique and attractive.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.trafficrooms.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '21', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(138, '', '', 0, '', 'Little Sound', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '63', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(139, '', '', 0, '', 'Black N Glam', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '28', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(140, '', '', 0, '', 'BALE LOMBOK', 'Kami adalah spesialis ayam taliwang dan betutu yang tentu saja juga menyediakan makanan khas Indonesia yang lezat lainnya.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 274', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.facebook.com/balelombokresto', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '51', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(141, '', '', 0, '', 'Born Ga', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 356', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'bornga.co.kr/bonga/index.asp', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '53', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(142, '', '', 0, '', 'Stonart Gallery', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 708', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '9', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(143, '', '', 0, '', 'Aree', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '60', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
(144, '', '', 0, '', 'EQ', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 281', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '61', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00')
;

-- Update Master Box Number (The Merchant Verification Number)
UPDATE `{$prefix}merchants` SET masterbox_number=merchant_id where merchant_id >= 131;
TENANT;

        $this->command->info('Seeding merchants table with lippo puri tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $categories = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
(1, 131, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(9, 131, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 132, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3, 133, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 134, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 135, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(12, 136, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(15, 136, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(6, 137, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(5, 138, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(6, 139, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 140, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 141, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(11, 142, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 143, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 144, '0000-00-00 00:00:00', '0000-00-00 00:00:00')
;
CAT;

        $this->command->info('Seeding category_merchant table with lippo puri tenants id 131 to 144...');
        DB::unprepared($categories);
        $this->command->info('table category_merchant seeded.');

    }

}
