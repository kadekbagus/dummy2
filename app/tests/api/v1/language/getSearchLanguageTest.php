<?php

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

class getSearchLanguageTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->authData = Factory::create('apikey_super_admin');
    }


    private function makeRequest()
    {

        $_GET['apikey'] = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $url = '/api/v1/language/list?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);

        return $response;
    }

    public function testSearch()
    {
        $lang = Factory::create('Language');
        $lang2 = Factory::create('Language');

        $response = $this->makeRequest();

        $this->assertResponseOk();
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(2, $response->data->total_records);

        $order = [$lang, $lang2];
        if ($response->data->records[0]->name != $lang->name) {
            $order = [$lang2, $lang];
        }

        for ($i = 0; $i < 2; $i++) {
            $this->assertSame($order[$i]->name, $response->data->records[$i]->name);
            $this->assertSame((string)$order[$i]->language_id, $response->data->records[$i]->language_id);
        }
    }

}