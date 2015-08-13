<?php

use Laracasts\TestDummy\Factory;

class EventTranslationTest extends TestCase
{
    public function testEventTranslation()
    {
        $english = new Language();
        $english->name = 'English';
        $english->save();

        $event = Factory::create('Merchant');
        $merchant_language = new MerchantLanguage();
        $merchant_language->merchant_id = $event->merchant_id;
        $merchant_language->language_id = $english->language_id;
        $merchant_language->save();

        $event = Factory::create('EventModel', ['merchant_id' => $event->merchant_id]);

        $translation = new EventTranslation();
        $translation->merchant_language_id = $merchant_language->merchant_language_id;
        $translation->event_id = $event->event_id;
        $translation->event_name = 'English name';
        $translation->description = 'English description';
        $translation->save();



        $this->assertCount(1, $event->translations);
        $this->assertSame('English', $event->translations[0]->language->language->name);
        $this->assertSame('English name', $event->translations[0]->event_name);
        $this->assertSame('English description', $event->translations[0]->description);
    }
}
