<?php

use Laracasts\TestDummy\Factory;

class LanguageTest extends TestCase
{

    public function testMerchantLanguage()
    {
        $english = new Language();
        $english->name = 'English';
        $english->save();

        /** @var Merchant $merchant */
        $merchant = Factory::create('Merchant');
        $this->assertCount(0, $merchant->languages);
        $merchant_language = new MerchantLanguage();
        $merchant_language->language_id = $english->language_id;
        $merchant_language->merchant_id = $merchant->merchant_id;
        $merchant_language->save();

        $merchant = Merchant::find($merchant->merchant_id);
        $this->assertCount(1, $merchant->languages);
        $lang = $merchant->languages[0];
        $this->assertSame('English', $lang->language->name);
        $this->assertSame($merchant->name, $lang->merchant->name);
    }

    public function testDeletedMerchantLanguageNotReturned()
    {
        $english = new Language();
        $english->name = 'English';
        $english->save();

        /** @var Merchant $merchant */
        $merchant = Factory::create('Merchant');
        $this->assertCount(0, $merchant->languages);
        $merchant_language = new MerchantLanguage();
        $merchant_language->language_id = $english->language_id;
        $merchant_language->merchant_id = $merchant->merchant_id;
        $merchant_language->save();
        $merchant_language->delete();

        $merchant = Merchant::find($merchant->merchant_id);
        $this->assertCount(0, $merchant->languages);
    }

    public function testMerchantLanguageScopeAllowedForUser()
    {
        $language = Factory::create('Language');
        $merchant = Factory::create('Merchant');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $merchant->merchant_id;
        $merchant_language->language_id = $language->language_id;
        $merchant_language->save();

        $other_merchant = Factory::create('Merchant');
        $this->assertSame(1, MerchantLanguage::allowedForUser($merchant->user)->count());
        $this->assertSame(0, MerchantLanguage::allowedForUser($other_merchant->user)->count());
    }

    public function testMerchantLanguageScopeAllowedForRetailerUser()
    {
        $language = Factory::create('Language');
        $retailer = Factory::create('Retailer');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $retailer->merchant_id;
        $merchant_language->language_id = $language->language_id;
        $merchant_language->save();

        $other_merchant = Factory::create('Merchant');
        $this->assertSame(1, MerchantLanguage::allowedForUser($retailer->user)->count());
        $this->assertSame(0, MerchantLanguage::allowedForUser($other_merchant->user)->count());
    }

    public function testMerchantLanguageMallRelation()
    {
        $language = Factory::create('Language');
        $retailer = Factory::create('Retailer');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $retailer->merchant_id;
        $merchant_language->language_id = $language->language_id;
        $merchant_language->save();

        $merchant_language = MerchantLanguage::find($merchant_language->merchant_language_id);
        $this->assertNotNull($merchant_language->mall);
        $this->assertSame((string)$retailer->merchant_id, (string)$merchant_language->mall->merchant_id);

    }
}
