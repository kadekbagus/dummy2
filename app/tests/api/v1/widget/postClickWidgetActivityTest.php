<?php
/**
 * Test for API /api/v1/cust/widgetclick
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class postClickWidgetActivityTest extends TestCase
{
    private $baseUrl  = '/api/v1/cust/widgetclick?';

    public function setUp()
    {
        parent::setUp();

        DB::beginTransaction();

        $this->authData = Factory::create('Apikey');
        $this->timezone = Factory::create('timezone_jakarta');
        $this->mall = Factory::create('Mall');

        $this->widgetGroupTenant = Factory::create('WidgetGroupName', ['widget_group_name' => 'Tenant']);
        $this->widgetGroupPromotion = Factory::create('WidgetGroupName', ['widget_group_name' => 'Promotion']);
        $this->widgetGroupNews = Factory::create('WidgetGroupName', ['widget_group_name' => 'News']);
        $this->widgetGroupCoupon = Factory::create('WidgetGroupName', ['widget_group_name' => 'Coupon']);
        $this->widgetGroupLuckyDraw = Factory::create('WidgetGroupName', ['widget_group_name' => 'Lucky Draw']);
        $this->widgetGroupService = Factory::create('WidgetGroupName', ['widget_group_name' => 'Service']);
        $this->widgetGroupFreeWifi = Factory::create('WidgetGroupName', ['widget_group_name' => 'Free Wifi']);
    }

    private function makeRequest($data, $authData = null)
    {
        if ($authData === null) {
            $authData = $this->authData;
        }

        $_GET = array_merge($data, [
            'apikey' => $authData->api_key,
            'apitimestamp' => time(),
        ]);

        $_GET['apikey'] = $authData->api_key;
        $_GET['apitimestamp'] = time();

        $_POST = $data;
        $url = $this->baseUrl . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('POST', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testNoInputParameter()
    {
        // send nothing
        $data = array();
        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/mall id field is required/i', $response->message);
    }

    public function testMallIdGiven()
    {
        // test only send mall_id
        $mall = Factory::create('Mall');
        $data = array('mall_id' => $mall->merchant_id);

        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/widget id field is required/i', $response->message);
    }

    public function testMallIdAndWidgetIdGiven()
    {
        // test send mall_id and widget_id
        $mall = Factory::create('Mall');
        $widget = Factory::create('Widget', ['widget_type' => 'tenant', 'merchant_id' => $mall->merchant_id]);
        $data = array('mall_id' => $mall->merchant_id, 'widget_id' => $widget->widget_id);

        $response = $this->makeRequest($data);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);
    }

    public function testWrongMallId()
    {
        // test wrong mall_id
        $mall = Factory::create('Mall');
        $widget = Factory::create('Widget', ['widget_type' => 'tenant', 'merchant_id' => $mall->merchant_id]);
        $data = array('mall_id' => '1234', 'widget_id' => $widget->widget_id);

        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Mall ID you specified is not found/i', $response->message);
    }

    public function testWrongWidgetId()
    {
        // test wrong widget_id
        $mall = Factory::create('Mall');
        $widget = Factory::create('Widget', ['widget_type' => 'tenant', 'merchant_id' => $mall->merchant_id]);
        $data = array('mall_id' => $mall->merchant_id, 'widget_id' => '1234');

        $response = $this->makeRequest($data);

        $this->assertSame(14, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Widget ID you specified is not found/i', $response->message);
    }

    public function testValidateData()
    {
        // validate the data
        $mall = Factory::create('Mall');
        $widget = Factory::create('Widget', ['widget_type' => 'tenant', 'merchant_id' => $mall->merchant_id]);
        $role = Factory::create('role_guest');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('mall_id' => $mall->merchant_id, 'widget_id' => $widget->widget_id);

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);

        $widgetClick = WidgetClick::first();
        
        $this->assertSame($widget->widget_id, $widgetClick->widget_id);
        $this->assertSame($user->user_id, $widgetClick->user_id);
        $this->assertSame($mall->merchant_id, $widgetClick->location_id);
        $this->assertSame($this->widgetGroupTenant->widget_group_name_id, $widgetClick->widget_group_name_id);
    }

}