<?php

use Laracasts\TestDummy\Factory;

class MerchantTranslationTest extends TestCase
{
    public function testMerchantTranslation()
    {
        $english = new Language();
        $english->name = 'English';
        $english->save();

        $merchant = Factory::create('Merchant');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $merchant->merchant_id;
        $merchant_language->language_id = $english->language_id;
        $merchant_language->save();

        $translation = new MerchantTranslation();
        $translation->merchant_language_id = $merchant_language->merchant_language_id;
        $translation->merchant_id = $merchant->merchant_id;
        $translation->name = 'English name';
        $translation->description = 'English description';
        $translation->ticket_header = 'English header';
        $translation->ticket_footer = 'English footer';
        $translation->save();



        $this->assertCount(1, $merchant->translations);
        $this->assertSame('English', $merchant->translations[0]->language->language->name);
        $this->assertSame('English name', $merchant->translations[0]->name);
        $this->assertSame('English description', $merchant->translations[0]->description);
        $this->assertSame('English header', $merchant->translations[0]->ticket_header);
        $this->assertSame('English footer', $merchant->translations[0]->ticket_footer);
    }

    public function testRetailerTranslation()
    {
        $english = new Language();
        $english->name = 'English';
        $english->save();

        $merchant = Factory::create('Merchant');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $merchant->merchant_id;
        $merchant_language->language_id = $english->language_id;
        $merchant_language->save();

        $retailer = Factory::create('Retailer', ['parent_id' => $merchant->merchant_id]);

        $translation = new MerchantTranslation();
        $translation->merchant_language_id = $merchant_language->merchant_language_id;
        $translation->merchant_id = $retailer->merchant_id;
        $translation->name = 'English name';
        $translation->description = 'English description';
        $translation->ticket_header = 'English header';
        $translation->ticket_footer = 'English footer';
        $translation->save();



        $this->assertCount(1, $retailer->translations);
        $this->assertSame('English', $retailer->translations[0]->language->language->name);
        $this->assertSame('English name', $retailer->translations[0]->name);
        $this->assertSame('English description', $retailer->translations[0]->description);
        $this->assertSame('English header', $retailer->translations[0]->ticket_header);
        $this->assertSame('English footer', $retailer->translations[0]->ticket_footer);
    }
}
