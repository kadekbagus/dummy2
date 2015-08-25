<?php
class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Seeding master language data...');
        try {
            DB::table('languages')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        $languages = [
            'en' => 'English', 
            'zh' => 'Chinese', 
            'ja' => 'Japan'
        ];

        foreach ($languages as $code=>$language_name) {
            $language = new Language();
            $language->name = $code;
            $language->name_long = $language_name;
            $language->save();
        }
    }
}