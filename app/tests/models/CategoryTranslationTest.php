<?php

use Laracasts\TestDummy\Factory;

class CategoryTranslationTest extends TestCase
{
    public function testCategoryTranslation()
    {
        $english = new Language();
        $english->name = 'English';
        $english->save();

        $category = Factory::create('Merchant');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $category->merchant_id;
        $merchant_language->language_id = $english->language_id;
        $merchant_language->save();

        $mall = Factory::create('Retailer', ['is_mall' => 'yes', 'parent_id' => $category->merchant_id]);

        $category = Factory::create('Category', ['merchant_id' => $mall->merchant_id]);

        $translation = new CategoryTranslation();
        $translation->merchant_language_id = $merchant_language->merchant_language_id;
        $translation->category_id = $category->category_id;
        $translation->category_name = 'English name';
        $translation->description = 'English description';
        $translation->save();

        $this->assertCount(1, $category->translations);
        $this->assertSame('English', $category->translations[0]->language->language->name);
        $this->assertSame('English name', $category->translations[0]->category_name);
        $this->assertSame('English description', $category->translations[0]->description);
    }
}
