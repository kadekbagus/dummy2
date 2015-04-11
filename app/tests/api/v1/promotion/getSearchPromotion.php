<?php
/**
 * PHP Unit Test for PromotionApiController#getSearchPromotion
 *
 * @author: Yudi Rahono <yudi.rahono@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class getSearchPromotion extends TestCase {
    private $baseUrl  = '/api/v1/promotion/search';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('apikey_super_admin');
        $this->promotions = Factory::times(3)->create('Promotion');
        $this->merchant   = Factory::create('Merchant');
        $this->retailer   = Factory::create('Retailer', ['parent_id' => $this->merchant->merchant_id]);
    }

    public function tearDown()
    {
        DB::rollback();

        $this->useTruncate = false;

        parent::tearDown();
    }


    public function testOK_get_search_with_valid_data()
    {
        $makeRequest = function ($getData) {
            $_GET                 = $getData;
            $_GET['apikey']       = $this->authData->api_key;
            $_GET['apitimestamp'] = time();

            $url = $this->baseUrl . '?' . http_build_query($_GET);

            $secretKey = $this->authData->api_secret_key;
            $_SERVER['REQUEST_METHOD']         = 'POST';
            $_SERVER['REQUEST_URI']            = $url;
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

            $response = $this->call('GET', $url)->getContent();
            $response = json_decode($response);

            return $response;
        };


        $response = call_user_func($makeRequest, []);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);


        $merchant = Factory::create('Merchant', ['user_id' => $this->authData->user_id]);
        $promotions = Factory::times(6)->create('Promotion', ['merchant_id' => $merchant->merchant_id]);


        $response = call_user_func($makeRequest, []);

        $this->assertResponseOk();

        $this->assertSame(Status::OK, $response->code);
        $this->assertSame(Status::OK_MSG, $response->message);
    }

}
