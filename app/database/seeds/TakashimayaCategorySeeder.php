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
Chinese Casual Dining
Chinese Cuisine
Confectionery
Cosmetics & Skincare
Educational Toys & Enrichment Centres
Fashion & Accessories
Fashion Apparel
Fine Jewellery & Watches
Fragrance & Body Care
Gifts/Hobbies/Toys
Gourment Confectionery
Hair & Beauty
Hairdressing & Beauty Salon
Home Decorative
International Jeweller
Japanese Casual Dining
Japanese Cuisine
Korean Cuisine
Leather Goods & Shoes
Money Changer
Optical Services
Pharmac
Restaurants
Services
Shoes Repair & Key Services
Sports Apparel
Travel & Lifestyle
Western Casual Dining
Western Cuisine
CATEGORIES;

        $sources_zh = <<<CATEGORIES
ATM
Appliances
Cafe & Bakery
Chinese Casual Dining
Chinese Cuisine
Confectionery
Cosmetics & Skincare
Educational Toys & Enrichment Centres
Fashion & Accessories
Fashion Apparel
Fine Jewellery & Watches
Fragrance & Body Care
Gifts/Hobbies/Toys
Gourment Confectionery
Hair & Beauty
Hairdressing & Beauty Salon
Home Decorative
International Jeweller
Japanese Casual Dining
Japanese Cuisine
Korean Cuisine
Leather Goods & Shoes
Money Changer
Optical Services
Pharmac
Restaurants
Services
Shoes Repair & Key Services
Sports Apparel
Travel & Lifestyle
Western Casual Dining
Western Cuisine
CATEGORIES;

        $sources_ja = <<<CATEGORIES
ATM
Appliances
Cafe & Bakery
Chinese Casual Dining
Chinese Cuisine
Confectionery
Cosmetics & Skincare
Educational Toys & Enrichment Centres
Fashion & Accessories
Fashion Apparel
Fine Jewellery & Watches
Fragrance & Body Care
Gifts/Hobbies/Toys
Gourment Confectionery
Hair & Beauty
Hairdressing & Beauty Salon
Home Decorative
International Jeweller
Japanese Casual Dining
Japanese Cuisine
Korean Cuisine
Leather Goods & Shoes
Money Changer
Optical Services
Pharmac
Restaurants
Services
Shoes Repair & Key Services
Sports Apparel
Travel & Lifestyle
Western Casual Dining
Western Cuisine
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
