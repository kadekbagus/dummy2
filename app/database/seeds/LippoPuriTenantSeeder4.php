<?php
use Orbit\Database\ObjectID;
/**
 * Seeder for new tenants
 * the 'Lippo Mall Puri'.
 *
 */
class LippoPuriTenantSeeder4 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $prefix = DB::getTablePrefix();
        $generatedIDs = [
            'tenant' => [],
            'category_merchant' => []
        ];
        $pdo = DB::connection()->getPdo();
        $mall_id = $pdo->quote(MerchantDataSeeder::MALL_ID);

        $generateID = function ($group, $sequence) use (&$generatedIDs, $pdo)
        {
                $id = $this->generateID();
                $id = $pdo->quote($id);
                $generatedIDs[$group] [$sequence] = $id;
                return $id;
        };

        $tenants = <<<TENANT
INSERT INTO `{$prefix}merchants` (`merchant_id`, `omid`, `orid`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `postal_code`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `end_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `contact_person_firstname`, `contact_person_lastname`, `contact_person_position`, `contact_person_phone`, `contact_person_phone2`, `contact_person_email`, `sector_of_activity`, `object_type`, `parent_id`, `is_mall`, `url`, `masterbox_number`, `slavebox_number`, `mobile_default_language`, `pos_language`, `ticket_header`, `ticket_footer`, `floor`, `unit`, `modified_by`, `created_at`, `updated_at`) VALUES
({$generateID('tenant', 145)}, '', '', 0, '', 'The Handcrafter', 'The Handcrafter is a felt wool craft supplies store in Jakarta, Indonesia. We are also Hamanaka representative in Indonesia. We are here to let you all say hello to a whole new world of craft: FELT WOOL CRAFT~ or needle felting. Hardly heard in Indonesia but this craft has gained popularity in other countries! So, we want to share this addictive, unique, and fun craft to our handcrafters in Indonesia! ', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 393', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'thehandcrafter.com', {$generateID('tenant', 145)}, NULL, NULL, NULL, NULL, NULL, 'LG', '22F', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 146)}, '', '', 0, '', 'Smailing Tour', 'Smailing Tour provides the highest quality at the best price. Training our staff to exceed international standards is the key to our success. We continuously improve our personal service and upgrade our technologies, ensuring that you will continue to be satisfied beyond your expectations.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 700', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'www.smailingtour.co.id', {$generateID('tenant', 146)}, NULL, NULL, NULL, NULL, NULL, '', '21A', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 147)}, '', '', 0, '', 'Esthetic Rosereve', 'Esthetic Rosereve is a beauty shop located in Plaza Semanggi, Supermal Karawaci and Pluit Village. One other outlets with brand Esthetic Melrose located in Ciputra Mal. Both of which have been established since 2005 under PT Kirei Paras Santika, part of Fortune Star Group which engages in medical therapeutic devices, dietary supplements and cosmetics since 2002 with the motto ‘for people happiness’.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'estheticrosereve.com/id', {$generateID('tenant', 147)}, NULL, NULL, NULL, NULL, NULL, '', '22H', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 148)}, '', '', 0, '', 'PAPAJO eatery', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 724', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', '', {$generateID('tenant', 148)}, NULL, NULL, NULL, NULL, NULL, 'G', '97', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 149)}, '', '', 0, '', 'H & M', "H&M's design team creates sustainable fashion for all, always at the best price. The collections include everything from dazzling party collections to quintessential basics and functional sportswear – for women, men, teenagers and children, and for every season or occasion. In addition to clothes, shoes, bags, jewellery, make up and underwear there is also H&M Home – fashionable interiors for children and adults.", NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'www.hm.com/id/', {$generateID('tenant', 149)}, NULL, NULL, NULL, NULL, NULL, '', '', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 150)}, '', '', 0, '', 'Bariuma Ramen', 'One of the best Ramen chain in Japan!', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 752', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'www.facebook.com/BariumaRamenID', {$generateID('tenant', 150)}, NULL, NULL, NULL, NULL, NULL, 'L1', '98', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 151)}, '', '', 0, '', 'KOI Café', "The dream of travelling around the world has inspired the founder to bring teas with her on every overseas trip to places like Hawaii, Tokyo, and Australia as away from home made her craving for authentic Home-Made Taiwanese Tea. Little by little, the idea of building and sharing the Taiwan's iconic bubble tea to the rest of the world has emerged. Hoping soon in the future, tea lovers would be impressed with KOI's joy-filled beverages.", NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'www.koicafe.com/id/', {$generateID('tenant', 151)}, NULL, NULL, NULL, NULL, NULL, '', '92', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 152)}, '', '', 0, '', 'Manzone Concept', 'Manzone merupakan retail store dari perusahaan PT Mega Perintis yang sudah bergerak di bidang fashion sejak tahun 1999. Hingga saat ini Manzone telah memiliki kurang lebih dari 250 outlet. Sebagian besar berada di daerah Jakarta dan sekitarnya namun kami juga memiliki outlet di kota Malang, Makassar, Manado, Surabaya, Palembang, dan Banjarmasin.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 22 582 729', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'www.manzone-store.com/', {$generateID('tenant', 152)}, NULL, NULL, NULL, NULL, NULL, 'L1', '33', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00'),
({$generateID('tenant', 153)}, '', '', 0, '', 'Revel', 'Revel Cake sendiri berdiri pada tahun 2012, Revel Cake memiliki beberapa produk andalan diantaranya adalah lapis legit, kue pia, mochi, eclairs, ice cream nitrogen, dan burger. Semua produk yang kami olah menggunakan bahan-bahan yang berkualitas. Tanpa menggunakan bahan pengawet, pewarna, pemanis buatan dan perenyah makanan. Hal ini membuat produk kami sangat aman dikonsumsi.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 293', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tenant', {$mall_id}, 'no', 'revelcake.com/', {$generateID('tenant', 153)}, NULL, NULL, NULL, NULL, NULL, 'L2', '58', 0, '2015-08-12 10:56:00', '2015-08-12 10:56:00')
;
TENANT;

        $this->command->info('Seeding merchants table with lippo puri tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $findCategoryId = function  ($skip) use ($pdo) {
            $id = DB::table('categories')->skip($skip - 1)->first()->category_id;
            return $pdo->quote($id);
        };

        $categories = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_merchant_id`, `category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
({$generateID('category_merchant', 179)}, {$findCategoryId(2)}, {$generatedIDs['tenant'][145]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 180)}, {$findCategoryId(8)}, {$generatedIDs['tenant'][145]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 181)}, {$findCategoryId(10)}, {$generatedIDs['tenant'][145]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 182)}, {$findCategoryId(13)}, {$generatedIDs['tenant'][146]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 183)}, {$findCategoryId(9)}, {$generatedIDs['tenant'][147]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 184)}, {$findCategoryId(1)}, {$generatedIDs['tenant'][147]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 185)}, {$findCategoryId(7)}, {$generatedIDs['tenant'][148]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 186)}, {$findCategoryId(6)}, {$generatedIDs['tenant'][149]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 187)}, {$findCategoryId(7)}, {$generatedIDs['tenant'][150]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 188)}, {$findCategoryId(7)}, {$generatedIDs['tenant'][151]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 189)}, {$findCategoryId(6)}, {$generatedIDs['tenant'][152]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID('category_merchant', 190)}, {$findCategoryId(7)}, {$generatedIDs['tenant'][153]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00')
;
CAT;

        $this->command->info('Seeding category_merchant table with lippo puri tenants id 145 to 153...');
        DB::unprepared($categories);
        $this->command->info('table category_merchant seeded.');

    }

    private function generateID()
    {
        return ObjectID::make();
    }

}
