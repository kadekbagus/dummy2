<?php

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

class postAddMerchantLanguageTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->english = $english = Factory::create('Language', ['name' => 'English']);
        $this->french = $french = Factory::create('Language', ['name' => 'French']);
        $this->japanese = $japanese = Factory::create('Language', ['name' => 'Japanese']);
        $this->balinese = $balinese = Factory::create('Language', ['name' => 'Balinese']);
        $this->thai = $thai = Factory::create('Language', ['name' => 'Thai']);
        $this->europeanMerchant = $european = Factory::create('Merchant');
        $this->asianMerchant = $asian = Factory::create('Merchant');

        $combinations = [[$asian, $english], [$asian, $japanese], [$asian, $balinese], [$european, $french], [$european, $english]];
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

    private function makeRequest($post_data)
    {

        $_GET = [
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ];

        $_POST = $post_data;

        $url = '/api/v1/language/add-merchant?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    /**
     * Adding a language returns the MerchantLanguage.
     */
    public function testAddingLanguage()
    {
        $id = $this->asianMerchant->merchant_id;
        $existing_languages = $this->asianMerchant->languages;
        $this->assertCount(2, $existing_languages);
        $response = $this->makeRequest(['merchant_id' => $id, 'language_id' => $this->thai->language_id]);
        $after_languages = Merchant::find($id)->languages;
        $this->assertCount(3, $after_languages);
        $this->assertResponseOk();
        $this->assertResponseStatus(200);
        $this->assertSame((string)$this->thai->language_id, (string)$response->data->language->language_id);
    }

    /**
     * Adding existing language simply returns the existing MerchantLanguage.
     */
    public function testAddingExistingLanguage()
    {
        $id = $this->asianMerchant->merchant_id;
        $existing_languages = Merchant::find($id)->languages;
        $existing_count = count($existing_languages);
        $response = $this->makeRequest(['merchant_id' => $id, 'language_id' => $this->english->language_id]);
        $after_languages = Merchant::find($id)->languages;
        $this->assertCount($existing_count, $after_languages);
        $this->assertResponseOk();
        $this->assertResponseStatus(200);
        $this->assertSame((string)$this->english->language_id, (string)$response->data->language->language_id);
    }

    public function testAddingToNonExistentMerchant()
    {
        $response = $this->makeRequest(['merchant_id' => 999999, 'language_id' => $this->thai->language_id]);
        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/merchant.*not found/i', $response->message);
        $this->assertSame(null, $response->data);
    }

    public function testAddingNonExistentLanguage()
    {
        $response = $this->makeRequest(['merchant_id' => $this->asianMerchant->merchant_id, 'language_id' => 999999]);
        $this->assertResponseStatus(403);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/language.*not found/i', $response->message);
        $this->assertSame(null, $response->data);
    }
}
