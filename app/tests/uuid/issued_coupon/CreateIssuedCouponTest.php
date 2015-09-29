<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Issued Coupon API
 */
class CreateIssuedCouponTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    /** @var Retailer */
    private $retailer;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->retailer = Factory::create('Retailer', ['is_mall' => 'yes']);
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }
        $_GET = array_merge([], [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);
        $_POST = $data;
        $url = '/api/v1/issued-coupon/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCreateIssuedCoupon()
    {
        $coupon = Factory::create('Coupon');
        $coupon_rule = Factory::create('CouponRule', [
            'promotion_id' => $coupon->promotion_id,
            'rule_value' => 1,
        ]);
        $response = $this->makeRequest([
            'promotion_id' => $coupon->promotion_id,
            'issued_coupon_code' => '999',
            'user_id' => $this->authData->user_id,
            'issuer_retailer_id' => $this->retailer->merchant_id,
            'status' => 'active'
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $coupon = IssuedCoupon::find($response->data->issued_coupon_id);
        $this->assertNotNull($coupon);
    }


}
