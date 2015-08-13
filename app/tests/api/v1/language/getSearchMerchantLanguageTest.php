<?php

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

class getSearchMerchantLanguageTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->authData = Factory::create('apikey_super_admin');
        $this->english = $english = Factory::create('Language', ['name' => 'English']);
        $this->french = $french = Factory::create('Language', ['name' => 'French']);
        $this->japanese = $japanese = Factory::create('Language', ['name' => 'Japanese']);
        $this->balinese = $balinese = Factory::create('Language', ['name' => 'Balinese']);
        $this->europeanMerchant = $european = Factory::create('Merchant');
        $this->europeanMall = Factory::create('Retailer', ['parent_id' => $european->merchant_id, 'is_mall' => 'yes']);
        $this->asianMerchant = $asian = Factory::create('Merchant');
        $this->asianMall = Factory::create('Retailer', ['parent_id' => $asian->merchant_id, 'is_mall' => 'yes']);

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

    private function makeRequest($get_data)
    {

        $_GET = array_merge([
            'apikey' => $this->authData->api_key,
            'apitimestamp' => time(),
        ], $get_data);

        $url = '/api/v1/language/list-merchant?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    public function testSearchWithoutMerchantId()
    {
        $response = $this->makeRequest([]);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
    }

    public function testSearchWithInvalidMerchantId()
    {
        $response = $this->makeRequest(['merchant_id' => 999999]);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
    }

    public function testSearchWithValidMerchantId()
    {
        $response = $this->makeRequest(['merchant_id' => $this->asianMall->merchant_id]);

        $this->assertResponseStatus(200);
        $this->assertSame(Status::OK, $response->code);
        $this->assertCount(2, $response->data->records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, $response->data->returned_records);
        $expected = ['English', 'Japanese'];
        $found = [];
        foreach ($response->data->records as $record) {
            foreach ($expected as $expected_language) {
                if ($record->language->name == $expected_language) {
                    $found[$expected_language] = true;
                    break;
                }
            }
        }
        $this->assertCount(count($expected), $found);

        $response = $this->makeRequest(['merchant_id' => $this->europeanMall->merchant_id]);

        $this->assertResponseStatus(200);
        $this->assertSame(Status::OK, $response->code);
        $this->assertCount(2, $response->data->records);
        $this->assertSame(2, $response->data->total_records);
        $this->assertSame(2, $response->data->returned_records);
        $expected = ['English', 'French'];
        $found = [];
        foreach ($response->data->records as $record) {
            foreach ($expected as $expected_language) {
                if ($record->language->name == $expected_language) {
                    $found[$expected_language] = true;
                    break;
                }
            }
        }
        $this->assertCount(count($expected), $found);
    }
}