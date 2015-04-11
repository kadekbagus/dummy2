<?php
/**
 * PHP Unit Test for PromotionApiController#postUpdatePromotion
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postUpdatePromotion extends TestCase {
    private $baseUrl  = '/api/v1/promotion/update';

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->promotions = Factory::times(3)->create('Promotion');
        $this->merchant   = Factory::create('Merchant');
        $this->retailer   = Factory::create('Retailer', ['parent_id' => $this->merchant->merchant_id]);
    }

    public function testOK_post_update_promotion()
    {
        $promotion = Factory::create('Promotion');
        Factory::create('PromotionRule', ['promotion_id' => $promotion->promotion_id]);

        $makeRequest = function ($changes = []) use ($promotion) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = $changes;
            $_POST['promotion_id'] = $promotion->promotion_id;

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = call_user_func($makeRequest, ['promotion_name' => 'Changed']);
        $currentPromo = Promotion::where('promotion_id', $promotion->promotion_id)->first();

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should change name
        $this->assertSame('Changed', $currentPromo->promotion_name);


        $response = call_user_func($makeRequest, ['status' => 'inactive']);
        $currentPromo = Promotion::where('promotion_id', $promotion->promotion_id)->first();

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should change name
        $this->assertSame('inactive', $currentPromo->status);

        // should not change number of promotion
        $this->assertSame(4, Promotion::count());
    }

    public function testACL_post_update_promotion()
    {
        $promotion = Factory::create('Promotion');
        Factory::create('PromotionRule', ['promotion_id' => $promotion->promotion_id]);

        $makeRequest = function ($authData) use ($promotion) {
            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST['promotion_id'] = $promotion->promotion_id;

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };


        // As Super Admin
        $response = call_user_func($makeRequest, $this->authData);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // As User Without Granted Permission
        $merchant   = $promotion->merchant()->first();
        $authData   = Factory::create('Apikey', ['user_id' => $merchant->user_id]);

        $response   = call_user_func($makeRequest, $authData);

        // should be failed
        $this->assertResponseStatus(403);

        // should be access denied
        $this->assertSame(Status::ACCESS_DENIED, $response->code);
        $this->assertRegExp('/you.do.not.have.permission.to/i', $response->message);

        // As User With Permission to update promotion on their store
        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'update_promotion']);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);
        $promotion->merchant_id = $merchant->merchant_id;
        $promotion->save();

        Factory::create('PermissionRole', ['role_id' => $user->user_role_id, 'permission_id' => $permission->permission_id]);

        $response = call_user_func($makeRequest,  $authData);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should not change number of promotion
        $this->assertSame(4, Promotion::count());
    }

    public function testError_parameters_post_update_promotion()
    {
        $promotion = Factory::create('Promotion');
        Factory::create('PromotionRule', ['promotion_id' => $promotion->promotion_id]);

        $makeRequest = function ($postData) {
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST = $postData;

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };


        // post without parameters
        $response = call_user_func($makeRequest, []);

        // should be failed
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/promotion.id.*is.required/', $response->message);

        // post with 3 char name
        $response = call_user_func($makeRequest, [
            'promotion_id' => $promotion->promotion_id,
            'promotion_name' => 'abc'
        ]);

        // should be failed
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/promotion.name.*at.least.5/', $response->message);

        // post with merchant id not number
        $response = call_user_func($makeRequest, [
            'promotion_id' => $promotion->promotion_id,
            'merchant_id'  => 'abc'
        ]);

        // should be failed
        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/merchant.id.must.be.a.number/', $response->message);
    }
}
