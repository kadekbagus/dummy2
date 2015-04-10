<?php
/**
 * PHP Unit Test for PromotionApiController#postNewPromotion
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class postNewPromotion extends TestCase {
    private $baseUrl  = '/api/v1/promotion/new';

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->promotions = Factory::times(3)->create('Promotion');
        $this->merchant   = Factory::create('Merchant');
        $this->retailer   = Factory::create('Retailer', ['parent_id' => $this->merchant->merchant_id]);
    }

    public function testOK_post_new_promotion_for_product()
    {
        $product = Factory::create('Product');

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();


        $_POST['merchant_id'] = $this->merchant->merchant_id;
        $_POST['promotion_name']  = 'Christmas\'s Discount';
        $_POST['promotion_type']  = 'product';
        $_POST['status']          = 'active';
        $_POST['description']     = 'Discount for random product selected';
        $_POST['discount_object_type'] = 'product';
        $_POST['discount_object_id1']  = $product->product_id;
        $_POST['retailer_ids']         = [$this->retailer->merchant_id];

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should increment number of promotion
        $this->assertSame(4, Promotion::count());
    }

    public function testOK_post_new_promotion_for_family()
    {
        $category = Factory::create('Category');

        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();


        $_POST['merchant_id'] = $this->merchant->merchant_id;
        $_POST['promotion_name']  = 'Christmas\'s Discount';
        $_POST['promotion_type']  = 'product';
        $_POST['status']          = 'active';
        $_POST['description']     = 'Discount for random family selected';
        $_POST['discount_object_type'] = 'family';
        $_POST['discount_object_id1']  = $category->category_id;
        $_POST['retailer_ids']         = [$this->retailer->merchant_id];

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should increment number of promotion
        $this->assertSame(4, Promotion::count());
    }


    public function testOK_post_new_cart_based_promotion()
    {
        $_GET['apikey']       = $this->authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['merchant_id'] = $this->merchant->merchant_id;
        $_POST['promotion_name']  = 'Christmas\'s Discount';
        $_POST['promotion_type']  = 'cart';
        $_POST['status']          = 'active';
        $_POST['description']     = 'Discount for random product selected';
        $_POST['retailer_ids']         = [$this->retailer->merchant_id];

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->authData->api_secret_key;
        $_SERVER['REQUEST_METHOD']         = 'POST';
        $_SERVER['REQUEST_URI']            = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        // should increment number of promotion
        $this->assertSame(4, Promotion::count());
    }

    public function testACL_post_new_promotion()
    {
        $makeRequest = function ($authData, $merchant) {
            $product = Factory::create('Product');

            $_GET['apikey']       = $authData->api_key;
            $_GET['apitimestamp'] = time();

            $_POST['merchant_id'] = $merchant->merchant_id;
            $_POST['promotion_name']  = "Christmas's Discount {$product->product_name}";
            $_POST['promotion_type']  = 'cart';
            $_POST['status']          = 'active';
            $_POST['description']     = 'Discount for random product selected';

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('POST', $url, $_POST)->getContent();
            $response = json_decode($response);

            return $response;
        };

        $response = call_user_func($makeRequest, $this->authData, $this->merchant);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $permission = Factory::create('Permission', ['permission_name' => 'create_promotion']);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);

        Factory::create('PermissionRole', ['role_id' => $user->user_role_id, 'permission_id' => $permission->permission_id]);

        $response = call_user_func($makeRequest,  $authData, $merchant);

        // Should be OK
        $this->assertResponseOk();

        // should say OK
        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);

        $user       = Factory::create('User');
        $authData   = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $merchant   = Factory::create('Merchant', ['user_id' => $user->user_id]);

        $response   = call_user_func($makeRequest, $authData, $merchant);

        // should be failed
        $this->assertResponseStatus(403);

        // should be access denied
        $this->assertSame(Status::ACCESS_DENIED, $response->code);
        $this->assertRegExp('/you.do.not.have.permission.to/i', $response->message);
    }

    public function testError_parameters_post_new_promotion()
    {
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

        $postData = array(
            'merchant_id'    => $this->merchant->merchant_id,
            'promotion_name' => 'Christmas\'s Discount',
            'promotion_type' => 'cart',
            'status'         => 'active',
            'description'    => 'Discount for random selected product'
        );

        $reqData = $postData;
        unset($reqData['merchant_id']);
        $response = call_user_func($makeRequest, $reqData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/merchant.*is.required/', $response->message);

        $reqData = $postData;
        unset($reqData['promotion_name']);
        $response  = call_user_func($makeRequest, $reqData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/promotion.name.*is.required/', $response->message);

        $reqData = $postData;
        unset($reqData['promotion_type']);
        $response  = call_user_func($makeRequest, $reqData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/promotion.type.*is.required/', $response->message);

        $reqData = $postData;
        unset($reqData['status']);
        $response  = call_user_func($makeRequest, $reqData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/status.*is.required/', $response->message);

        $reqData = $postData;
        $reqData['promotion_name'] = Promotion::first()->promotion_name;
        $response  = call_user_func($makeRequest, $reqData);

        $this->assertResponseStatus(403);
        $this->assertSame(Status::INVALID_ARGUMENT, $response->code);
        $this->assertRegExp('/promotion.name.*already.*used/', $response->message);

        // should not change the number of persisted promotion
        $this->assertSame(3, Promotion::count());
    }
}
