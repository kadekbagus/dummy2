<?php

/**
 * Unit testing for Orbit\Controller\API\v1\Customer\UserCIAPIController::getMyAccountInfo() method.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use OrbitShop\API\v1\Helper\Generator;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class getMyAccountInfoCIAngularTest extends TestCase
{
    protected $apiUrl = '/api/v1/cust/my-account';

    public function setUp()
    {
        parent::setUp();

        $this->user = Factory::create('user_consumer');
        $this->userdetail = Factory::create('UserDetail', [
            'user_id' => $this->user->user_id
        ]);
        $this->apikey = Factory::create('apikey_super_admin', [
            'user_id' => $this->user->user_id
        ]);

        $this->user2 = Factory::create('user_consumer');
        $this->userdetail2 = Factory::create('UserDetail', [
            'user_id' => $this->user2->user_id
        ]);
        $this->apikey2 = Factory::create('apikey_super_admin', [
            'user_id' => $this->user2->user_id
        ]);

        $this->usermedia2 = Factory::create('Media', [
            'media_name_id' => 'user_profile_picture',
            'media_name_long' => 'user_profile_picture_orig',
            'object_id' => $this->user2->user_id,
            'object_name' => 'user',
            'path' => '/path/to/the/profile/image'
        ]);

        $this->mall_1 = Factory::create('Mall');

        Config::set('orbit.shop.main_domain', 'gotomalls.cool');

        $_GET = [];
        $_POST = [];
    }

    public function testOKGetMyAccountUser1()
    {
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['apitimestamp'] = time();

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame($this->user->user_email, $response->data->email);
        $this->assertSame($this->user->user_firstname, $response->data->firstname);
        $this->assertSame($this->user->user_lastname, $response->data->lastname);
        $this->assertNull($response->data->image);
    }

    public function testOKGetMyAccountUser2()
    {
        $_GET['apikey'] = $this->apikey2->api_key;
        $_GET['mall_id'] = $this->mall_1->merchant_id;
        $_GET['apitimestamp'] = time();

        $url = $this->apiUrl . '?' . http_build_query($_GET);
        $secretKey = $this->apikey2->api_secret_key;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, (int) $response->code);
        $this->assertSame($this->user2->user_email, $response->data->email);
        $this->assertSame($this->user2->user_firstname, $response->data->firstname);
        $this->assertSame($this->user2->user_lastname, $response->data->lastname);
        $this->assertSame($this->usermedia2->path, $response->data->image);
    }
}
