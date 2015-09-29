<?php
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

/**
 * UUID Smoke Test: Widget API
 */
class CreateWidgetTest extends TestCase
{
    /** @var Apikey */
    private $authData;
    private $retailer;

    public function setUp()
    {
        parent::setUp();

        $this->authData = Factory::create('apikey_super_admin');
        $this->retailer = Factory::create('Retailer', ['is_mall' => 'yes']);
        Factory::create('Role', ['role_name' => 'assistant']);
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
        $url = '/api/v1/widget/new?' . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function testCreateWidget()
    {
        $response = $this->makeRequest([
            'merchant_id' => $this->retailer->merchant_id,
            'widget_type' => 'news',
            'object_id' => $this->authData->apikey_id, // this is not used, but still required
            'widget_order' => 0,
            'status' => 'active',
        ]);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('success', $response->status);
        $widget = Widget::find($response->data->widget_id);
        $this->assertNotNull($widget);
        $this->assertSame((string)$this->retailer->parent_id, (string)$widget->merchant_id);
    }


}
