<?php
use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Test handling of translations when updating a Tenant.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Merchant $unrelatedGroup
 * @property Merchant $mall
 */
class postUpdateTenant_TranslationsTest extends TestCase
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

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes']);

        $setting = new Setting();
        $setting->setting_name = 'current_retailer';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'update_retailer']);

        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $merchant->user->user_id]);

        $combos = [
            [$merchant, $english, 'english'],
            [$merchant, $french, 'french'],
            [$merchant, $balinese, 'deleted_balinese'],
            [$unrelatedMerchant, $balinese, 'balinese'],
            [$unrelatedMerchant, $chinese, 'chinese']
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

        Factory::create('Role', ['role_name' => 'retailer owner']); // must exist to create a tenant

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // must exist to populate user_ip
    }

    private function makeRequest($tenant, $translations)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = [
            'retailer_id' => $tenant->merchant_id,
            'translations' => json_encode($translations),
        ];

        $url = '/api/v1/tenant/update?' . http_build_query($_GET);

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

    private function createTenant()
    {
        $faker = Faker\Factory::create();
        return Factory::create('Retailer', [
            'parent_id' => $this->mall->merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);
    }

    private function createTenantWithTranslation($merchant_language_name)
    {
        $tenant = $this->createTenant();
        $translation = new MerchantTranslation();
        $translation->merchant_id = $tenant->merchant_id;
        $translation->merchant_language_id = $this->merchantLanguages[$merchant_language_name]->merchant_language_id;
        $translation->name = 'Translated name';
        $translation->description = 'Translated description';
        $translation->ticket_header = 'Translated header';
        $translation->ticket_footer = 'Translated footer';
        $translation->save();

        return [$tenant, $translation];
    }

    // with no translations, add translation
    public function testAddTranslationsWithNoExistingTranslations()
    {
        $tenant = $this->createTenant();
        $english_translations = [
            'name' => 'English name',
            'description' => 'English description',
            'ticket_header' => 'English header',
            'ticket_footer' => 'English footer',
        ];
        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseOk($response);

        $saved_translation = MerchantTranslation::where('merchant_id', '=', $tenant->merchant_id)
            ->where('merchant_language_id', '=', $this->merchantLanguages['english']->merchant_language_id)
            ->first();
        $this->assertNotNull($saved_translation);
        foreach ($english_translations as $key => $value) {
            $this->assertSame($value, $saved_translation->{$key});
        }
    }

    // ... for a nonexistent language
    public function testAddTranslationForNonexistentLanguage()
    {
        $tenant = $this->createTenant();
        $english_translations = [
            'name' => 'English name',
            'description' => 'English description',
            'ticket_header' => 'English header',
            'ticket_footer' => 'English footer',
        ];
        $translations = [
            '999999' => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count = MerchantTranslation::where('merchant_id', '=', $tenant->merchant_id)->count();
        $this->assertSame(0, $translation_count);
    }

    // ... for a deleted language
    public function testAddTranslationForDeletedLanguage()
    {
        $tenant = $this->createTenant();
        $english_translations = [
            'name' => 'English name',
            'description' => 'English description',
            'ticket_header' => 'English header',
            'ticket_footer' => 'English footer',
        ];
        $translations = [
            $this->merchantLanguages['deleted_balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count = MerchantTranslation::where('merchant_id', '=', $tenant->merchant_id)->count();
        $this->assertSame(0, $translation_count);
    }

    // ... for a language belonging to another merchant
    public function testAddTranslationForOtherMerchantLanguage()
    {
        $tenant = $this->createTenant();
        $english_translations = [
            'name' => 'English name',
            'description' => 'English description',
            'ticket_header' => 'English header',
            'ticket_footer' => 'English footer',
        ];
        $translations = [
            $this->merchantLanguages['balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count = MerchantTranslation::where('merchant_id', '=', $tenant->merchant_id)->count();
        $this->assertSame(0, $translation_count);
    }

    // with a translation, delete translation
    public function testDeletingTranslation()
    {
        list($product, $translation) = $this->createTenantWithTranslation('english');
        $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_before);

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => null
        ];

        $response = $this->makeRequest($product, $translations);
        $this->assertJsonResponseOk($response);

        $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(0, $translation_count_after);
    }

    // ... for a nonexistent language
    public function testDeletingNonexistentTranslation()
    {
        list($product, $translation) = $this->createTenantWithTranslation('english');
        $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_before);
        $translations = [
            $this->merchantLanguages['french']->merchant_language_id => null
        ];
        $response = $this->makeRequest($product, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_after);
    }

    // ... for a language belonging to another merchant
    public function testDeletingOtherMerchantLanguage()
    {
        list($product, $translation) = $this->createTenantWithTranslation('english');
        $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_before);
        $translations = [
            $this->merchantLanguages['chinese']->merchant_language_id => null
        ];
        $response = $this->makeRequest($product, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_after);
    }

    // with a translation, update translation
    public function testUpdatingTranslation()
    {
        list($product, $translation) = $this->createTenantWithTranslation('english');
        $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_before);

        $updated_english = [
            'name' => 'English name',
            'description' => 'English description',
            'ticket_header' => 'English header',
            'ticket_footer' => 'English footer',
        ];
        foreach ($updated_english as $k => $v) {
            $this->assertNotSame($v, $translation->{$k});
        }

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => $updated_english
        ];
        $response = $this->makeRequest($product, $translations);
        $this->assertJsonResponseOk($response);

        $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_after);

        $updated_translation = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->first();

        foreach ($updated_english as $k => $v) {
            $this->assertSame($v, $updated_translation->{$k});
        }
    }

    // ... with some fields left unspecified
    public function testUpdatingTranslationWithUnspecifiedFields()
    {
        $updated_english = [
            'name' => 'English name',
            'description' => 'English description',
            'ticket_header' => 'English header',
            'ticket_footer' => 'English footer',
        ];

        foreach ($updated_english as $field => $value) {
            $minimal_update = [$field => $value];

            list($product, $original_translation) = $this->createTenantWithTranslation('english');
            $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
                $product->merchant_id)->count();
            $this->assertSame(1, $translation_count_before);

            foreach ($minimal_update as $k => $v) {
                $this->assertNotSame($v, $original_translation->{$k});
            }

            $translations = [
                $this->merchantLanguages['english']->merchant_language_id => $minimal_update
            ];
            $response = $this->makeRequest($product, $translations);
            $this->assertJsonResponseOk($response);

            $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
                $product->merchant_id)->count();
            $this->assertSame(1, $translation_count_after);

            $updated_translation = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
                $product->merchant_id)->first();

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
        list($product, $translation) = $this->createTenantWithTranslation('english');
        $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_before);

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => [
                'name' => null,
                'description' => null,
                'ticket_header' => null,
                'ticket_footer' => null,
            ]
        ];
        $response = $this->makeRequest($product, $translations);
        $this->assertJsonResponseOk($response);

        $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_after);

        $updated_translation = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->first();

        foreach (['product_name', 'short_description', 'long_description', 'in_store_localization'] as $k) {
            $this->assertNull($updated_translation->{$k});
        }
    }

    // ... with some illegal fields
    public function testUpdatingTranslationWithIllegalFields()
    {
        list($product, $translation) = $this->createTenantWithTranslation('english');
        $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_before);

        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => [
                'this' => 'should not be here',
                'product_name' => 'should not be updated'
            ]
        ];
        $response = $this->makeRequest($product, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/invalid key/i', $response);

        $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->count();
        $this->assertSame(1, $translation_count_after);

        $updated_translation = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
            $product->merchant_id)->first();

        foreach (['product_name', 'short_description', 'long_description', 'in_store_localization'] as $k) {
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
            list($product, $translation) = $this->createTenantWithTranslation('english');
            $translation_count_before = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
                $product->merchant_id)->count();
            $this->assertSame(1, $translation_count_before);

            $translations = [
                $this->merchantLanguages['english']->merchant_language_id => [
                    'description' => $illegal_value,
                    'name' => 'should not be updated'
                ]
            ];
            $response = $this->makeRequest($product, $translations);
            $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/invalid value/i', $response);

            $translation_count_after = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
                $product->merchant_id)->count();
            $this->assertSame(1, $translation_count_after);

            $updated_translation = MerchantTranslation::excludeDeleted()->where('merchant_id', '=',
                $product->merchant_id)->first();

            foreach (['product_name', 'short_description', 'long_description', 'in_store_localization'] as $k) {
                $this->assertSame($translation->{$k}, $updated_translation->{$k});
            }
        }
    }

}
