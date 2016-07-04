<?php
/**
 * Unit test for WidgetAPIController::getSearchWidget(). Call to this
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;
use OrbitShop\API\v1\Helper\Generator;

class getSearchWidgetAngularCITest extends TestCase
{
    private $baseUrl = '/api/v1/cust/widgets';

    public function setUp()
    {
        parent::setUp();

        $this->user_1 = Factory::create('user_guest');

        $this->userdetail = Factory::create('UserDetail', [
            'user_id' => $this->user_1->user_id,
            'gender'  => null,
        ]);

        $this->apikey_user_1 = Factory::create('Apikey', ['user_id' => $this->user_1->user_id]);

        $this->mall_1 = Factory::create('Mall');

        // create widgets for mall_1 via Factory
        $this->widgets = array();
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'tenant',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 1,
        ]);
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'promotion',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 2,
        ]);
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'news',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 3,
        ]);
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'coupon',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 4,
        ]);
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'lucky_draw',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 5,
        ]);
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'service',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 6,
        ]);
        $this->widgets[] = Factory::create('Widget', [
            'widget_type' => 'free_wifi',
            'merchant_id' => $this->mall_1->merchant_id,
            'widget_order'  => 7,
        ]);

        // create widgets retailer records
        $this->widget_retailers = array();
        foreach ($this->widgets as $widget) {
            $this->widget_retailers[] = Factory::create('WidgetRetailer', [
                'widget_id'   => $widget->widget_id,
                'retailer_id'   => $this->mall_1->merchant_id
            ]);
        }

        $_GET = [];
        $_POST = [];
    }

    public function testOK_found_seven_widget()
    {
        $coupon_widget = Factory::create('Setting', ['setting_name' => 'enable_coupon_widget', 'object_id' => $this->mall_1->merchant_id]);
        $lucky_draw_widget = Factory::create('Setting', ['setting_name' => 'enable_lucky_draw_widget', 'object_id' => $this->mall_1->merchant_id]);
        $free_wifi_widget = Factory::create('Setting', ['setting_name' => 'enable_free_wifi_widget', 'object_id' => $this->mall_1->merchant_id]);

        $_GET['apikey'] = $this->apikey_user_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['status'] = 'active';
        $_GET['mall_id'] = $this->mall_1->merchant_id;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey_user_1->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        // test number of returned records
        $this->assertSame(7, $response->data->returned_records);
    }

    public function testOK_found_six_widget_disable_free_wifi_widget()
    {
        $coupon_widget = Factory::create('Setting', ['setting_name' => 'enable_coupon_widget', 'object_id' => $this->mall_1->merchant_id]);
        $lucky_draw_widget = Factory::create('Setting', ['setting_name' => 'enable_lucky_draw_widget', 'object_id' => $this->mall_1->merchant_id]);
        $free_wifi_widget = Factory::create('Setting', ['setting_name' => 'enable_free_wifi_widget', 'object_id' => $this->mall_1->merchant_id, 'setting_value' => 'false']);

        $_GET['apikey'] = $this->apikey_user_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['status'] = 'active';
        $_GET['mall_id'] = $this->mall_1->merchant_id;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey_user_1->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        // test number of returned records
        $this->assertSame(6, $response->data->returned_records);
    }

    public function testOK_found_six_widget_disable_lucky_draw_widget()
    {
        $coupon_widget = Factory::create('Setting', ['setting_name' => 'enable_coupon_widget', 'object_id' => $this->mall_1->merchant_id]);
        $lucky_draw_widget = Factory::create('Setting', ['setting_name' => 'enable_lucky_draw_widget', 'object_id' => $this->mall_1->merchant_id, 'setting_value' => 'false']);
        $free_wifi_widget = Factory::create('Setting', ['setting_name' => 'enable_free_wifi_widget', 'object_id' => $this->mall_1->merchant_id]);

        $_GET['apikey'] = $this->apikey_user_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['status'] = 'active';
        $_GET['mall_id'] = $this->mall_1->merchant_id;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey_user_1->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        // test number of returned records
        $this->assertSame(6, $response->data->returned_records);
    }

    public function testOK_found_six_widget_disable_lucky_draw_and_free_wifi_widget()
    {
        $coupon_widget = Factory::create('Setting', ['setting_name' => 'enable_coupon_widget', 'object_id' => $this->mall_1->merchant_id]);
        $lucky_draw_widget = Factory::create('Setting', ['setting_name' => 'enable_lucky_draw_widget', 'object_id' => $this->mall_1->merchant_id, 'setting_value' => 'false']);
        $free_wifi_widget = Factory::create('Setting', ['setting_name' => 'enable_free_wifi_widget', 'object_id' => $this->mall_1->merchant_id, 'setting_value' => 'false']);

        $_GET['apikey'] = $this->apikey_user_1->api_key;
        $_GET['apitimestamp'] = time();
        $_GET['status'] = 'active';
        $_GET['mall_id'] = $this->mall_1->merchant_id;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey_user_1->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('GET', $url)->getContent();
        $response = json_decode($json);

        // test number of returned records
        $this->assertSame(5, $response->data->returned_records);
    }
}