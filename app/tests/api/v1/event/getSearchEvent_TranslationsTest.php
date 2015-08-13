<?php

use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests handling of translations when listing / getting Events.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Retailer $mall
 */
class getSearchEvent_TranslationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $english = Factory::create('Language', ['name' => 'English']);
        $french = Factory::create('Language', ['name' => 'French']);
        $balinese = Factory::create('Language', ['name' => 'Balinese']);

        $this->group = $merchant = Factory::create('Merchant');

        $owner_role = Factory::create('Role', ['role_name' => 'mall owner']);

        $owner_user = Factory::create('User', ['user_role_id' => $owner_role->role_id]);

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes', 'user_id' => $owner_user]);

        $setting = new Setting();
        $setting->setting_name = 'current_retailer';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'view_event']);

        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $owner_user->user_id]);

        $combos = [
            [$merchant, $english, 'english'],
            [$merchant, $french, 'french'],
        ];
        $merchant_languages = [];
        foreach ($combos as $combo) {
            $lang = new MerchantLanguage();
            $lang->merchant_id = $combo[0]->merchant_id;
            $lang->language_id = $combo[1]->language_id;
            $lang->save();
            $merchant_languages[$combo[2]] = $lang;
        }

        $this->merchantLanguages = $merchant_languages;
    }

    private function makeRequest($get_data)
    {
        $_GET = array_merge([
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ], $get_data);

        $_POST = [];

        $url = '/api/v1/event/search?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createEvent()
    {
        return Factory::create('EventModel', [
            'merchant_id' => $this->mall->merchant_id,
        ]);
    }

    private function createEventWithTranslation($merchant_language_names)
    {
        if (is_string($merchant_language_names)) {
            $merchant_language_names = [$merchant_language_names];
        }

        $event = $this->createEvent();
        $translations = [];
        foreach ($merchant_language_names as $name) {
            $translation = new EventTranslation();
            $translation->event_id = $event->event_id;
            $translation->merchant_language_id = $this->merchantLanguages[$name]->merchant_language_id;
            $translation->event_name = 'Translated name ' . $name;
            $translation->description = 'Translated description ' . $name;
            $translation->save();
            $translations[$name] = $translation;
        }

        return [$event, $translations];
    }


    /**
     * @param object $response
     */
    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    private function assertJsonResponseMatchesRegExp(
        $expected_code,
        $expected_status,
        $expected_message_regexp,
        $response
    ) {
        $this->assertRegExp($expected_message_regexp, $response->message);
        $this->assertSame($expected_status, $response->status);
        $this->assertSame($expected_code, (int)$response->code);
    }

    /**
     * @param int $expected_total
     * @param int $expected_returned
     * @param $response
     */
    private function assertResponseDataCount($expected_total, $expected_returned, $response)
    {
        $this->assertSame($expected_total, $response->data->total_records);
        $this->assertSame($expected_returned, $response->data->returned_records);
        $this->assertCount($expected_returned, $response->data->records);
    }
    /**
     * @param $response
     * @param EventModel[] $events
     */
    private function assertResponseDataRecordsContains($response, $events)
    {
        $event_ids = array_map(function ($e) {
            return $e->event_id;
        }, $events);
        $found = array();
        foreach ($response->data->records as $event) {
            $this->assertContains($event->event_id, $event_ids);
            $found[$event->event_id] = true;
        }
        $this->assertSame(count($found), count($events));
    }

    // with no translation, no with, return nothing
    public function testNoTranslationNoWithDoesNotReturnTranslations()
    {
        $event = $this->createEvent();
        $response = $this->makeRequest([]);
        $this->assertJsonResponseOk($response);
        $this->assertResponseDataCount(1, 1, $response);
        $this->assertResponseDataRecordsContains($response, [$event]);

        $this->assertObjectNotHasAttribute('translations', $response->data->records[0]);
    }

    // with no translation, specify with, return empty
    public function testNoTranslationWithTranslationReturnsNoTranslations()
    {
        $event = $this->createEvent();
        $response = $this->makeRequest(['with' => ['translations']]);
        $this->assertJsonResponseOk($response);
        $this->assertResponseDataCount(1, 1, $response);
        $this->assertResponseDataRecordsContains($response, [$event]);

        $this->assertObjectHasAttribute('translations', $response->data->records[0]);
        $this->assertCount(0, $response->data->records[0]->translations);
    }

    // with translations, no with, return nothing
    public function testHasTranslationNoWithDoesNotReturnTranslations()
    {
        list($event, $translations) = $this->createEventWithTranslation(['english', 'french']);
        $response = $this->makeRequest([]);
        $this->assertJsonResponseOk($response);
        $this->assertResponseDataCount(1, 1, $response);
        $this->assertResponseDataRecordsContains($response, [$event]);

        $this->assertObjectNotHasAttribute('translations', $response->data->records[0]);
    }

    // with translations, specify with, return translations
    public function testHasTranslationWithTranslationReturnsTranslations()
    {
        list($event, $translations) = $this->createEventWithTranslation(['english', 'french']);
        $response = $this->makeRequest(['with' => ['translations']]);
        $this->assertJsonResponseOk($response);
        $this->assertResponseDataCount(1, 1, $response);
        $this->assertResponseDataRecordsContains($response, [$event]);

        $this->assertObjectHasAttribute('translations', $response->data->records[0]);
        $returned_translations = $response->data->records[0]->translations;
        $this->assertCount(2, $returned_translations);

        $compare = [$translations['english'], $translations['french']];
        if ((string)$returned_translations[0]->event_translation_id !== (string)$translations['english']->event_translation_id) {
            $compare = [$translations['french'], $translations['english']];
        }
        for ($i = 0; $i < 2; $i++) {
            $returned_translation = $returned_translations[$i];
            $translation = $compare[$i];
            foreach (['merchant_language_id', 'event_name', 'description'] as $field) {
                $this->assertSame((string)$translation->{$field}, (string)$returned_translation->{$field});
            }
        }
    }
}
