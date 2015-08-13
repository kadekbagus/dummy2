<?php
use Laracasts\TestDummy\Factory;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests translations are deleted when tenants are.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Retailer $mall
 * @property string $masterPassword
 *
 * @property Apikey $authData
 * @property int $userId
 */
class postDeleteTenant_TranslationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $english = Factory::create('Language', ['name' => 'English']);

        $this->group = $merchant = Factory::create('Merchant');

        $this->mall = Factory::create('Retailer', ['is_mall' => 'yes']);

        $setting = new Setting();
        $setting->setting_name = 'current_retailer';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $password_setting = new Setting();
        $password_setting->setting_name = 'master_password';
        $password_setting->object_type = 'merchant';
        $password_setting->object_id = $merchant->object_id;
        $password_setting->setting_value = Hash::make($this->masterPassword = '12345');
        $password_setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'delete_retailer']);

        Factory::create('PermissionRole',
            ['role_id' => $merchant->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authData = Factory::create('Apikey', ['user_id' => $merchant->user->user_id]);
        $this->userId = $merchant->user->user_id;

        $english_merchant_language = new MerchantLanguage();
        $english_merchant_language->language_id = $english;
        $english_merchant_language->merchant_id = $merchant->merchant_id;
        $english_merchant_language->save();

        $this->merchantLanguages = [
            'english' => $english_merchant_language
        ];
    }

    private function makeRequest($tenant)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = [
            'retailer_id' => $tenant->merchant_id,
            'password' => $this->masterPassword
        ];

        $url = '/api/v1/tenant/delete?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createTenantWithTranslation($merchant_language_name)
    {
        $retailer = Factory::create('Retailer', [
            'parent_id' => $this->mall->merchant_id,
            'is_mall' => 'no',
        ]);
        $translation = new MerchantTranslation();
        $translation->merchant_id = $retailer->merchant_id;
        $translation->merchant_language_id = $this->merchantLanguages[$merchant_language_name]->merchant_language_id;
        $translation->name = 'Translated name';
        $translation->description = 'Translated description';
        $translation->ticket_header = 'Translated header';
        $translation->ticket_footer = 'Translated footer';
        $translation->save();

        return [$retailer, $translation];
    }

    /**
     * @param object $response
     */
    private function assertJsonResponseOk($response)
    {
        $this->assertSame('Retailer has been successfully deleted.', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    function testDeletingTenantDeletesTranslations() {
        list($tenant, $translation) = $this->createTenantWithTranslation('english');
        $this->assertNull(
            MerchantTranslation::find($translation->merchant_translation_id)->modified_by
        );
        $count_retailer_before = Retailer::excludeDeleted()->count();
        $count_translation_before = MerchantTranslation::excludeDeleted()->count();

        $response = $this->makeRequest($tenant);
        $this->assertJsonResponseOk($response);

        $count_retailer_after = Retailer::excludeDeleted()->count();
        $count_translation_after = MerchantTranslation::excludeDeleted()->count();
        $this->assertSame($count_retailer_before - 1, $count_retailer_after);
        $this->assertSame($count_translation_before - 1, $count_translation_after);
        $this->assertSame(
            $this->userId,
            MerchantTranslation::find($translation->merchant_translation_id)->modified_by
        );
    }

}
