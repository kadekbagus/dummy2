<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Coupon API
 */
class CreateCouponTest extends TestCase
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
        $url = '/api/v1/coupon/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCreateCoupon()
    {
        $response = $this->makeRequest([
            'merchant_id' => $this->retailer->merchant_id,
            'promotion_name' => 'test',
            'promotion_type' => 'mall',
            'begin_date' => '2015-01-01 00:00:00',
            'end_date' => '2015-10-10 00:00:00',
            'coupon_validity_in_date' => '2015-11-11 00:00:00',
            'rule_value' => 1,
            'discount_value' => 20,
            'retailer_ids' => [$this->retailer->merchant_id],
            'status' => 'active'
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $coupon = Coupon::find($response->data->promotion_id);
        $this->assertNotNull($coupon);
    }


}
