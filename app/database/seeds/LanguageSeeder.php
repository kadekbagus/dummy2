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
        $languages = ['en', 'zh', 'ja'];
        foreach ($languages as $language_name) {
            $language = new Language();
            $language->name = $language_name;
            $language->save();
        }
    }
}