<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: News API
 */
class UpdateNewsTest extends TestCase
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
        $url = '/api/v1/news/update?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testUpdateNews()
    {
        $news = new News();
        $news->mall_id = $this->retailer->merchant_id;
        $news->status = 'active';
        $news->news_name = 'Great News';
        $news->save();
        $retailer = Factory::create('Retailer', ['is_mall' => 'no']);
        $response = $this->makeRequest([
            'news_id' => $news->news_id,
            'retailer_ids' => [$retailer->merchant_id],
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $news = News::find($response->data->news_id);
        $this->assertNotNull($news);
        $this->assertCount(1, $news->tenants);
        $this->assertSame((string)$news->tenants[0]->merchant_id, (string)$retailer->merchant_id);
    }


}
