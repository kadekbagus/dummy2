<?php

use Laracasts\TestDummy\Factory;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests handling of translations when creating new Category.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Merchant $unrelatedGroup
 * @property Retailer $mall
 * @property Retailer $unrelatedMall
 *
 * @property Apikey $authData
 * @property int $userId
 */
class postNewCategory_TranslationsTest extends TestCase
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

        $owner_role = Factory::create('Role', ['role_name' => 'mall owner']);

        $owner_user = Factory::create('User', ['user_role_id' => $owner_role->role_id]);

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes', 'user_id' => $owner_user->user_id]);
        $this->unrelatedMall = Factory::create('Retailer', ['is_mall' => 'yes', 'parent_id' => $this->unrelatedGroup->merchant_id]);

        $setting = new Setting();
        $setting->setting_name = 'current_retailer';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'create_category']);

        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $owner_user->user_id]);
        $this->userId = $owner_user->user_id;

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

    private function makeRequest($category_data, $translations)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = array_merge($category_data, [
            'translations' => json_encode($translations)
        ]);

        $_POST['id_language_default'] = 1;

        $url = '/api/v1/family/new?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createCategoryData()
    {
        $faker = Faker\Factory::create();
        return Factory::attributesFor('Category', [
            'merchant_id' => $this->mall->merchant_id,
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
        $category = $this->createCategoryData();
        $english_translations = [
            'category_name' => 'English Name',
            'description' => 'English Description',
        ];
        $translations = [
            $this->merchantLanguages['english']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($category, $translations);
        $this->assertJsonResponseOk($response);

        $saved_translation = CategoryTranslation::where('category_id', '=', $response->data->category_id)
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
        $category = $this->createCategoryData();
        $english_translations = [
            'category_name' => 'English Name',
            'description' => 'English Description',
        ];
        $translations = [
            '999999' => $english_translations
        ];
        $response = $this->makeRequest($category, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $count_after = Mall::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // ... for a deleted language
    public function testAddTranslationForDeletedLanguage()
    {
        $count_before = Category::excludeDeleted()->count();
        $category = $this->createCategoryData();
        $english_translations = [
            'category_name' => 'English Name',
            'description' => 'English Description',
        ];
        $translations = [
            $this->merchantLanguages['deleted_balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($category, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $count_after = Category::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // ... for a language belonging to another merchant
    public function testAddTranslationForOtherMerchantLanguage()
    {
        $count_before = Category::excludeDeleted()->count();
        $category = $this->createCategoryData();
        $english_translations = [
            'category_name' => 'English Name',
            'description' => 'English Description',
        ];
        $translations = [
            $this->merchantLanguages['balinese']->merchant_language_id => $english_translations
        ];
        $response = $this->makeRequest($category, $translations);
        $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);

        $count_after = Category::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }

    // should not be able to delete, ever.
    public function testDeletingTranslation()
    {
        $count_before = Category::excludeDeleted()->count();
        $category = $this->createCategoryData();

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
            $response = $this->makeRequest($category, $translations);
            $this->assertJsonResponseMatchesRegExp(Status::INVALID_ARGUMENT, 'error', '/language.*not found/i', $response);
        }

        $count_after = Category::excludeDeleted()->count();
        $this->assertSame($count_before, $count_after);
    }



}
