<?php
/**
 * Unit test for API /api/v1/lucky-draw/new
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postUpdateLuckyDrawTest extends TestCase
{
    protected $apiUrl = '/api/v1/lucky-draw/update';

    public function setUp()
    {
        parent::setUp();

        $this->apikey = Factory::create('apikey_super_admin');
        $this->merchant   = Factory::create('Merchant');
        $this->retailer   = Factory::create('Retailer', ['parent_id' => $this->merchant->merchant_id, 'is_mall' => 'yes']);

        Config::set('orbit.shop.id', $this->retailer->merchant_id);

        $_GET = [];
        $_POST = [];
    }

    public function testSaveUpdateLuckyDrawAllowableFields()
    {
        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        // Create lucky draw
        $oldLuckyDraw = Factory::create('LuckyDraw', ['mall_id' => $this->retailer->merchant_id]);

        $data = [
            'lucky_draw_id' => $oldLuckyDraw->lucky_draw_id,
            'description' => $faker->text(),
            'start_date' => date('Y-m-d 00:00:00'),
            'end_date' => $dateNextWeek,
            'grace_period_date' => $dateTwoWeek,
            'status' => 'inactive'
        ];

        // Set the client API Keys
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field=>$value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        $this->assertSame(0, $response->code);
        foreach ($data as $field=>$value) {
            $this->assertSame((string)$data[$field], (string)$response->data->{$field});
        }

        // Records returned should be zero (inactive)
        $builder = LuckyDraw::active()->where('mall_id', $oldLuckyDraw->mall_id);
        $count = $builder->count();
        $this->assertSame(0, $count);
    }

    public function testSaveUpdateLuckyDrawForbiddenFieldMinNumber()
    {
        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        // Create lucky draw
        $oldLuckyDraw = Factory::create('LuckyDraw', ['mall_id' => $this->retailer->merchant_id]);

        $data = [
            'mall_id' => $oldLuckyDraw->mall_id,
            'lucky_draw_id' => $oldLuckyDraw->lucky_draw_id,
            'min_number' => $oldLuckyDraw->min_number + 1
        ];

        // Set the client API Keys
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field=>$value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        $this->assertNotSame(0, $response->code);
    }

    public function testSaveUpdateLuckyDrawForbiddenFieldMaxNumber()
    {
        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        // Create lucky draw
        $oldLuckyDraw = Factory::create('LuckyDraw', ['mall_id' => $this->retailer->merchant_id]);

        $data = [
            'mall_id' => $oldLuckyDraw->mall_id,
            'lucky_draw_id' => $oldLuckyDraw->lucky_draw_id,
            'max_number' => $oldLuckyDraw->max_number + 1
        ];

        // Set the client API Keys
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field=>$value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        $this->assertNotSame(0, $response->code);
    }

    public function testSaveUpdateLuckyDrawForbiddenFieldMinimumAmount()
    {
        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        // Create lucky draw
        $oldLuckyDraw = Factory::create('LuckyDraw', ['mall_id' => $this->retailer->merchant_id]);

        $data = [
            'mall_id' => $oldLuckyDraw->mall_id,
            'lucky_draw_id' => $oldLuckyDraw->lucky_draw_id,
            'minimum_amount' => $oldLuckyDraw->minimum_amount + 1
        ];

        // Set the client API Keys
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field=>$value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        $this->assertNotSame(0, $response->code);
    }

    public function testSaveUpdateLuckyDrawMoreThanOneActiveReturnError()
    {
        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        // Create lucky draw
        $activeLuckyDraw = Factory::create('LuckyDraw', ['mall_id' => $this->retailer->merchant_id]);
        $oldLuckyDraw = Factory::create('LuckyDraw', ['mall_id' => $this->retailer->merchant_id, 'status' => 'inactive']);

        $data = [
            'mall_id' => $oldLuckyDraw->mall_id,
            'lucky_draw_id' => $oldLuckyDraw->lucky_draw_id,
            'status' => 'active'
        ];

        // Set the client API Keys
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        foreach ($data as $field=>$value) {
            $_POST[$field] = $value;
        }
        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $json = $this->call('POST', $url)->getContent();
        $response = json_decode($json);

        $this->assertNotSame(0, $response->code);
    }
}