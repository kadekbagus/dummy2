<?php
use Laracasts\TestDummy\Factory;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests translations are deleted when categories are.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Retailer $mall
 *
 * @property Apikey $authData
 * @property int $userId
 */
class postDeleteCategory_TranslationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $english = Factory::create('Language', ['name' => 'English']);

        $this->group = $merchant = Factory::create('Merchant');

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes']);
        $role = $this->mall->user->role;
        $role->role_name = 'mall owner';
        $role->save();

        $setting = new Setting();
        $setting->setting_name = 'current_category';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'delete_category']);

        Factory::create('PermissionRole',
            ['role_id' => $this->mall->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $this->mall->user->user_id]);
        $this->userId = $this->mall->user->user_id;

        $english_merchant_language = new MerchantLanguage();
        $english_merchant_language->language_id = $english;
        $english_merchant_language->merchant_id = $merchant->merchant_id;
        $english_merchant_language->save();

        $this->merchantLanguages = [
            'english' => $english_merchant_language
        ];
    }

    private function makeRequest($category)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = [
            'category_id' => $category->category_id,
        ];

        $url = '/api/v1/family/delete?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createCategoryWithTranslation($merchant_language_name)
    {
        $category = Factory::create('Category', [
            'merchant_id' => $this->mall->merchant_id,
        ]);
        $translation = new CategoryTranslation();
        $translation->category_id = $category->category_id;
        $translation->merchant_language_id = $this->merchantLanguages[$merchant_language_name]->merchant_language_id;
        $translation->category_name = 'Translated name';
        $translation->description = 'Translated description';
        $translation->save();

        return [$category, $translation];
    }

    /**
     * @param object $response
     */
    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Category has been successfully deleted.', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    function testDeletingCategoryDeletesTranslations() {
        list($category, $translation) = $this->createCategoryWithTranslation('english');
        $this->assertNull(
            CategoryTranslation::find($translation->category_translation_id)->modified_by
        );
        $count_category_before = Category::excludeDeleted()->count();
        $count_translation_before = CategoryTranslation::excludeDeleted()->count();

        $response = $this->makeRequest($category);
        $this->assertJsonResponseOk($response);

        $count_category_after = Category::excludeDeleted()->count();
        $count_translation_after = CategoryTranslation::excludeDeleted()->count();
        $this->assertSame($count_category_before - 1, $count_category_after);
        $this->assertSame($count_translation_before - 1, $count_translation_after);
        $this->assertSame(
            $this->userId,
            CategoryTranslation::find($translation->category_translation_id)->modified_by
        );
    }

}
