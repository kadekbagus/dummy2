<?php
/**
 * Seeder for Category, categories are linked to merchant_id 3
 * the 'Takashimaya Shopping Center'.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @author Tian <tian@dominopos.com>
 */
class TakashimayaCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sources = <<<CATEGORIES
ATM
Appliances
Cafe & Bakery
Cafe
Chinese Casual Dining
Chinese Cuisine
Confectionery
Cosmetics & Skincare
Educational Toys & Enrichment Centres
Fashion, Accessories & Shoes
Fine Jewellery & Watches
Fragrance & Body Care
Gifts/Hobbies/Toys
Gourment Confectionery
Hairdressing & Beauty Salon
Home Decorative
International Fashion & Accessories
International Fashion, Accessories & Shoes
International Jewellery, Watches & Writing Instruments
Japanese Casual Dining
Japanese Cuisine
Korean Cuisine
Leather Goods & Shoes
Money Changer
Optical Services
Pharmacy, Health & Toiletries
Restaurants
Services
Shoes Repair & Key Services
Sports Apparel, Equipment & Accessories
Travel & Lifestyle
Western Casual Dining
Western Cuisine
CATEGORIES;

        $sources_zh = <<<CATEGORIES
ATM
电器用品
咖啡及蛋糕西点
咖啡馆
中餐饮
中餐馆
糖果西点
化妆及护肤品
教育玩具
时尚衣装、饰品及鞋子
精致珠宝首饰及手表
香水及护肤美肤保养品
礼物/爱好/玩具
高級甜点
美发及美容院
家庭装饰
国际时装及饰品
国际时装、饰品及鞋子
国际珠宝首饰、手表及名笔
日式餐饮
日本烹调
韩食餐馆
皮革物品&鞋子
货币兑换商
光学服务
西药房及保健美妆品
餐馆
服务
鞋子修理&关键服务
运动服装，配备及用品
旅行&生活方式
西方休闲餐饮
西餐馆
CATEGORIES;

        $sources_ja = <<<CATEGORIES
ja:ATM
ja:Appliances
ja:Cafe
ja:Cafe & Bakery
ja:Chinese Casual Dining
ja:Chinese Cuisine
ja:Confectionery
ja:Cosmetics & Skincare
ja:Educational Toys & Enrichment Centres
ja:Fashion, Accessories & Shoes
ja:Fine Jewellery & Watches
ja:Fragrance & Body Care
ja:Gifts/Hobbies/Toys
ja:Gourmet Confectionery
ja:Hair & Beauty
ja:Hairdressing & Beauty Salon
ja:Home Decorative
ja:International Fashion & Accessories
ja:International Fashion, Accessories & Shoes
ja:International Jewellery, Watches & Writing Instruments
ja:Japanese Casual Dining
ja:Japanese Cuisine
ja:Korean Cuisine
ja:Leather Goods & Shoes
ja:Money Changer
ja:Optical Services
ja:Pharmacy, Health & Toiletries
ja:Restaurants
ja:Services
ja:Shoes Repair & Key Services
ja:Sports Apparel, Equipment & Accessories
ja:Travel & Lifestyle
ja:Western Casual Dining
ja:Western Cuisine
CATEGORIES;



        $categories = explode("\n", $sources);
        $category_translations = [];
        $category_translations['zh'] = explode("\n", $sources_zh);
        $category_translations['ja'] = explode("\n", $sources_ja);

        $this->command->info('Seeding categories table...');

        try {
            // delete category_translations for related mall
            CategoryTranslation::whereHas('category', function($q) {
                    $q->where('merchant_id', TakashimayaMerchantSeeder::MALL_ID);
                })
                ->delete();

            // delete categories for related mall
            Category::where('merchant_id', TakashimayaMerchantSeeder::MALL_ID)
                ->delete();

        } catch (Illuminate\Database\QueryException $e) {
        }

        $mall = Mall::where('merchant_id', TakashimayaMerchantSeeder::MALL_ID)->first();

        $languages_by_name = [];
        foreach ($mall->languages as $language) {
            $name = $language->language->name;
            $languages_by_name[$name] = $language;
        }

        foreach ($categories as $index => $category) {
            $category = trim($category);

            $this->command->info(sprintf('    Create record for category %s.', $category));

            $record = [
                'merchant_id'       => $mall->merchant_id,
                'category_name'     => $category,
                'category_level'    => 1,
                'category_order'    => 0,
                'status'            => 'active',
                'created_by'        => NULL,
                'modified_by'       => NULL
            ];

            Category::unguard();
            $new_category = Category::create($record);

            foreach (['zh', 'ja'] as $lang) {
                $this->command->info(sprintf('    Create translation record for category %s language %s.', $category, $lang));
                CategoryTranslation::unguard();
                $record = [
                    'category_id' => $new_category->category_id,
                    'merchant_language_id' => $languages_by_name[$lang]->merchant_language_id,
                    'category_name' => trim($category_translations[$lang][$index]),
                    'status' => 'active',
                    'created_by'        => NULL,
                    'modified_by'       => NULL
                ];
                CategoryTranslation::create($record);
            }

        }

        $this->command->info('categories table seeded.');
    }
}
