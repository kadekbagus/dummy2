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
Cafe
Cafe & Bakery
Chinese Casual Dining
Chinese Cuisine
Confectionery
Cosmetics & Skincare
Educational Toys & Enrichment Centres
Fashion, Accessories & Shoes
Fine Jewellery & Watches
Fragrance & Body Care
Gifts/Hobbies/Toys
Gourmet Confectionery
Hair & Beauty
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
zh:ATM
zh:Appliances
zh:Cafe
zh:Cafe & Bakery
zh:Chinese Casual Dining
zh:Chinese Cuisine
zh:Confectionery
zh:Cosmetics & Skincare
zh:Educational Toys & Enrichment Centres
zh:Fashion, Accessories & Shoes
zh:Fine Jewellery & Watches
zh:Fragrance & Body Care
zh:Gifts/Hobbies/Toys
zh:Gourmet Confectionery
zh:Hair & Beauty
zh:Hairdressing & Beauty Salon
zh:Home Decorative
zh:International Fashion & Accessories
zh:International Fashion, Accessories & Shoes
zh:International Jewellery, Watches & Writing Instruments
zh:Japanese Casual Dining
zh:Japanese Cuisine
zh:Korean Cuisine
zh:Leather Goods & Shoes
zh:Money Changer
zh:Optical Services
zh:Pharmacy, Health & Toiletries
zh:Restaurants
zh:Services
zh:Shoes Repair & Key Services
zh:Sports Apparel, Equipment & Accessories
zh:Travel & Lifestyle
zh:Western Casual Dining
zh:Western Cuisine
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
