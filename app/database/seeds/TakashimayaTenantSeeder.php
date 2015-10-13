<?php
use Orbit\Database\ObjectID;
/**
 * Seeder for Tenant.
 *
 */
class TakashimayaTenantSeeder extends Seeder
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

        $generatedIDs = [
            'tenant' => [],
            'category_merchant' => []
        ];
        $pdo = DB::connection()->getPdo();
        $mall_id = $pdo->quote(TakashimayaMerchantSeeder::MALL_ID);
        $generateID = function ($group = null, $sequence = false) use (&$generatedIDs, $pdo)
        {
                $id  = $this->generateID();
                $id  = $pdo->quote($id);
                if($sequence) $generatedIDs[$group] [$sequence] = $id;
                return $id;
        };

        $tenants = <<<TENANT
INSERT INTO `{$prefix}merchants` (`merchant_id`, `omid`, `orid`, `user_id`, `email`, `name`, `description`, `address_line1`, `address_line2`, `address_line3`, `postal_code`, `city_id`, `city`, `country_id`, `country`, `phone`, `fax`, `start_date_activity`, `end_date_activity`, `status`, `logo`, `currency`, `currency_symbol`, `tax_code1`, `tax_code2`, `tax_code3`, `slogan`, `vat_included`, `contact_person_firstname`, `contact_person_lastname`, `contact_person_position`, `contact_person_phone`, `contact_person_phone2`, `contact_person_email`, `sector_of_activity`, `object_type`, `parent_id`, `is_mall`, `url`, `masterbox_number`, `slavebox_number`, `mobile_default_language`, `pos_language`, `ticket_header`, `ticket_footer`, `floor`, `unit`, `modified_by`, `created_at`, `updated_at`) VALUES
({$generateID('tenant', 3)}, '', '', 0, '', 'ARMANI EXCHANGE', 'The modern wardrobe as only Giorgio Armani could envision it, Armani Exchange embodies the youthful spirit of a new generation. \r\n\r\nArmani Exchange takes a playful, urban approach to apparel and accessories, reaching a global audience through over 200 stores worldwide.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '68352855', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/maps/3-armani-exchange-1433763154_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'armaniexchange.com/singapore', '3', NULL, NULL, NULL, NULL, NULL, 'LG', '3', 3, '2015-04-10 20:21:25', '2015-06-08 11:32:34'),
({$generateID('tenant', 4)}, '', '', 0, '', 'BEAUTY SPA MIS PARIS & DANDY HOUSE', 'The concept of our salon is“Respect for Japanese Style”The typical Japanese wooden interior welcomes you in a warm atmosphere, the tiles on our floors and walls remind of Japanese ceramics, creating the grace of the traditional beauty of Japan. If you take one step into our salon, for a little while, you will be able to indulge in luxury that will make you feel like a real VIP in a traditional Japanese surrounding, all the while being in a different country.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '62351159', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/maps/4-beauty-spa-mis-paris-dandy-house-1433763172_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'miss-paris.com.sg/', '4', NULL, NULL, NULL, NULL, NULL, 'L5', '25', 3, '2015-04-10 20:21:26', '2015-06-08 11:32:52'),
({$generateID('tenant', 5)}, '', '', 0, '', 'BEST DENKI', 'stores in Japan. We are constantly developing new retail concepts including multimedia oriented era outlets, information based exchanges and housing related specialty shops.\r\nTo date, Best Denki has more than 500 retail stores worldwide with 466 in Japan, 11 in singapore, 10 in Malaysia, 6 in Indonesia', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '68352855', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/maps/5-best-denki-1433763119_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'go.bestdenki.com.sg', '5', NULL, NULL, NULL, NULL, NULL, 'L5', '1', 3, '2015-04-10 20:21:26', '2015-06-08 11:31:59'),
({$generateID('tenant', 6)}, '', '', 0, '', 'BRICKS WORLD', 'Bricks World main shop is located at Ngee Ann City, Level 5 and the shop is the first and largest LEGO Exclusive shop in Singapore. Our Ngee Ann City store was officially opened on 13 December 2003 and was the first monobrand LEGO store in Singapore.\r\nWe carry more than 90 per cent of the LEGO merchandise available in Singapore and thus able to provide superior service and support for our customers.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '67345512', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/6-bricks-world-1433763557_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'bricksworld.com/', '6', NULL, NULL, NULL, NULL, NULL, 'L5', '15', 3, '2015-04-10 20:21:26', '2015-06-08 11:39:17'),
({$generateID('tenant', 7)}, '', '', 0, '', 'CHARLES & KEITH', 'CHARLES & KEITH SUMMER 2015 lends an element of finesse accompanied by contemporary appeal that binds the lithe movements and vivacity of youth to life.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '67370152', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/7-charles-keith-1433763771_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'www.charleskeith.com/', '7', NULL, NULL, NULL, NULL, NULL, 'LG', '12', 3, '2015-04-10 20:21:26', '2015-06-08 11:42:51'),
({$generateID('tenant', 8)}, '', '', 0, '', 'CHOPARD BOUTIQUE', 'It all began in 1860 in the small village of Sonvilier, Switzerland. Here Louis-Ulysse Chopard, a talented young craftsman, established his workshop. By virtue of their precision and reliability, his watches quickly gained a solid reputation among enthusiasts and found buyers as far afield as Eastern Europe, Russia and Scandinavia.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '67338111', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/8-chopard-boutique-1433763967_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'www.chopard.com/‎', '8', NULL, NULL, NULL, NULL, NULL, 'L1', '3', 3, '2015-04-10 20:21:26', '2015-06-08 11:46:07'),
({$generateID('tenant', 9)}, '', '', 0, '', 'LA CURE GOURMANDE', 'Created in 1989, La Cure Gourmande is far more than chocolates, confectionery and biscuits. It''s an emotional experience from the moment you walk into the store. Everything about La Cure Gourmande will make you feel like you just stepped back into childhood.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '66842983', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/9-la-cure-gourmande-1433764758_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'www.curegourmande.com/index.cfm', '9', NULL, NULL, NULL, NULL, NULL, 'L3', '9', 3, '2015-04-10 20:21:26', '2015-06-08 11:59:18'),
({$generateID('tenant', 10)}, '', '', 0, '', 'LADUREE BOUTIQUE', 'Parisian tea rooms'' history is intimately tied to the \r\nhistory of the Ladurée family. It all began in 1862, when \r\nLouis Ernest Ladurée, a miller from the southwest of \r\nFrance, founded a bakery in Paris at 16 rue Royale.\r\nIn 1871, while Baron Haussmann was giving \r\nParis a « new face », a fire in the bakery opened \r\nthe opportunity to transform it into a pastry shop.\r\nThe decoration of the pastry shop was entrusted to \r\nJules Cheret, a famous turn-of-the-century \r\npainter and poster artist.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '68847361', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/10-laduree-boutique-1433764911_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'www.laduree.com', '10', NULL, NULL, NULL, NULL, NULL, 'L2', '9', 3, '2015-04-10 20:21:26', '2015-06-08 12:01:52'),
({$generateID('tenant', 11)}, '', '', 0, '', 'L''OCCITANE', 'With nothing but an alambic, a small truck and a solid knowledge of plants, Olivier Baussan, at the age of 23, distills Rosemary essential oil which he sells on the local markets of Provence. The L’OCCITANE journey begins.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '67377800', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/11-loccitane-1433764346_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'www.shihlinsnacks.com.tw/id', '11', NULL, NULL, NULL, NULL, NULL, 'LG', '33', 3, '2015-04-10 20:21:26', '2015-06-08 11:52:26'),
({$generateID('tenant', 12)}, '', '', 0, '', 'SEPHORA', 'Sephora is a visionary beauty-retail concept founded in France by Dominique Mandonnaud in 1970. Sephora''s unique, open-sell environment features an ever-increasing amount of classic and emerging brands across a broad range of product categories including skincare, color, fragrance, body, smilecare, and haircare, in addition to Sephora''s own private label. \r\n\r\nToday, Sephora is not only the leading chain of perfume and cosmetics stores in France, but also a powerful beauty presence in countries around the world. \r\n\r\nTo build the most knowledgeable and professional team of product consultants in the beauty industry, Sephora developed "Science of Sephora." This program ensures that our team is skilled to identify skin types, have knowledge of skin physiology, the history of makeup, application techniques, the science of creating fragrances, and most importantly, how to interact with Sephora''s diverse clientele. \r\n\r\nOwned by LVMH Moët Hennessy Louis Vuitton, the world''s leading luxury goods group, Sephora is highly regarded as a beauty trailblazer, thanks to its unparalleled assortment of prestige products, unbiased service from experts, interactive shopping environment, and innovation.', 'null', 'null', NULL, 0, NULL, 'null', 0, '', '68365622', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'active', 'uploads/retailers/pictures/12-sephora-1433765032_1.jpg', 'IDR', 'Rp', NULL, NULL, NULL, NULL, 'yes', 'null', 'null', 'null', NULL, NULL, 'null', NULL, 'tenant', {$mall_id}, 'no', 'sephora.com/', '12', NULL, NULL, NULL, NULL, NULL, 'L1', '6', 3, '2015-04-10 20:21:26', '2015-06-08 12:03:52');

-- Update Master Box Number (The Merchant Verification Number)
UPDATE `{$prefix}merchants` SET masterbox_number=merchant_id;
TENANT;

        $this->command->info('Seeding merchants table with Takashimaya tenants...');
        DB::unprepared($tenants);
        $this->command->info('merchants table seeded.');

        $findCategoryId = function  ($skip) use ($pdo) {
            $id = DB::table('categories')->skip($skip - 1)->first()->category_id;
            return $pdo->quote($id);
        };

        $categories = <<<CAT
INSERT INTO `{$prefix}category_merchant` (`category_merchant_id`, `category_id`, `merchant_id`, `created_at`, `updated_at`) VALUES
({$generateID()}, {$findCategoryId(6)},  {$generatedIDs['tenant'][3]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(1)},  {$generatedIDs['tenant'][4]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(4)},  {$generatedIDs['tenant'][5]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(1)},  {$generatedIDs['tenant'][6]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(9)},  {$generatedIDs['tenant'][6]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(6)},  {$generatedIDs['tenant'][7]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(12)},  {$generatedIDs['tenant'][8]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(7)},  {$generatedIDs['tenant'][9]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(7)}, {$generatedIDs['tenant'][10]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(1)}, {$generatedIDs['tenant'][11]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
({$generateID()}, {$findCategoryId(1)}, {$generatedIDs['tenant'][12]}, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
CAT;

        $this->command->info('Seeding category_merchant table with Takashimaya tenants...');
        DB::unprepared($categories);
        $this->command->info('table category_merchant seeded.');

        $twoMonth = date('Y-m-d H:i:s', strtotime('+2 month'));
        $luckydraws = <<<LUCKY
INSERT INTO `{$prefix}lucky_draws` (`lucky_draw_id`, `mall_id`, `lucky_draw_name`, `description`, `image`, `start_date`, `end_date`, `minimum_amount`, `grace_period_date`, `grace_period_in_days`, `min_number`, `max_number`, `status`, `created_by`, `modified_by`, `created_at`, `updated_at`) VALUES
({$generateID()}, {$mall_id}, 'Takashimaya Lucky Draw', 'Takashimaya Lucky Draw.', NULL, NOW(), '{$twoMonth}', 100000.00, NULL, 30, 100000, 200000, 'active', 0, 3, '0000-00-00 00:00:00', '2015-04-17 18:08:43');

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

        $this->createTranslations();
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
            $retailerIds = [TakashimayaMerchantSeeder::MALL_ID];
            $newEmployee->retailers()->sync($retailerIds);

        } catch (Exception $e) {
            printf("(%s) %s\n", $e->getLine(), $e->getMessage());
        }
    }

    protected function createSetting()
    {
        $landingPage = Setting::where('object_type', 'merchant')
                              ->where('object_id', TakashimayaMerchantSeeder::MALL_ID)
                              ->where('setting_name', 'landing_page')
                              ->active()
                              ->first();

       if (empty($landingPage)) {
            $landingPage = new Setting();
            $landingPage->object_id = TakashimayaMerchantSeeder::MALL_ID;
            $landingPage->object_type = 'merchant';
        }

        $landingPage->setting_name = 'landing_page';
        $landingPage->setting_value = 'widget';
        $landingPage->save();

        $masterPassword = Setting::where('object_type', 'merchant')
                              ->where('object_id', TakashimayaMerchantSeeder::MALL_ID)
                              ->where('setting_name', 'master_password')
                              ->active()
                              ->first();

        if (empty($masterPassword)) {
            $masterPassword = new Setting();
            $masterPassword->object_id = TakashimayaMerchantSeeder::MALL_ID;
            $masterPassword->object_type = 'merchant';
        }

        $masterPassword->setting_name = 'master_password';
        $masterPassword->setting_value = Hash::make('abc123');
        $masterPassword->save();
    }

    private function createTranslations()
    {
        $translations['zh'] = [
            // armani exchange
            '3' => '现代衣柜，因为只有乔治·阿玛尼可以想象它，阿玛尼体现了新一代的青春气息。阿玛尼交易所有限公司俏皮，城市的方法来服装及配饰，通过200多家商店在全球达到了全球观众。',
            // beauty spa mis paris
            '4' => '我们沙龙的理念是“尊重日本的风格”。典型的日系木质内饰欢迎您在热烈的气氛中，我们的地板和墙壁瓷砖提醒日本陶瓷，创造了日本的传统美的恩典。如果你走一步进入我们的沙龙，一小会儿，你就可以沉迷于奢侈品，会让你仿佛置身于一个传统的日本环境中的真正的VIP，在不同的国家所有，而为。',
            // best denki
            '5' => '我们不断开发新的零售概念，包括导向的时代网点多媒体，基于信息的交流与住房相关的专卖店。迄今为止，最好电器在全球拥有500多家零售店：466专卖店在日本，11家店在新加坡，10在马来西亚，6印尼。',
            // bricks world
            '6' => '砖世界主力店坐落在义安城，5级店是新加坡第一个，也是最大的乐高专卖店。我们的义安城店于2003年12月13日正式开业，是首家专卖店乐高专卖店在新加坡。我们进行的LEGO商品在新加坡提供，从而能够为我们的客户提供卓越的服务和支持超过90％。',
            // charles & keith
            '7' => 'CHARLES＆KEITH SUMMER 2015 年借给技巧伴随着现代化吸引力结合轻盈的运动和青年生活的活泼的元素。',
            // chopard boutique
            '8' => '这一切都始于1860年松维利耶，瑞士的小村庄。在这里，路易 - 雅典萧邦，一个有才华的年轻的工匠，建立了自己的工作室。凭借其精确度和可靠性，他的手表迅速获得爱好者之间的良好的声誉，并找到了买家远至东欧，俄罗斯和斯堪的纳维亚半岛。',
            // la cure gourmande
            '9' => '创建于1989年，香格里拉治疗Gourmande餐厅远远超过巧克力，糖果及饼干。这是一个从你走进商店的那一刻的情感体验。关于香格里拉治疗Gourmande餐厅的一切会让你觉得像你刚才踩回童年。',
            // laduree boutique
            '10' => '巴黎茶室的历史紧密联系在一起的LADUREE家族的历史。这一切都始于1862年，路易欧内斯特LADUREE，一位磨坊主从西南 法国，成立一间面包店位于巴黎的16街皇家。 1871年，当奥斯曼男爵是给巴黎«新面貌»，一场大火在面包店开业的机会，把它改造成一个糕点店。在糕点店的装修委托给朱CHÉRET，开启了本世纪的著名画家和海报艺术家。',
            // l'occitane
            '11' => '随着不过是一种蒸馏器，一辆小货车和植物知识扎实，奥利维尔Baussan，在23岁的时候，提炼其中他卖上普罗旺斯的当地市场迷迭香精油。欧舒丹旅程开始了。',
            // sephora
            '12' => '丝芙兰在法国创立多米尼克Mandonnaud于1970年丝芙兰独特的，开放的销售环境，一个有远见的美容零售概念拥有一个不断增加的经典量和在广泛的产品类别的新兴品牌，包括护肤品，色，香，身体，smilecare和护发，除了丝芙兰自己的私人标签。',
        ];
        $translations['ja'] = [
            // armani exchange
            '3' => '唯一のジョルジオ・アルマーニは、それを想像することができように、現代のワードローブは、アルマーニエクスチェンジは、新世代の若々しい精神を体現しています。 アルマーニエクスチェンジは、世界中の200以上の店を通じて世界の視聴者に到達し、アパレル、アクセサリーに遊び心、都市のアプローチを採用しています。',
            // beauty spa mis paris
            '4' => '私たちのサロンのコンセプトは、典型的な日本の木造インテリアは温かい雰囲気の中であなたを歓迎し「日本スタイルの尊重」であり、私たちの床と壁のタイルは、日本の伝統的な美しさの恵みを作成し、日本セラミックス思い出させます。あなたが私たちのサロンに一歩を取る場合は、しばらくの間、あなたは伝統的な日本では、すべての中には、別の国にいる周囲の真のVIPのような気分にさせるだろう贅沢にふけることができるようになります。',
            // best denki
            '5' => '私たちは常に時代のアウトレット向けマルチメディア、情報ベースの交換や住宅関連の専門店などの新しい小売コンセプトを開発しています。現在までに、ベスト電器は、世界中の500以上の小売店舗を展開しています。日本で466店舗、シンガポールに11店、インドネシアで10マレーシアの店舗と6',
            // bricks world
            '6' => 'レンガ世界本店はニーアン市、レベル5にあり、店はシンガポールで最初かつ最大のレゴ独占店です。私たちのニーアンシティ店は正式に2003年12月13日にオープンしたシンガポールで最初のmonobrandのLEGOストアでした。私たちはシンガポールで利用可能とお客様に優れたサービスとサポートを提供することができるLEGO商品の90パーセントを運びます。',
            // charles & keith
            '7' => 'CHARLES＆KEITH SUMMER2015はしなやかな動きや生活への若者の快活に特異的に結合する現代的な魅力を伴うフィネスの要素を貸します。',
            // chopard boutique
            '8' => 'それはすべてSonvilier、スイスの小さな村で1860年に始まりました。ここではルイ・ユリスショパール、才能ある若い職人は、彼のワークショップを設立しました。その精度と信頼性のおかげで、彼の時計はすぐに愛好家の間高い評価を得て、東欧、ロシア、スカンジナビアとして遠く買い手を見つけました。',
            // la cure gourmande
            '9' => '1989年に作成された、ラ・キュアGourmandeのはチョコレート、菓子、ビスケットよりもはるかにです。それはあなたが店に入る瞬間から、感情的な経験です。ラキュアGourmandeのについてのすべては、あなたが戻ったばかりの子供時代に足を踏み入れたように感じるようになります。',
            // laduree boutique
            '10' => 'パリのティールームの歴史は密接ラデュレの家族の歴史に関連付けられています。ルイアーネストラデュレ、フランスの南西部からのミラーは、16ロワイヤル通りにパリでパン屋を設立したときにそれはすべて、1862年に始まりました。男爵オスマンは«新人»パリを与えていた一方で1871年に、パン屋の火災は、ペストリーショップに変換する機会を開きました。ペストリーショップの装飾はシェレ、有名な世紀末前後の画家やポスターアーティストに委託されました。',
            // l'occitane
            '11' => 'アレンビック、小型トラックと23歳で植物の確かな知識、オリビエBaussan、何もして、彼はプロヴァンスのローカル市場で販売しているローズマリーエッセンシャルオイルを蒸留します。ロクシタンの旅が始まります。',
            // sephora
            '12' => 'セフォラは1970年セフォラのユニークな、オープン売り環境でドミニクMandonnaudによってフランスで設立されたビジョンを持った美しさ、小売コンセプトである古典的なの増え続ける量とスキンケア、色、香り、ボディなどの製品カテゴリーの広い範囲にわたって新興ブランドを提供しています、smilecare、およびヘアケア、セフォラ自身のプライベートラベルに加えました。',
        ];
        $mall = Mall::find(TakashimayaMerchantSeeder::MALL_ID);
        $languages_by_name = [];
        foreach ($mall->languages as $language) {
            $name = $language->language->name;
            $languages_by_name[$name] = $language;
        }

        $languages = ['zh', 'ja'];
        foreach ($languages as $language_name) {
            foreach ($translations[$language_name] as $id => $translation) {
                $merchant_translation = new MerchantTranslation();
                $merchant_translation->merchant_id = $id;
                $merchant_translation->merchant_language_id = $languages_by_name[$language_name]->merchant_language_id;
                $merchant_translation->description = $translation;
                $merchant_translation->save();
            }
        }
    }

    private function generateID()
    {
        return ObjectID::make();
    }
}
