<?php
/**
 * Seeder for Category, categories are linked to merchant_id 3
 * the 'Lippo Mall Puri'.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class LippoPuriTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $prefix = DB::getTablePrefix();

        $init = <<<INIT
TRUNCATE TABLE {$prefix}category_merchant;
ALTER TABLE {$prefix}category_merchant AUTO_INCREMENT=1;

DELETE FROM {$prefix}merchants WHERE merchant_id >= 3;
ALTER TABLE {$prefix}merchants AUTO_INCREMENT=3;

TRUNCATE TABLE {$prefix}lucky_draws;
ALTER TABLE {$prefix}lucky_draws AUTO_INCREMENT=2;

TRUNCATE TABLE {$prefix}lucky_numbers;
ALTER TABLE {$prefix}lucky_numbers AUTO_INCREMENT=1;
INIT;
        DB::unprepared($init);

        $tenants = <<<TENANT
INSERT INTO `{$prefix}merchants` (`merchant_id`, `omid`, `orid`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `postal_code`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `end_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `contact_person_firstname`, `contact_person_lastname`, `contact_person_position`, `contact_person_phone`, `contact_person_phone2`, `contact_person_email`, `sector_of_activity`, `object_type`, `parent_id`, `is_mall`, `url`, `masterbox_number`, `slavebox_number`, `mobile_default_language`, `pos_language`, `ticket_header`, `ticket_footer`, `floor`, `unit`, `modified_by`, `created_at`, `updated_at`) VALUES
(3, '', '', 0, '', 'THE BODY SHOP', 'The Body Shop adalah produsen produk perawatan tubuh dan kecantikan. Kami percaya kecantikan sejati berasal dari hati. Bagi kami, kecantikan jauh lebih dari sekedar wajah cantik.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021-29 111 072', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.thebodyshop.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '62', 0, '2015-04-11 04:21:25', '2015-04-11 04:21:25'),
(4, '', '', 0, '', 'YVES ROCHER', 'Yves Rocher is reinventing beauty with a genuine compassion for nature and women. He is the creator of Botanical Beauty – a vision of beauty that now attracts 30 million women around the world.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021-29 111 073', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.yvesrocher.ca', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '63', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(5, '', '', 0, '', 'BEYOND', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 318', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '66', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(6, '', '', 0, '', 'ERHA APOTHECARY', 'erha senantiasa menyempurnakan layanan dan perawatannya sehingga dapat memberikan terapi yang komprehensif untuk menjawab masalah kulit baik untuk perempuan maupun laki-laki di segala usia, mulai dari bayi, anak hingga lanjut usia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 039', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.erha.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '67', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(7, '', '', 0, '', 'CENTURY HEALTH CARE', 'Wellness adalah tubuh yang fit dan jiwa yang bahagia. Wellness adalah kesehatan yang utuh. Manusia yang sehat secara utuh adalah mata air Century untuk terus maju. Kesehatan utuh Anda adalah komitmen kami.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 076', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.century-pharma.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '69', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(8, '', '', 0, '', 'KENNY ROGERS ROASTERS', 'To be your home away from home, a casual dining restaurant that offers friendly service in a comfortable setting.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 033', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '70', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(9, '', '', 0, '', 'RIUNG SUNDA', 'Nestled in many acclaimed icons, Riung Sunda is one of the true place to go and reminisce the warm touch of Indonesian hospitality. With a warm interior decor, and admirable service Riung Sunda gives patron an enjoyable dining experience from the ease of selection, and wide array of Indonesian dish. With all ingredients served fresh, it’s not hard to imagine why this place is always packed with loyal guests.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 074', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '71', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(10, '', '', 0, '', 'STREET FOOD FESTIVAL', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 078', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '72', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(11, '', '', 0, '', 'SHIHLIN', 'Shihlin Taiwan Street Snacks ® is everybody''s favourite Taiwanese food chain featuring popular snacks from the alleys of Taiwan''s night markets.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 059', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.shihlinsnacks.com.tw/id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '73A', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(12, '', '', 0, '', 'PRESOTEA', 'Different from other tea brewed in a bulk bucket, Presotea insists to brew the tea by using the espresso-type machine to keep the flavor and sweetness of tea. Presotea also develops a wide range of tea menu to fulfill all kinds of demand. Completely overthrowing conventional tea-brewing method, drinking freshly brewed tea makes you healthier.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 035', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.presotea.com/eng/pro.asp', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '73B', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(13, '', '', 0, '', 'BREADLIFE', 'BreadLife is an open kitchen concept bakery store. It allows you to see our breads are freshly baked daily.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 097', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.breadlifebakery.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '75', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(14, '', '', 0, '', 'STOP ''N'' GO', 'Stop''N''Go memiliki keahlian khusus di bidang jasa reparasi sepatu, reparasi tas, duplikat kunci dan penjualan berbagai macam produk perawatan sepatu.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 086', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.stopngo.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '76A', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(15, '', '', 0, '', 'BONVIVO LAUNDRY', 'Bonvivo’s Laundry cleaning process guarantees that your clothes look good, are odorless and you can rest assured that no harmful chemicals remain in your freshly cleaned fabrics. Our Good Services is our goals.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 109', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'bonvivoclean.wix.com/mywebcard', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '76B', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(16, '', '', 0, '', 'HYPERMART', 'Perjalanan Hypermart merintis langkahnya di Indonesia tak bisa dikatakan singkat. Mulai beroperasi pada 2004, Hypermart yang kala itu hadir sebagai peritel paling bungsu, mengejar ketertinggalannya untuk menunjukkan kepada publik: Inilah peritel asli Indonesia yang lahir dari Bumi Pertiwi dan mampu bersaing dengan peritel asing. - See more at: http://www.hypermart.co.id/id/tentang-hypermart/tentang/10-tentang-hypermart#sthash.xh5CG7RQ.dpuf', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 150', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.hypermart.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '78', 0, '2015-04-11 04:21:26', '2015-04-11 04:21:26'),
(17, '', '', 0, '', 'SERUPUT', 'Restoran yang bergerak dibawah Takigawa Group ini menyajikan hidangan khas Indonesia dan pada khususnya adalah hidangan yang berasal dari ibu kota, Jakarta.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 315', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '79', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(18, '', '', 0, '', 'DONBURI ICHIYA', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 006', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '80', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(19, '', '', 0, '', 'GNC', 'GNC sets the standard in the nutritional supplement industry by demanding truth in labeling, ingredient safety and product potency, all while remaining on the cutting-edge of nutritional science.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.gnc.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '81A', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(20, '', '', 0, '', 'SOLARIA', 'Resto keluarga yg selalu menyajikan makanan segar', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 096', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '81B', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(21, '', '', 0, '', 'LOTTERIA', 'Lotteria''s corporate values are dedicated to creating a new dietary life for the people and delivering customer satisfaction', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 004', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.lotteria.com/eng/main.asp', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '82', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(22, '', '', 0, '', 'YA KUN KAYA TOAST', 'To establish Ya Kun as a household name in Singapore and Asia, offering delectable kaya toast and other complementary traditional food and beverages to one and all.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 005', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.yakun.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '83', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(23, '', '', 0, '', 'LOCK & LOCK', 'Leading kitchenware company in the world. Being together with various culture. Thinking of environment and human. Better idea, better life.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 083', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.locknlockindonesia.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '85', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(24, '', '', 0, '', 'FIT PLUS', 'Provides fitness equipments with quality, innovative and valuable at the affordable price.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.fitplus-store.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '87', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(25, '', '', 0, '', 'PERFECT HEALTH', 'Menciptakan gaya hidup yang sehat di setiap hunian melalui peralatan kesehatan yang inovatif dengan para ahli di bidang.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 320', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.perfecthealth.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '88', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(26, '', '', 0, '', 'ST.JAMES', 'Hankook, established in 1943, introduces Hankook Saint James, its finest range of high quality Chinaware. With its high quality and sophisticated designs, St. James is a product of a unique breakthrough in the art of Fine Bone China manufacturing in Korea as well as Super Bone in Indonesia', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 197', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.saint-james.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '90', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(27, '', '', 0, '', 'KALCARE', 'KALCare adalah portal klinik pertama di Indonesia yang menyediakan solusi kesehatan secara menyeluruh. Di KALCare, Anda dapat merasakan pengalaman baru yang belum pernah ada di industri kesehatan Indonesia sebelumnya, di mana semua jenis layanan kesehatan tersentralisasi pada satu pusat kesehatan terpadu.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 329', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'https://www.kalcare.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '91', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(28, '', '', 0, '', 'HOLIKA HOLIKA', 'Holika Holika products feature unique, eye-catching packaging to appeal to young, style-conscious consumers, both female and male.  Our brand is offering affordable prices on high quality products made with the finest ingredients. - See more at: http://www.holikaholika.ca/the-story/the-holika-holika-story/#sthash.VMNHMiUP.dpuf', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 098', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.holikaholika.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', '92', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(29, '', '', 0, '', 'SIMPLY', 'Simply adalah usaha franchise (waralaba) untuk toko-toko SARI TEBU, dan minuman minuman sehat lainnya. Semua produk Simply adalah dari kwalitas tinggi dan dibuat menurut resep resep rahasi kami.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'https://simplydrinks.wordpress.com/about', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', 'K01', 0, '2015-04-11 04:21:27', '2015-04-11 04:21:27'),
(30, '', '', 0, '', 'DORAYAKI ADDICT', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', 'K02', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(31, '', '', 0, '', 'OKIROBOX', 'Along these past 8 years, we''ve been with you: serving great foods, smile and everything. Yes, only for you. We are pioneer as well as leader in Japanese hotsnacks dealer in the country.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.okirobox.com', NULL, NULL, NULL, NULL, NULL, NULL, 'LG', 'K05', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(32, '', '', 0, '', 'EVERBEST', 'The Everbest Group has been designing, making and selling shoes for over 30 years. Today, our shoes are sold primarily across South East Asia, but are also found in countries as widespread as the UK, Mauritius and Australia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 140', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.everbestshoes.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '27', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(33, '', '', 0, '', 'DANAR HADI', 'Danar Hadi, dengan pencapaian dalam kualitas dan keahlian, memiliki masa depan cerah dalam industri batik. Semua itu di dukung filosofi perusahaan yang mengakar kuat pada seni tradisional yang diusungnya', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.danarhadibatik.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '28', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(34, '', '', 0, '', 'SAMSONITE', 'Samsonite has set an industry precedence by perfecting and innovating luggage, casual bags, backpacks, travel accessories, and now electronics carriers and laptop bags. Over one hundred years of reliability, durability, style and innovative functionality have made Samsonite’s iconic products, and brand, the global leader they are today.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 139', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.samsonite.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '30', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(35, '', '', 0, '', 'HUSH PUPPIES', 'Hush Puppies is a part of the Pacific Brands business in Australia and globally a division of Wolverine World Wide, the world’s leading maker of casual, work, and outdoor footwear.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 089', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.hushpuppies.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '31', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(36, '', '', 0, '', 'GIORDANO', 'Giordano, International Limited is a Hong Kong-based retailer of men''s, women''s and children''s quality apparel', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 091', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'id.e-giordano.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '32', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(37, '', '', 0, '', 'PARKSON', 'Parkson is continuing its success story as Asia’s leading department store by opening the first store in Jakarta, located in Lippo Mall Puri @St.Moritz', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 011', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.parkson.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '35', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(38, '', '', 0, '', 'OUTBACK STEAKHOUSE', 'Outback Steakhouse is an Australian themed steakhouse restaurant. Although beef and steak items make up a good portion of the menu, the concept offers a variety of chicken, ribs, seafood, and pasta dishes. The Company''s strategy is to differentiate its restaurants by emphasizing consistently high-quality food and service, generous portions at moderate prices and a casual atmosphere suggestive of the Australian Outback.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 323', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.outback.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '53', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(39, '', '', 0, '', 'GAUDEN CAFÉ & BAR', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 316', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '56', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(40, '', '', 0, '', 'J.CO', 'We are an international premium Donuts and Coffee brand which offers unique and original mixed flavors of donuts and beverages to people unlike they''ve ever tasted before. Within less than 6 years of operation, J.CO has succeeded in opening more than 120 outlets throughout Asia with its expansion in Indonesia, Singapore, China and Malaysia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 081', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.jcodonuts.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '62', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(41, '', '', 0, '', 'BALENO', '"Baleno’s story can be traced back to 1981, when the trademark “BALENO” was registered in Hong Kong. In 1996, Texwinca, the Hong Kong listed company, acquired the trademark ""BALENO"" and established Baleno Holdings Limited as the holding company. With successful rebranding tactics and marketing strategies, Baleno expanded its network rapidly across Asia."', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 116', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '63', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(42, '', '', 0, '', 'SAMUEL & KEVIN', '"The brand S&K, ""SAMUEL & KELVIN"" was established in 1997 in Hong Kong. Adopting a clear brand positioning and management direction, S&K has successfully built its image as ""The Trousers Expert"" and gained widespread recognition amongst young people."', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 114', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.samuel-kevin.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '65', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(43, '', '', 0, '', 'C&F PERFUMERY', 'C&F Perfumery was founded in 1994 and since then the store has become a nationwide retail chain located throughout the cities in Indonesia.  We distinguish ourselves by providing an extensive collection of ever growing number of brands and offering the best quality of service to our customers.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 034', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '67', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(44, '', '', 0, '', 'SECRET RECIPE', 'Secret Recipe is a popular brand of international lifestyle café chain operating in 9 countries worldwide with over 250 outlets to date. Secret Recipe has immediately grown to four outlets in two years time with a fully operational Commissary instituted to support aggressive outlet expansion.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 118', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.secretrecipe.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '68', 0, '2015-04-11 04:21:28', '2015-04-11 04:21:28'),
(45, '', '', 0, '', 'KYARA JEWELLERY', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 112', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '70', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(46, '', '', 0, '', 'CAFÉ EXCELSO', 'EXCELSO is an original coffee shop from Indonesia and a part of Kapal Api Group, the largest coffee producer in Indonesia . The first EXCELSO Café was opened in September 1991 in Plaza Indonesia, Jakarta. EXCELSO has grown to become one of  the strongest and recognized coffee shop brands in Indonesia. More than a decade later, it has now becomes a chain with up to 100* stores in around 28* cities in Indonesia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 178', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.excelso-coffee.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '73', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(47, '', '', 0, '', 'PARANG KENCANA', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 319', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '75', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(48, '', '', 0, '', 'SWAN JEWELLERY', 'Swan Jewellery is a modern chain store in Indonesia. With tagline “Design Without Limit”, Swan Jewellery commit to provide jewelleries product with the newest design, quality, and fashionable. With this concept, Swan Jewellery will surely be a market leader in diamond jewellery sales in Indonesia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 199', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.swan-jewellery.com/index.php?lang=id', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '76', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(49, '', '', 0, '', 'LOOKZ', 'A Unique Eyewear Experience', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 321', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '78', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(50, '', '', 0, '', 'POMPOUS', 'P O M P O U S ''Korean high end hand-selected fashion''.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021-29 111 080', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '79', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(51, '', '', 0, '', 'ADELLE JEWELRY', 'Established since 2013, Adelle Jewellery is one of the jewellery company in Indonesia that presents the best quality collection for any age, gender, and occasion in varied price. Until now, Adelle Jewellery has already opened 3 stores in Jakarta, Bandung, and Surabaya.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021-29 111 168', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'adellejewellery.com', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '80', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(52, '', '', 0, '', 'MOKKA COFFEE CABANA', 'We love to make coffee for the city that loves to drink it.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 351', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'G', '82', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(53, '', '', 0, '', 'PRESTIGE', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 106', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.prestige-asia.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '28', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(54, '', '', 0, '', 'FRANK & CO', 'Frank & co. is an expert jewellery company and home to a unique collection of exclusive F colour and VVS clarity diamonds, straight from registered diamond suppliers all over the world. Speak our name and it echoes creativity, quality, innovation and integrity. We follow exacting procedures in selecting the best from the best to turn them into man''s most dazzling treasures.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 001', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.frankncojewellery.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '35', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(55, '', '', 0, '', 'PARKSON', 'Parkson is continuing its success story as Asia’s leading department store by opening the first store in Jakarta, located in Lippo Mall Puri @St.Moritz.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 011', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.parkson.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '36', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(56, '', '', 0, '', 'STACCATO', 'Staccato defines its brand as trendy and fashion forward ‘European stylish shoes” which is easy to wear and mix & match with wide variety of styles. The wide range of Staccato shoes include casual, formal and even party shoes.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 220', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '38', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(57, '', '', 0, '', 'OPTIK BELYNA', '"PT. Belyna Kurnia Abadi (BKA) was established in 2011. Focusing on optical products of frames, sunglasses, contact lenses and solutions, BKA is an authorized dealer for several international eyewear brand names in Indonesia including Kenzo, Balmain, Jill Stuart and more. We are the most competitive optical distributor in the Indonesian market introducing new, excellent, reliable brands and to be the leading optical shops in Indonesia."', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 093', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '39', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(58, '', '', 0, '', 'OPTIK TUNGGAL', 'To provide premium quality lenses, Optik Tunggal is fully supported by Tunggal Optical Laboratory (TOL), a special laboratory in collaboration with Carl Zeiss, the world’s renowned lens factory located in Germany, in producing premium quality lenses based on state-of-the-art technology in the world.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 138', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.optiktunggal.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '60', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(59, '', '', 0, '', 'OPTIK SEIS', 'Optik Seis has the most high quality product range with renowed fashion brands from all over the world, to enable the customer in choosing the best product according to his/her need and personal style. Today, Optik Seis is the most experienced optic in Indonesia with more than 1,000 professionals in more than 120 stores in Indonesia. Optik Seis will continue to grow to be the best there is.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 031', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'https://www.optikseis.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '62', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(60, '', '', 0, '', 'T.G.I.FRIDAY''S', 'TGI Fridays℠ is an international chain focusing on casual dining, with over 1000 restaurants in 58 countries. Famous for fresh food and mouthwatering American classics, from appetizers perfect for sharing, to memorable burgers and delicious desserts. With the fun and friendly waiters and waitresses, we have become the ultimate destination for diners looking for something distinctive and different.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 173', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'fridays.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '65', 0, '2015-04-11 04:21:29', '2015-04-11 04:21:29'),
(61, '', '', 0, '', 'DONINI', 'The name of the brand itself, originated from our most celebrated Italian designer. Born in Bologna – Italy, since 1970, DONINI has been recognized as a manufacturer of high quality ladies hard bags in Europe.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 192', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'doninibags.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UG', '66', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(62, '', '', 0, '', 'TRAVEL XPERIENCE', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 167', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.travel-xperience.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '50', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(63, '', '', 0, '', 'RON''S LAB', 'We are a molecular gastronomy gelato , ice cream, sorbet and coffee shop', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 166', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.ronslaboratory.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '51', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(64, '', '', 0, '', 'PALMERHAUS', 'Palmerhaus has rapidly established itself as a leading destination for luxury bedding, bath linens and general homeware. Distinctive from others, Palmerhaus’ signature collections of bedroom and bathroom products are customisable with a special personalisation service.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'palmerhaus.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '52', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(65, '', '', 0, '', 'WACOAL', 'Our ultimate goal is to help build an ever more prosperous cosmopolitan society by providing beauty solutions, in the form of products and related information, to meet customers need. We are specialists in the area of stretched fabrics, which are essential for the intimate apparel products we manufactured.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 092', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.wacoal.com/id', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '55', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(66, '', '', 0, '', 'PARKSON', 'Parkson is continuing its success story as Asia’s leading department store by opening the first store in Jakarta, located in Lippo Mall Puri @St.Moritz.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 011', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.parkson.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '56', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(67, '', '', 0, '', 'OKE SHOP', 'PT Trikomsel Oke Tbk. merupakan perusahaan penyedia produk dan layanan telekomunikasi seluler ternama di Indonesia. Aktivitas usaha Perusahaan dilakukan melalui jalur distribusi dan ritel dengan tujuan mewujudkan visi Perseroan : Memberikan Kepuasan Meraih Kepercayaan serta Visi dan Misi Perusahaan : Memberikan kepuasan dan meraih kepercayaan konsumen yang memiliki gaya hidup komunikasi berbeda-beda dan tersebar di berbagai tempat dengan sumber daya manusia yang unggul, produk dan layanan yang tepat serta didukung oleh sistem informasi dan operasional yang terpercaya.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 060', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.oke.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '58', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(68, '', '', 0, '', 'SENTRA PONSEL', 'SentraPonsel adalah outlet modern penyedia produk telekomunikasi bergaransi resmi dengan aksesoris original. beragam koleksi HP Nokia, blackberry, Samsung , Sony dan Iphone tersedia di SentraPonsel lengkap dengan aksesorisnya. Produk - Produk non garansi tidak ditemukan di SentraPonsel karena sentraponsel... Always Original!!', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 042', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.sentraponsel.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '60', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(69, '', '', 0, '', 'POINT 2000', 'Era Point Globalindo founded as a commitment from the founders to realize the attitude of professionalism and optimism in developing its business organization, as well as play an active role to support government regulation in the telecommunications industry.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 007', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'poin2000.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '61', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(70, '', '', 0, '', 'GLOBAL TELESHOP', 'PT. Global Teleshop is nation-wide retail chain for telecommunication products in Indonesia. Our mission is: “To be the Customers'' first choice for their telecommunication needs”', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 094', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.globalteleshop.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '62', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(71, '', '', 0, '', 'PLAY', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 195', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '65', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(72, '', '', 0, '', 'ILC', 'International Language Center, a language course that provides 12 languages in Jakarta. As the students join ILC, they can freely choose and learn all 7 major languages – English, Mandarin, Japanese, Korean, French, German and Dutch.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 180', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'ilccourse.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '67C', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(73, '', '', 0, '', 'FOOD AVENUE', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 070', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '68', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(74, '', '', 0, '', 'GS SHOP', 'GS Shop adalah retailer video game yang sudah sangat berpengalaman di bidangnya. Dengan eksistensi selama lebih dari 20 tahun, GS Shop saat ini telah berkembang dan memiliki lebih dari 30 toko retail yang tersebar di berbagai kota di Indonesia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 145', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'https://www.gsshop.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '70', 0, '2015-04-11 04:21:30', '2015-04-11 04:21:30'),
(75, '', '', 0, '', 'ERAFONE', 'Erafone berkomitmen untuk senantiasa berinovasi demi memenuhi tuntutan kebutuhan telekomunikasi dan pasar. Komitmen tersebut dijaga dan dipelihara dengan memberikan nilai tambah dan kualitas, serta pelayanan profesional kepada para pelanggan. Seluruh usaha tersebut didukung dengan tersebarnya 200-an outlet Erafone dengan 18 kantor cabang di Jabodetabek dan kota-kota besar lainnya di seluruh nusantara.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 137', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'erafone.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '71', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(76, '', '', 0, '', 'GET WID', 'GET-WID™ adalah salah satu gadget store yang memiliki retail & online store yg menawarkan berbagai macam gadgets seperti BlackBerry, Apple, HTC, Android, Nokia & berbagai aksesori bermerk, berkualitas pastinya untuk gadget anda.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 141', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.get-wid.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '73', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(77, '', '', 0, '', 'BOOKS & BEYOND', 'Books & Beyond formerly known as Times Bookstore. Books & Beyond managed by PT Gratia Prima Indonesia, a subsidiary of PT Multipolar Group, Tbk , a local multi format retailer in Indonesia. Times bookstores was first inaugurated in 2008 with the first store in Universitas Pelita Harapan, Lippo Karawaci, Tangerang. On October 10, 2012, Times bookstore changed its name to Books & Beyond. Currently, we have 36 stores located in Jakarta, Tangerang, Bandung, Medan, Balikpapan, Ujung Pandang, Bali, Manado, Makassar and we will continue to grow to meet the needs of book lovers in Indonesia.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 143', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'booksbeyond.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '75', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(78, '', '', 0, '', 'WATSONS', 'Watsons Indonesia delivers only the best health, wellness, and beauty solutions to each and every customer.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 008', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.watsonsasia.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '76', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(79, '', '', 0, '', 'URBAN LIFE', 'URBANLIFE is a new innovative concept store for Gadget, Audio, Travel & Lifestyle. We are passionate in bringing fun accessories and cool lifestyle products. The concept store offers wide selections of exclusive award winning products with impressive design, offering experience in advanced solutions and intelligent performance in excellent quality. To us it really matters when you can add a dash of excitement to your gadgets in your own way.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 099', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'urbanlifestore.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '77', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(80, '', '', 0, '', 'POLO RALPH LAUREN', 'What began 40 years ago with a collection of ties has grown into an entire world, redefining American style. Ralph Lauren has always stood for providing quality products, creating worlds and inviting people to take part in our dream. We were the innovators of lifestyle advertisements that tell a story and the first to create stores that encourage customers to participate in that lifestyle.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 177', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.ralphlauren.com/shop/index.jsp?categoryId...', NULL, NULL, NULL, NULL, NULL, NULL, 'L1', '79', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(81, '', '', 0, '', 'GOLDEN RAMA', '', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.golden-rama.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '11', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(82, '', '', 0, '', 'DANIEL AMARTA', 'Salon yang sudah berdiri sejak lama ini..kini hadir lebih dekat lagi dengan anda..cabang baru kami yang kini beroperasi telah bertambah..dengan stylis yang sudah berpengalaman dan juga servis kami yg semakin lengkap.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 088', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'danielamarta.blogspot.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '12', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(83, '', '', 0, '', 'KAIZEN', 'KAIZEN – adalah sebuah brand untuk tempat Pangkas Rambut (Barber Shop)  yang sudah beroperasi sejak tahun 2004 di Jakarta. Konsep layanan yang disediakan oleh KAIZEN sebenarnya sangat sederhana dengan hanya satu treatment yaitu pangkas/potong rambut express, yang dilayani hanya dalam waktu sepuluh menit. Selain factor express, dalam layanannya,   KAIZEN  juga mengutamakan aspek  Hygienic, Beauty and Value.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', '', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '15', 0, '2015-04-11 04:21:31', '2015-04-11 04:21:31'),
(84, '', '', 0, '', 'MATAHARI DEPARTMENT STORE', 'PT Matahari Department Store Tbk (Matahari) adalah perusahaan ritel yang menyediakan pakaian, aksesoris, perlengkapan kecantikan, dan perlengkapan rumah untuk konsumen yang menghargai mode dan nilai tambah. Didukung oleh jaringan pemasok lokal dan internasional terpercaya, gabungan antara mode yang terjangkau, gerai dengan visual menarik, berkualitas dan modern, memberikan pengalaman berbelanja yang dinamis dan menyenangkan, dan menjadikan Matahari sebagai department store pilihan utama bagi kelas menengah Indonesia yang tengah tumbuh pesat.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 043', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.matahari.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '21', 0, '2015-04-11 04:21:32', '2015-04-11 04:21:32'),
(85, '', '', 0, '', 'GADGET PLUS', 'Find All Your Gadget need in our Corner', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 095', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.gadgetplusstore.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '37', 0, '2015-04-11 04:21:32', '2015-04-11 04:21:32'),
(86, '', '', 0, '', 'THE GRAND NIHAO RESTAURANT', 'Cooking is art ,we make it passion.', NULL, NULL, NULL, NULL, NULL, NULL, 101, 'Indonesia', '021 - 29 111 135', NULL, NULL, NULL, 'active', NULL, 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'retailer', 2, 'no', 'www.grandnihao.com', NULL, NULL, NULL, NULL, NULL, NULL, 'L2', '50', 0, '2015-04-11 04:21:32', '2015-04-11 04:21:32');
TENANT;

        $this->command->info('Seeding merchants table with lippo puri tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $categories = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_merchant_id`, `category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
(1, 1, 3, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 9, 3, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3, 1, 4, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(4, 9, 4, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(5, 1, 5, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(6, 9, 5, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 1, 6, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(8, 9, 6, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(9, 1, 7, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(10, 9, 7, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(11, 7, 8, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(12, 7, 9, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(13, 7, 10, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(14, 7, 11, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(15, 7, 12, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(16, 7, 13, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(17, 13, 14, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(18, 13, 15, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(19, 4, 16, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(20, 7, 17, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(21, 7, 18, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(22, 14, 19, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(23, 7, 20, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(24, 7, 21, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(25, 7, 22, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(26, 11, 23, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(27, 14, 24, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(28, 1, 25, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(29, 9, 25, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(30, 11, 26, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(31, 1, 27, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(32, 9, 27, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(33, 1, 28, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(34, 9, 28, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(35, 7, 29, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(36, 7, 30, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(37, 7, 31, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(38, 6, 32, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(39, 6, 33, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(40, 6, 34, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(41, 6, 35, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(42, 6, 36, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(43, 6, 37, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(44, 7, 38, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(45, 7, 39, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(46, 7, 40, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(47, 6, 41, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(48, 6, 42, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(49, 12, 43, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(50, 15, 43, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(51, 7, 44, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(52, 12, 45, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(53, 15, 45, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(54, 7, 46, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(55, 6, 47, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(56, 12, 48, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(57, 15, 48, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(58, 6, 49, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(59, 6, 50, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(60, 12, 51, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(61, 15, 51, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(62, 7, 52, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(63, 6, 53, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(64, 12, 54, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(65, 15, 54, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(66, 6, 55, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(67, 6, 56, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(68, 12, 57, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(69, 15, 57, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(70, 12, 58, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(71, 15, 58, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(72, 12, 59, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(73, 15, 59, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(74, 7, 60, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(75, 6, 61, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(76, 6, 62, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(77, 7, 63, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(78, 11, 64, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(79, 6, 65, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(80, 6, 66, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(81, 5, 67, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(82, 5, 68, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(83, 5, 69, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(84, 5, 70, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(85, 5, 71, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(86, 3, 72, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(87, 7, 73, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(88, 2, 74, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(89, 8, 74, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(90, 10, 74, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(91, 5, 75, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(92, 5, 76, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(93, 2, 77, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(94, 8, 77, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(95, 10, 77, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(96, 1, 78, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(97, 9, 78, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(98, 5, 79, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(99, 6, 80, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(100, 2, 81, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(101, 8, 81, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(102, 10, 81, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(103, 6, 82, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(104, 6, 83, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(105, 6, 84, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(106, 5, 85, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(107, 7, 86, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
CAT;

        $this->command->info('Seeding category_merchant table with lippo puri tenants...');
        DB::unprepared($categories);
        $this->command->info('table category_merchant seeded.');

        $twoMonth = date('Y-m-d H:i:s', strtotime('+2 month'));
        $luckydraws = <<<LUCKY
INSERT INTO `{$prefix}lucky_draws` (`lucky_draw_id`, `mall_id`, `lucky_draw_name`, `description`, `image`, `start_date`, `end_date`, `minimum_amount`, `grace_period_date`, `grace_period_in_days`, `min_number`, `max_number`, `status`, `created_by`, `modified_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'Lippo Mall Puri Lucky Draw', 'Lippo Mall Puri Lucky Draw.', NULL, NOW(), '{$twoMonth}', 100000.00, NULL, 30, 100000, 200000, 'active', 0, 3, '0000-00-00 00:00:00', '2015-04-17 18:08:43');

truncate table {$prefix}lucky_draw_numbers;
start transaction;
call generate_lucky_draw_number(100001, 200000, 1, 3);
commit;
LUCKY;

        $this->command->info('Seeding lucky_draws table...');
        DB::unprepared($luckydraws);
        $this->command->info('table lucky_draws seeded.');

        $this->command->info('Creating new customer service account...');
        $this->createCustomerService();
        $this->command->info('customer service created.');

        $this->command->info('Creating settings...');
        $this->createSetting();
        $this->command->info('settings created.');
    }

    protected function createCustomerService()
    {
        try {
            $loginId = 'cs1';
            $birthdate = '1990-01-01';
            $password = '123456';
            $position = 'Front Side';
            $employeeId = 'CS001';
            $firstName = 'Customer';
            $lastName = 'Service';
            $empStatus = 'active';

            $current = User::where('username', 'cs1')->first();
            if (is_object($current)) {
                DB::table('users')->where('user_id', $current->user_id)->delete();
                DB::table('apikeys')->where('user_id', $current->user_id)->delete();
                DB::table('user_details')->where('user_id', $current->user_id)->delete();

                $emp = Employee::where('user_id', $current->user_id)->first();
                DB::table('employee_retailer')->where('employee_id', $emp->employee_id)->delete();
                DB::table('employees')->where('user_id', $current->user_id)->delete();
            }

            $role = Role::where('role_name', 'mall customer service')->first();

            $newUser = new User();
            $newUser->username = $loginId;
            $newUser->user_email = $loginId . '@myorbit.com';
            $newUser->user_password = Hash::make($password);
            $newUser->status = $empStatus;
            $newUser->user_role_id = $role->role_id;
            $newUser->user_ip = '127.0.0.1';
            $newUser->modified_by = 0;
            $newUser->user_firstname = $firstName;
            $newUser->user_lastname = $lastName;

            $newUser->save();

            $apikey = new Apikey();
            $apikey->api_key = Apikey::genApiKey($newUser);
            $apikey->api_secret_key = Apikey::genSecretKey($newUser);
            $apikey->status = 'active';
            $apikey->user_id = $newUser->user_id;
            $apikey = $newUser->apikey()->save($apikey);

            $newUser->setRelation('apikey', $apikey);
            $newUser->setHidden(array('user_password'));

            $userdetail = new UserDetail();
            $userdetail->birthdate = $birthdate;
            $userdetail = $newUser->userdetail()->save($userdetail);

            $newEmployee = new Employee();
            $newEmployee->employee_id_char = $employeeId;
            $newEmployee->position = $position;
            $newEmployee->status = $newUser->status;
            $newEmployee = $newUser->employee()->save($newEmployee);

            // @Todo: Remove this hardcode
            $retailerIds = [2];
            $newEmployee->retailers()->sync($retailerIds);

        } catch (Exception $e) {
            printf("(%s) %s\n", $e->getLine(), $e->getMessage());
        }
    }

    protected function createSetting()
    {
        $landingPage = Setting::where('object_type', 'merchant')
                              ->where('object_id', 2)
                              ->where('setting_name', 'landing_page')
                              ->active()
                              ->first();

       if (empty($landingPage)) {
            $landingPage = new Setting();
            $landingPage->object_id = 2;
            $landingPage->object_type = 'merchant';
        }

        $landingPage->setting_name = 'landing_page';
        $landingPage->setting_value = 'news';
        $landingPage->save();

        $masterPassword = Setting::where('object_type', 'merchant')
                              ->where('object_id', 2)
                              ->where('setting_name', 'master_password')
                              ->active()
                              ->first();

        if (empty($masterPassword)) {
            $masterPassword = new Setting();
            $masterPassword->object_id = 2;
            $masterPassword->object_type = 'merchant';
        }

        $masterPassword->setting_name = 'master_password';
        $masterPassword->setting_value = Hash::make('abc123');
        $masterPassword->save();
    }
}
