<?php
/**
 * Test for API /api/v1/dashboard/top-widget
 * @author kadek <kadek@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Carbon\Carbon as Carbon;

class getWidgetClickDashboardTest extends TestCase
{
    private $baseUrl = '/api/v1/dashboard/top-widget?';

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

        $_POST = [];
        $url = $this->baseUrl . http_build_query($_GET);
        $secretKey = $authData->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $response = $this->call('GET', $url, $_POST)->getContent();
        $response = json_decode($response);
        return $response;
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    }

    public function testNotAllowedUserRole()
    {
        $role = Factory::create('role_guest');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);

        $response = $this->makeRequest([], $apikey);
        $this->assertSame(13, $response->code);
        $this->assertSame('error', $response->status);
        $this->assertRegExp('/Your role are not allowed to access this resource/i', $response->message);
    }

    public function testAllowedUserRole()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('merchant_id' => array($this->mall->merchant_id));

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/ok/i', $response->message);
    }

    public function testBeginAndEndDateGiven()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $data = array('merchant_id' => array($this->mall->merchant_id), 'begin_date' => '2016-06-07 17:00:00', 'end_date' => '2016-06-08 16:59:59');

        $response = $this->makeRequest($data, $apikey);
        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);
    }

    public function testEmptyData()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickTenant()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickTenant1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupTenant->widget_group_name_id]
                                             );

        $widgetClickTenant2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupTenant->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickPromotion()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickPromotion1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupPromotion->widget_group_name_id]
                                             );

        $widgetClickPromotion2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupPromotion->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickNews()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickNews1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupNews->widget_group_name_id]
                                             );

        $widgetClickNews2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupNews->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickCoupon()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickCoupon1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupCoupon->widget_group_name_id]
                                             );

        $widgetClickCoupon2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupCoupon->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickLuckyDraw()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickLuckyDraw1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupLuckyDraw->widget_group_name_id]
                                             );

        $widgetClickLuckyDraw2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupLuckyDraw->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickService()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickService1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupService->widget_group_name_id]
                                             );

        $widgetClickService2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupService->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(0, (int)$value->click_count); }
        }
    }

    public function testWidgetClickFreeWifi()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickFreeWifi1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupFreeWifi->widget_group_name_id]
                                             );

        $widgetClickFreeWifi2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupFreeWifi->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(2, (int)$value->click_count); }
        }
    }

    public function testWidgetClickAll()
    {
        $role = Factory::create('role_mall_owner');
        $user = Factory::create('User', ['user_role_id' => $role->role_id]);
        $apikey = Factory::create('Apikey', ['user_id' => $user->user_id]);
        $mall = Factory::create('Mall', ['timezone_id' => $this->timezone->timezone_id, 'user_id' => $user->user_id]);

        $widgetClickTenant1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupTenant->widget_group_name_id]
                                             );

        $widgetClickTenant2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupTenant->widget_group_name_id]
                                            );

        $widgetClickPromotion1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupPromotion->widget_group_name_id]
                                             );

        $widgetClickLuckyDraw1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupLuckyDraw->widget_group_name_id]
                                                );

        $widgetClickService1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupService->widget_group_name_id]
                                             );

        $widgetClickFreeWifi1 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                              'widget_group_name_id' => $this->widgetGroupFreeWifi->widget_group_name_id]
                                             );

        $widgetClickFreeWifi2 = Factory::create('WidgetClick', ['location_id' => $mall->merchant_id, 
                                                      'widget_group_name_id' => $this->widgetGroupFreeWifi->widget_group_name_id]
                                            );

        $dateNowStart = Carbon::now()->format('Y-m-d 00:00:00');
        $dateNowEnd = Carbon::now()->format('Y-m-d 23:59:59');
        $data = array('merchant_id' => array($mall->merchant_id),
                       'begin_date' => $dateNowStart, 
                       'end_date' => $dateNowEnd, 
                     );

        $response = $this->makeRequest($data, $apikey);

        $this->assertSame(0, $response->code);
        $this->assertSame('success', $response->status);
        $this->assertRegExp('/Ok/i', $response->message);

        $this->assertSame(7, $response->data->returned_records);
        $this->assertSame(7, $response->data->total_records);

        foreach ($response->data->records as $key => $value) {
            if($value->widget_type == 'Tenant')     { $this->assertSame(2, (int)$value->click_count); }
            if($value->widget_type == 'Promotion')  { $this->assertSame(1, (int)$value->click_count); }
            if($value->widget_type == 'News')       { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Coupon')     { $this->assertSame(0, (int)$value->click_count); }
            if($value->widget_type == 'Lucky Draw') { $this->assertSame(1, (int)$value->click_count); }
            if($value->widget_type == 'Service')    { $this->assertSame(1, (int)$value->click_count); }
            if($value->widget_type == 'Free Wifi')  { $this->assertSame(2, (int)$value->click_count); }
        }
    }
}