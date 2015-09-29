<?php
/**
 * Seeder for Category, categories are linked to merchant_id 3
 * the 'Takashimaya Shopping Center'.
 *
 * @author Rio Astamal <me@rioastamal.net>
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
Beauty & Personal Care
Books
Children Related & Education
Department Store
Electronics & Gadgets
Fashion & Accessories
Food & Beverages
Gifts
Health
Hobbies & Music
Home Related
Jewellery
Services
Sports Related
Watches & Optics
CATEGORIES;

        $sources_zh = <<<CATEGORIES
美容及个人护理
书籍
相关儿童与教育
百货商店
电子及配件
时装及配饰
食品和饮料
礼品
健康
爱好与音乐
家庭关系
珠宝
服务
体育相关
钟表及光学
CATEGORIES;

        $sources_ja = <<<CATEGORIES
ビューティー＆パーソナルケア
図書
子供関連・教育
デパート
エレクトロニクス＆ガジェット
ファッション＆アクセサリー
食品＆飲料
ギフト
健康
趣味・音楽
ホーム関連
ジュエリー
サービス
スポーツ関連
時計＆オプティクス
CATEGORIES;



        $categories = explode("\n", $sources);
        $category_translations = [];
        $category_translations['zh'] = explode("\n", $sources_zh);
        $category_translations['ja'] = explode("\n", $sources_ja);

        $this->command->info('Seeding categories table...');

        try {
            DB::table('categories')->truncate();
            DB::table('category_translations')->truncate();
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
