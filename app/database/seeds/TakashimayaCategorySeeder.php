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
自動支払機
家電機器
喫茶店及びパン屋
喫茶店
中国のカジュアル ダイニング
中華料理
お菓子屋
化粧品及びスキン ケア
教育おもちゃ
ファッション、アクセサリー靴
良い宝石類及び腕時計
フレグランス ボディケア
ギフト/趣味/おもちゃ
グルメ向きのお菓子屋
理髪及び美容院
家の装飾的
国際的な方法及び付属品
国際的な方法、付属品及び靴
国際的な宝石類、腕時計及び執筆器械
日本の偶然の食事
日本の料理
韓国の料理
革商品及び靴
お金チェンジャー
光学サービス
薬学、健康及び洗面用品
レストラン
サービス
靴は及びキー サービス修理する
スポーツ服装、装置及び付属品
旅行及び生活様式
西部の偶然の食事
西部の料理
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
