<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Category API
 */
class UpdateCategoryTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    /** @var Retailer */
    private $retailer;
    /** @var Category */
    private $category;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->retailer = Factory::create('Retailer', ['is_mall' => 'yes']);
        $this->category = Factory::create('Category', ['merchant_id' => $this->retailer->merchant_id]);
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
        $url = '/api/v1/family/update?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testUpdateCategory()
    {
        $response = $this->makeRequest([
            'category_id' => $this->category->category_id,
            'merchant_id' => $this->retailer->merchant_id,
            'category_name' => 'test',
            'status' => 'active'
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
    }


}
