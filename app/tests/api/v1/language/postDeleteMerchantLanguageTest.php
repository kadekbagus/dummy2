<?php
use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

/**
 * @property Language english
 * @property Language french
 * @property Language japanese
 * @property Language balinese
 * @property Language thai
 * @property Merchant asianMerchant
 * @property Merchant europeanMerchant
 * @property Retailer asianMall
 * @property Retailer asianTenant
 * @property Retailer europeanMall
 * @property Apikey $authDataAsianMall
 * @property Apikey $authDataEuropeanMall
 */
class postDeleteMerchantLanguageTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->english = $english = Factory::create('Language', ['name' => 'English']);
        $this->french = $french = Factory::create('Language', ['name' => 'French']);
        $this->japanese = $japanese = Factory::create('Language', ['name' => 'Japanese']);
        $this->balinese = $balinese = Factory::create('Language', ['name' => 'Balinese']);
        $this->thai = $thai = Factory::create('Language', ['name' => 'Thai']);
        $this->europeanMerchant = $european = Factory::create('Merchant');
        $this->europeanMall = Factory::create('Retailer', ['parent_id' => $european->merchant_id, 'is_mall' => 'yes']);
        $this->asianMerchant = $asian = Factory::create('Merchant');
        $this->asianMall = Factory::create('Retailer', ['parent_id' => $asian->merchant_id, 'is_mall' => 'yes']);
        $this->asianTenant = Factory::create('Retailer', ['parent_id' => $this->asianMall->merchant_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'update_merchant']);
        Factory::create('PermissionRole',
            ['role_id' => $european->user->user_role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('PermissionRole',
            ['role_id' => $asian->user->user_role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('PermissionRole',
            ['role_id' => $this->europeanMall->user->user_role_id, 'permission_id' => $permission->permission_id]);
        Factory::create('PermissionRole',
            ['role_id' => $this->asianMall->user->user_role_id, 'permission_id' => $permission->permission_id]);
        $this->authDataAsianMall = Factory::create('Apikey', ['user_id' => $this->asianMall->user_id]);
        $this->authDataEuropeanMall = Factory::create('Apikey', ['user_id' => $this->europeanMall->user_id]);

        $combinations = [
            [$this->asianMall, $english],
            [$this->asianMall, $japanese],
            [$this->asianMall, $balinese],
            [$this->europeanMall, $french],
            [$this->europeanMall, $english]
        ];
        foreach ($combinations as $merchant_and_language) {
            list($merchant, $language) = $merchant_and_language;
            $merchant_language = new MerchantLanguage();
            $merchant_language->merchant_id = $merchant->merchant_id;
            $merchant_language->language_id = $language->language_id;
            $merchant_language->save();
        }

        // to check excludes deleted
        $balinese_merchant_language = MerchantLanguage::where('language_id', '=', $balinese->language_id)->first();
        $balinese_merchant_language->delete();
    }

    private function makeRequest($post_data, $auth_data)
    {

        $_GET = [
            'apikey' => $auth_data->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = $post_data;

        $url = '/api/v1/language/delete-merchant?' . http_build_query($_GET);

        $secretKey = $auth_data->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    public function testDeletingLanguage()
    {
        $initial_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $merchant_language = MerchantLanguage::excludeDeleted()
            ->where('merchant_id', '=', $this->asianMall->merchant_id)
            ->where('language_id', '=', $this->english->language_id)
            ->first();
        $response = $this->makeRequest([
            'merchant_id' => $this->asianMall->merchant_id,
            'merchant_language_id' => $merchant_language->merchant_language_id
        ], $this->authDataAsianMall);
        $this->assertResponseOk();
        $this->assertResponseStatus(200);
        $final_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $this->assertSame($initial_count - 1, $final_count);
    }

    public function testDeletingNonExistentLanguage()
    {
        $initial_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $response = $this->makeRequest([
            'merchant_id' => $this->asianMall->merchant_id,
            'merchant_language_id' => 99999
        ], $this->authDataAsianMall);
        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/merchant language.*not found/i', $response->message);
        $this->assertSame(null, $response->data);
        $final_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $this->assertSame($initial_count, $final_count);
    }

    public function testDeletingDeletedLanguage()
    {
        $initial_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $merchant_language = MerchantLanguage::withDeleted()
            ->where('merchant_id', '=', $this->asianMall->merchant_id)
            ->where('language_id', '=', $this->balinese->language_id)
            ->first();
        $response = $this->makeRequest([
            'merchant_id' => $this->asianMall->merchant_id,
            'merchant_language_id' => $merchant_language->merchant_language_id
        ], $this->authDataAsianMall);
        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/merchant language.*not found/i', $response->message);
        $this->assertSame(null, $response->data);
        $final_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $this->assertSame($initial_count, $final_count);
    }

    public function testDeletingOtherMerchantLanguage()
    {
        $languages = Retailer::find($this->asianMall->merchant_id)->languages;
        $initial_count = count($languages);
        $merchant_language = $languages[0];

        // m.id E ml.id A ad E
        $response = $this->makeRequest([
            'merchant_id' => $this->europeanMall->merchant_id,
            'merchant_language_id' => $merchant_language->merchant_language_id
        ], $this->authDataEuropeanMall);

        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/merchant language.*not found/i', $response->message);
        $this->assertSame(null, $response->data);

        // m.id A ml.id A ad E
        $response = $this->makeRequest([
            'merchant_id' => $this->asianMall->merchant_id,
            'merchant_language_id' => $merchant_language->merchant_language_id
        ], $this->authDataEuropeanMall);

        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/merchant id.*not found/i', $response->message);
        $this->assertSame(null, $response->data);

        // m.id E ml.id A ad A
        $response = $this->makeRequest([
            'merchant_id' => $this->asianMall->merchant_id,
            'merchant_language_id' => $merchant_language->merchant_language_id
        ], $this->authDataEuropeanMall);

        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/merchant id.*not found/i', $response->message);
        $this->assertSame(null, $response->data);

        $final_count = count(Retailer::find($this->asianMall->merchant_id)->languages);
        $this->assertSame($initial_count, $final_count);
    }

}
