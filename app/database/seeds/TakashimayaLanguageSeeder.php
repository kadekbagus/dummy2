<?php
class TakashimayaLanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('merchant_languages')->truncate();

        $mall_id = TakashimayaMerchantSeeder::MALL_ID;

        $languages = ['en', 'zh', 'ja'];
        foreach ($languages as $language_name) {
            $merchant_language = new MerchantLanguage();
            $merchant_language->merchant_id = $mall_id;
            $merchant_language->language_id = Language::where('name', '=', $language_name)->first()->language_id;
            $merchant_language->save();
        }
    }
}
