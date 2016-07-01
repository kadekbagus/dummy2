<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\MallByDomainCIAPIController::getMallIdByDomain() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getMallIdByDomainAngularCITest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/malls';

    public function setUp()
    {
        parent::setUp();

        $this->user = Factory::create('user_super_admin');
        $this->userdetail = Factory::create('UserDetail', [
            'user_id' => $this->user->user_id
        ]);
        $this->apikey = Factory::create('apikey_super_admin', [
            'user_id' => $this->user->user_id
        ]);

        $this->mall_1 = Factory::create('Mall');
        $this->mall_2 = Factory::create('Mall');

        Config::set('orbit.shop.main_domain', 'gotomalls.cool');

        $this->mall_1_setting = Factory::create('Setting', [
            'setting_name' => 'dom:mall1.gotomalls.cool',
            'setting_value' => $this->mall_1->merchant_id,
            'object_id' => null,
            'object_type' => null,
        ]);

        $this->mall_2_setting = Factory::create('Setting', [
            'setting_name' => 'dom:mall2.gotomalls.cool',
            'setting_value' => $this->mall_2->merchant_id,
            'object_id' => null,
            'object_type' => null,
        ]);

        $_GET = [];
        $_POST = [];
    }

    public function testOKGetMallIdByDomainMall1()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['sub_domain'] = 'mall1';

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame($this->mall_1->merchant_id, $response->data->merchant_id);
        $this->assertSame($this->mall_1->name, $response->data->name);
    }

    public function testFAILGetMallIdByDomainMall1()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['sub_domain'] = 'mall-1'; // wrong domain

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(14, (int) $response->code);
        $this->assertSame(null, $response->data);
    }

    public function testOKGetMallIdByDomainMall2()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['sub_domain'] = 'mall2';

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame($this->mall_2->merchant_id, $response->data->merchant_id);
        $this->assertSame($this->mall_2->name, $response->data->name);
    }
}
