<?php
use Laracasts\TestDummy\Factory;
use OrbitShop\API\v1\Helper\Generator;

/**
 * Tests translations are deleted when news are.
 *
 * @property MerchantLanguage[] $merchantLanguages
 * @property Merchant $group
 * @property Retailer $mall
 * @property string $masterPassword
 *
 * @property Apikey $authData
 * @property int $userId
 */
class postDeleteNews_TranslationsTest extends TestCase
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
        $setting->setting_name = 'current_news';
        $setting->setting_value = $this->mall->merchant_id;
        $setting->save();

        $password_setting = new Setting();
        $password_setting->setting_name = 'master_password';
        $password_setting->object_type = 'merchant';
        $password_setting->object_id = $merchant->object_id;
        $password_setting->setting_value = Hash::make($this->masterPassword = '12345');
        $password_setting->save();

        $permission = Factory::create('Permission', ['permission_name' => 'delete_news']);

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

    private function makeRequest($news)
    {
        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = [
            'news_id' => $news->news_id,
            'password' => $this->masterPassword
        ];

        $url = '/api/v1/news/delete?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    private function createNewsWithTranslation($merchant_language_name)
    {
        $news = Factory::create('News', [
            'mall_id' => $this->mall->merchant_id,
        ]);
        $translation = new NewsTranslation();
        $translation->news_id = $news->news_id;
        $translation->merchant_language_id = $this->merchantLanguages[$merchant_language_name]->merchant_language_id;
        $translation->news_name = 'Translated name';
        $translation->description = 'Translated description';
        $translation->save();

        return [$news, $translation];
    }

    /**
     * @param object $response
     */
    private function assertJsonResponseOk($response)
    {
        $this->assertSame('News has been successfully deleted.', $response->message);
        $this->assertSame('success', $response->status);
        $this->assertSame(0, (int)$response->code);
    }

    function testDeletingNewsDeletesTranslations() {
        list($news, $translation) = $this->createNewsWithTranslation('english');
        $this->assertNull(
            NewsTranslation::find($translation->news_translation_id)->modified_by
        );
        $count_news_before = News::excludeDeleted()->count();
        $count_translation_before = NewsTranslation::excludeDeleted()->count();

        $response = $this->makeRequest($news);
        $this->assertJsonResponseOk($response);

        $count_news_after = News::excludeDeleted()->count();
        $count_translation_after = NewsTranslation::excludeDeleted()->count();
        $this->assertSame($count_news_before - 1, $count_news_after);
        $this->assertSame($count_translation_before - 1, $count_translation_after);
        $this->assertSame(
            $this->userId,
            NewsTranslation::find($translation->news_translation_id)->modified_by
        );
    }

}
