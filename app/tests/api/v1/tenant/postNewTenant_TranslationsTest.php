<?php

use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests handling of translations when creating new Tenant.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Merchant $unrelatedGroup
 * @property Retailer $mall
 *
 * @property Apikey $authData
 * @property int $userId
 */
class postNewTenant_TranslationsTest extends TestCase
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

        $permission = Factory::create('Permission', ['permission_name' => 'create_retailer']);

        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $merchant->user->user_id]);
        $this->userId = $merchant->user->user_id;

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

    private function makeRequest($tenant_data, $translations)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = array_merge($tenant_data, [
            'translations' => json_encode($translations)
        ]);

        $url = '/api/v1/tenant/new?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createTenantData()
    {
        $faker = Faker\Factory::create();
        return Factory::attributesFor('Retailer', [
            'parent_id' => $this->mall->merchant_id,
            'email' => $faker->email,
            'external_object_id' => $faker->uuid,
            'is_mall' => 'no',
        ]);
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

    // with no translations, add translation
    public function testAddTranslation()
    {
        $tenant = $this->createTenantData();
        $english_translations = [
            'name' => 'English Name',
            'description' => 'English Description',
            'ticket_header' => 'English Header',
            'ticket_footer' => 'English Footer',
        ];
        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseOk($response);

        $saved_translation = MerchantTranslation::where('merchant_id', '=', $response->data->merchant_id)
            ->where('merchant_language_id', '=', $this->merchantLanguages['english']->merchant_language_id)
            ->first();
        $this->assertNotNull($saved_translation);
        foreach ($english_translations as $key => $value) {
            $this->assertSame($value, $saved_translation->{$key});
        }
        $this->assertSame((string)$this->userId, (string)$saved_translation->created_by);
        $this->assertSame((string)$this->userId, (string)$saved_translation->modified_by);
    }

    // ... for a nonexistent language
    public function testAddTranslationForNonexistentLanguage()
    {
        $count_before = Mall::excludeDeleted()->count();
        $tenant = $this->createTenantData();
        $english_translations = [
            'name' => 'English Name',
            'description' => 'English Description',
            'ticket_header' => 'English Header',
            'ticket_footer' => 'English Footer',
        ];
        $translations = [
            '999999' => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // ... for a deleted language
    public function testAddTranslationForDeletedLanguage()
    {
        $count_before = Mall::excludeDeleted()->count();
        $tenant = $this->createTenantData();
        $english_translations = [
            'name' => 'English Name',
            'description' => 'English Description',
            'ticket_header' => 'English Header',
            'ticket_footer' => 'English Footer',
        ];
        $translations = [
            $this->merchantLanguages['deleted_balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // ... for a language belonging to another merchant
    public function testAddTranslationForOtherMerchantLanguage()
    {
        $count_before = Mall::excludeDeleted()->count();
        $tenant = $this->createTenantData();
        $english_translations = [
            'name' => 'English Name',
            'description' => 'English Description',
            'ticket_header' => 'English Header',
            'ticket_footer' => 'English Footer',
        ];
        $translations = [
            $this->merchantLanguages['balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($tenant, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // should not be able to delete, ever.
    public function testDeletingTranslation()
    {
        $count_before = Mall::excludeDeleted()->count();
        $tenant = $this->createTenantData();

        $merchant_languages_to_try = [
            $this->merchantLanguages['english']->merchant_language_id,
            $this->merchantLanguages['french']->merchant_language_id,
            $this->merchantLanguages['chinese']->merchant_language_id,
            999999,
        ];

        foreach ($merchant_languages_to_try as $language) {
            $translations = [
                $language => null
            ];
            $response = $this->makeRequest($tenant, $translations);
            $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);
        }

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }



}
