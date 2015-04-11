<?php
/**
 * Seeder for Category, categories are linked to merchant_id 3
 * the 'Lippo Mall Puri'.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class CategoryTableSeeder extends Seeder
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

        $categories = explode("\n", $sources);

        $this->command->info('Seeding categories table...');

        try {
            DB::table('categories')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        $lippoMallPuri = Retailer::where('merchant_id', 2)->first();

        foreach ($categories as $category) {
            $category = trim($category);

            $this->command->info(sprintf('    Create record for category %s.', $category));

            $record = [
                'merchant_id'       => $lippoMallPuri->merchant_id,
                'category_name'     => $category,
                'category_level'    => 1,
                'category_order'    => 0,
                'status'            => 'active',
                'created_by'        => NULL,
                'modified_by'       => NULL
            ];

            Category::unguard();
            Category::create($record);
        }

        $this->command->info('categories table seeded.');
    }
}
