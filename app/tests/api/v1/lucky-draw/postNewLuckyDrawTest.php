<?php
/**
 * Unit test for API /api/v1/lucky-draw/new
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewLuckyDrawTest extends TestCase
{
    protected $apiUrl = '/api/v1/lucky-draw/new';

    public function setUp()
    {
        parent::setUp();

        $this->apikey = Factory::create('apikey_super_admin');
        $this->merchant   = Factory::create('Merchant');
        $this->retailer   = Factory::create('Retailer', ['parent_id' => $this->merchant->merchant_id, 'is_mall' => 'yes']);

        Config::set('orbit.shop.id', $this->retailer->merchant_id);
    }

    public function testSaveLuckyDrawAllRequiredFieldFilled()
    {
        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        $data = [
            'mall_id' => $this->retailer->merchant_id,
            'lucky_draw_name' => 'Test ' . $faker->randomNumber(),
            'description' => 'Description 1',
            'start_date' => date('Y-m-d 00:00:00'),
            'end_date' => $dateNextWeek,
            'minimum_amount' => 10000,
            'grace_period_date' => $dateTwoWeek,
            'min_number' => 1001,
            'max_number' => 2000,
            'status' => 'active',
            'external_lucky_draw_id' => '0022-2222',
            'created_by' => $this->apikey->user_id,
            'modified_by' => $this->apikey->user_id,
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

        // Records returned should be one
        $builder = LuckyDraw::where('mall_id', $this->retailer->merchant_id);
        $count = $builder->count();
        $this->assertSame(1, $count);
        $ldraw = $builder->first();

        // Test lucky draw name
        $this->assertSame($data['lucky_draw_name'], $ldraw->lucky_draw_name);

        // There should be no number in lucky_draw_number tables
        // because we are not generating upfront anymore
        $numberCount = DB::table('lucky_draw_numbers')
                         ->where('lucky_draw_id', $ldraw->lucky_draw_id)
                         ->count();
        $this->assertSame(0, $numberCount);
    }

    public function testSaveLuckyDrawWithTheSameNameReturnError()
    {
        $luckyDrawOne = Factory::create('LuckyDraw', ['status' => 'active', 'mall_id' => $this->retailer->merchant_id]);

        $nextweek = strtotime('+1 week');
        $next2week = strtotime('+2 weeks');
        $dateNextWeek = date('Y-m-d 23:59:59', $nextweek);
        $dateTwoWeek = date('Y-m-d 23:59:59', $next2week);
        $faker = Faker::create();

        $data = [
            'mall_id' => $this->retailer->merchant_id,
            'lucky_draw_name' => $luckyDrawOne->lucky_draw_name,
            'description' => 'Description 1',
            'start_date' => date('Y-m-d 00:00:00'),
            'end_date' => $dateNextWeek,
            'minimum_amount' => 10000,
            'grace_period_date' => $dateTwoWeek,
            'min_number' => 1001,
            'max_number' => 2000,
            'status' => 'active',
            'external_lucky_draw_id' => '0022-2222',
            'created_by' => $this->apikey->user_id,
            'modified_by' => $this->apikey->user_id,
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