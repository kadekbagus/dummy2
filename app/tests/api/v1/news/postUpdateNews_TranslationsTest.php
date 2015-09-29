<?php
use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Test handling of translations when updating a News.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Merchant $unrelatedGroup
 * @property Retailer $mall
 * @property Retailer $unrelatedMall
 *
 * @property int $userId
 * @property Apikey $authData
 */
class postUpdateNews_TranslationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $english = Factory::create('Language', ['name' => 'English']);
        $chinese = Factory::create('Language', ['name' => 'Chinese']);
        $french = Factory::create('Language', ['name' => 'French']);
        $balinese = Factory::create('Language', ['name' => 'Balinese']);

        $this->group = $merchant = Factory::create('Merchant');
        $this->unrelatedGroup = $unrelatedMerchant = Factory::create('Merchant');

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes', 'parent_id' => $this->group->merchant_id]);
        $this->unrelatedMall = Factory::create('Retailer', ['is_mall' => 'yes', 'parent_id' => $this->unrelatedGroup->merchant_id]);

        $setting = new Setting();
        $setting->setting_name = 'current_retailer';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'update_news']);

        Factory::create('PermissionRole',
            ['role_id' => $this->mall->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $this->mall->user->user_id]);
        $this->userId = $this->mall->user->user_id;

        $combos = [
            [$this->mall, $english, 'english'],
            [$this->mall, $french, 'french'],
            [$this->mall, $balinese, 'deleted_balinese'],
            [$this->unrelatedMall, $balinese, 'balinese'],
            [$this->unrelatedMall, $chinese, 'chinese']
        ];
        $merchant_languages = [];
        foreach ($combos as $combo) {
            $lang = new MerchantLanguage();
            $lang->merchant_id = $combo[0]->merchant_id;
            $lang->language_id = $combo[1]->language_id;
            $lang->save();
            $merchant_languages[$combo[2]] = $lang;
        }
        $merchant_languages['deleted_balinese']->delete();

        $this->merchantLanguages = $merchant_languages;

    }

    private function makeRequest($news, $translations)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = [
            'news_id' => $news->news_id,
            'translations' => json_encode($translations),
            'id_language_default' => 1,
        ];

        $url = '/api/v1/family/update?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    private function assertJsonResponseMatches($expected_code, $expected_status, $expected_message, $response)
    {
        $this->assertSame($expected_message, $response->message);
        $this->assertSame($expected_status, $response->status);
        $this->assertSame($expected_code, (int)$response->code);
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

    private function createNews()
    {
        return Factory::create('News', [
            'mall_id' => $this->mall->merchant_id,
        ]);
    }

    private function createNewsWithTranslation($merchant_language_name)
    {
        $news = $this->createNews();
        $translation = new NewsTranslation();
        $translation->news_id = $news->news_id;
        $translation->merchant_language_id = $this->merchantLanguages[$merchant_language_name]->merchant_language_id;
        $translation->news_name = 'Translated name';
        $translation->description = 'Translated description';
        $translation->save();

        return [$news, $translation];
    }

    // with no translations, add translation
    public function testAddTranslationsWithNoExistingTranslations()
    {
        $news = $this->createNews();
        $english_translations = [
            'news_name' => 'English name',
            'description' => 'English description',
        ];
        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseOk($response);

        $saved_translation = NewsTranslation::where('news_id', '=', $news->news_id)
            ->where('merchant_language_id', '=', $this->merchantLanguages['english']->merchant_language_id)
            ->first();
        $this->assertNotNull($saved_translation);
        foreach ($english_translations as $key => $value) {
            $this->assertSame($value, $saved_translation->{$key});
        }
        $this->assertSame($this->userId, $saved_translation->created_by);
        $this->assertSame($this->userId, $saved_translation->modified_by);
    }

    // ... for a nonexistent language
    public function testAddTranslationForNonexistentLanguage()
    {
        $news = $this->createNews();
        $english_translations = [
            'name' => 'English name',
            'description' => 'English description',
        ];
        $translations = [
            '999999' => $english_translations
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count = NewsTranslation::where('news_id', '=', $news->news_id)->count();
        $this->assertSame(0, $translation_count);
    }

    // ... for a deleted language
    public function testAddTranslationForDeletedLanguage()
    {
        $news = $this->createNews();
        $english_translations = [
            'news_name' => 'English name',
            'description' => 'English description',
        ];
        $translations = [
            $this->merchantLanguages['deleted_balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count = NewsTranslation::where('news_id', '=', $news->news_id)->count();
        $this->assertSame(0, $translation_count);
    }

    // ... for a language belonging to another merchant
    public function testAddTranslationForOtherMerchantLanguage()
    {
        $news = $this->createNews();
        $english_translations = [
            'news_name' => 'English name',
            'description' => 'English description',
        ];
        $translations = [
            $this->merchantLanguages['balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count = NewsTranslation::where('news_id', '=', $news->news_id)->count();
        $this->assertSame(0, $translation_count);
    }

    // with a translation, delete translation
    public function testDeletingTranslation()
    {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $this->assertNull(
            NewsTranslation::find($translation->news_translation_id)->modified_by
        );
        $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_before);

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => null
        ];

        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseOk($response);

        $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(0, $translation_count_after);
        $this->assertSame(
            $this->userId,
            NewsTranslation::find($translation->news_translation_id)->modified_by
        );
    }

    // ... for a nonexistent language
    public function testDeletingNonexistentTranslation()
    {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_before);
        $translations = [
            $this->merchantLanguages['french']->merchant_language_id => null
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_after);
    }

    // ... for a language belonging to another merchant
    public function testDeletingOtherMerchantLanguage()
    {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_before);
        $translations = [
            $this->merchantLanguages['chinese']->merchant_language_id => null
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_after);
    }

    // with a translation, update translation
    public function testUpdatingTranslation()
    {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $this->assertNull(
            NewsTranslation::find($translation->news_translation_id)->modified_by
        );
        $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_before);

        $updated_english = [
            'news_name' => 'English name',
            'description' => 'English description',
        ];
        foreach ($updated_english as $k => $v) {
            $this->assertNotSame($v, $translation->{$k});
        }

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => $updated_english
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseOk($response);

        $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_after);

        /** @var NewsTranslation $updated_translation */
        $updated_translation = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->first();

        foreach ($updated_english as $k => $v) {
            $this->assertSame($v, $updated_translation->{$k});
        }
        $this->assertSame($this->userId, $updated_translation->modified_by);
    }

    // ... with some fields left unspecified
    public function testUpdatingTranslationWithUnspecifiedFields()
    {
        $updated_english = [
            'news_name' => 'English name',
            'description' => 'English description',
        ];

        foreach ($updated_english as $field => $value) {
            $minimal_update = [$field => $value];

            list($news, $original_translation) = $this->createNewsWithTranslation('english');
            $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
                $news->news_id)->count();
            $this->assertSame(1, $translation_count_before);

            foreach ($minimal_update as $k => $v) {
                $this->assertNotSame($v, $original_translation->{$k});
            }

            $translations = [
                $this->merchantLanguages['english']->merchant_language_id => $minimal_update
            ];
            $response = $this->makeRequest($news, $translations);
            $this->assertJsonResponseOk($response);

            $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
                $news->news_id)->count();
            $this->assertSame(1, $translation_count_after);

            $updated_translation = NewsTranslation::excludeDeleted()->where('news_id', '=',
                $news->news_id)->first();

            // the one sent is updated
            foreach ($minimal_update as $k => $v) {
                $this->assertSame($v, $updated_translation->{$k});
            }
            // the others are not
            foreach ($updated_english as $k => $v) {
                if ($k === $field) {
                    break;
                }
                $this->assertSame($original_translation->{$k}, $updated_translation->{$k});
            }
        }
    }

    // ... with null values
    public function testUpdatingTranslationWithNullValue()
    {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_before);

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => [
                'news_name' => null,
                'description' => null,
            ]
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseOk($response);

        $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_after);

        $updated_translation = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->first();

        foreach (['news_name', 'description'] as $k) {
            $this->assertNull($updated_translation->{$k});
        }
    }

    // ... with some illegal fields
    public function testUpdatingTranslationWithIllegalFields()
    {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_before);

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => [
                'this' => 'should not be here',
                'news_name' => 'should not be updated'
            ]
        ];
        $response = $this->makeRequest($news, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/invalid key/i', $response);

        $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->count();
        $this->assertSame(1, $translation_count_after);

        $updated_translation = NewsTranslation::excludeDeleted()->where('news_id', '=',
            $news->news_id)->first();

        foreach (['news_name', 'description'] as $k) {
            $this->assertSame($translation->{$k}, $updated_translation->{$k});
        }
    }

    // ... with fields having illegal values
    public function testUpdatingTranslationWithIllegalValues()
    {
        $illegal_values = [
            ['an', 'array', 'is', 'illegal'],
            ['and' => 'objects', 'are' => 'too'],
            true, // and booleans
            false,
            1234.56, // and numbers
        ];
        foreach ($illegal_values as $illegal_value) {
            list($news, $translation) = $this->createNewsWithTranslation('english');
            $translation_count_before = NewsTranslation::excludeDeleted()->where('news_id', '=',
                $news->news_id)->count();
            $this->assertSame(1, $translation_count_before);

            $translations = [
                $this->merchantLanguages['english']->merchant_language_id => [
                    'description' => $illegal_value,
                    'name' => 'should not be updated'
                ]
            ];
            $response = $this->makeRequest($news, $translations);
            $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/invalid value/i', $response);

            $translation_count_after = NewsTranslation::excludeDeleted()->where('news_id', '=',
                $news->news_id)->count();
            $this->assertSame(1, $translation_count_after);

            $updated_translation = NewsTranslation::excludeDeleted()->where('news_id', '=',
                $news->news_id)->first();

            foreach (['news_name', 'description'] as $k) {
                $this->assertSame($translation->{$k}, $updated_translation->{$k});
            }
        }
    }

}
