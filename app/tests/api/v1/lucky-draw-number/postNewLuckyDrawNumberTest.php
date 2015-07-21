<?php
/**
 * Unit test for API /api/v1/lucky-draw-number/new
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class postNewLuckyDrawNumberTest extends TestCase
{
    protected $apikey = NULL;
    protected $luckydraw = NULL;
    protected $apiUrl = '/api/v1/lucky-draw-number/new';

    public function setUp()
    {
        parent::setUp();

        $this->apikey = Factory::create('apikey_super_admin');

        // Create lucky draw
        $this->luckydraw = Factory::create('LuckyDraw');

        Config::set('orbit.shop.id', $this->luckydraw->mall->merchant_id);
    }

    public function testIssueFiveLuckyDrawNumber()
    {
        $user = Factory::create('User');

        $this->genLuckyDrawNumber(1001, 1050);

        // Set the client API Keys
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['user_id'] = $user->user_id;
        $_POST['lucky_draw_id'] = $this->luckydraw->lucky_draw_id;
        $_POST['lucky_draw_number_start'] = 1001;
        $_POST['lucky_draw_number_end'] = 1005;

        // 250,000 x 4 = 1,000,000
        $receipts = $this->genReceipts($this->luckydraw->mall, $user, 4);
        $_POST['receipts'] = json_encode($receipts);

        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url)->getContent();
        $response = json_decode($response);

        // Check the response
        $this->assertSame('0', (string)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('5', (string)$response->data->total_records);
        $this->assertSame('5', (string)$response->data->returned_records);

        // Number of receipt which has the same receipt group (hash) should be 4
        $hash = $response->data->records[0]->hash;
        $numberOfReceipt = LuckyDrawReceipt::excludeDeleted()
                                           ->where('receipt_group', $hash)
                                           ->count();
        $this->assertSame(4, $numberOfReceipt);
    }

    public function testIssueFiveLuckyDrawNumberThenThree()
    {
        $user = Factory::create('User');

        $this->genLuckyDrawNumber(1001, 1050);

        // Set the client API Keys
        // -- First Request
        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['user_id'] = $user->user_id;
        $_POST['lucky_draw_id'] = $this->luckydraw->lucky_draw_id;
        $_POST['lucky_draw_number_start'] = 1001;
        $_POST['lucky_draw_number_end'] = 1005;

        // 250,000 x 4 = 1,000,000
        $receipts = $this->genReceipts($this->luckydraw->mall, $user, 4);
        $_POST['receipts'] = json_encode($receipts);

        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url)->getContent();

        // -- Second Request
        // -- First Request
        $_GET = [];
        $_POST = [];

        $_GET['apikey'] = $this->apikey->api_key;
        $_GET['apitimestamp'] = time();

        $_POST['user_id'] = $user->user_id;
        $_POST['lucky_draw_id'] = $this->luckydraw->lucky_draw_id;
        $_POST['lucky_draw_number_start'] = 1006;
        $_POST['lucky_draw_number_end'] = 1008;

        // 250,000 x 4 = 1,000,000
        $receipts = $this->genReceipts($this->luckydraw->mall, $user, 10);
        $_POST['receipts'] = json_encode($receipts);

        $url = $this->apiUrl . '?' . http_build_query($_GET);

        $secretKey = $this->apikey->api_secret_key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = Generator::genSignature($secretKey, 'sha256');

        $response = $this->call('POST', $url)->getContent();

        $response = json_decode($response);

        // Check the response
        $this->assertSame('0', (string)$response->code);
        $this->assertSame('success', $response->status);
        $this->assertSame('Request OK', $response->message);
        $this->assertSame('3', (string)$response->data->total_records);
        $this->assertSame('3', (string)$response->data->returned_records);

        $last = end($response->data->records);
        $this->assertSame('1008', (string)$last->lucky_draw_number_code);

        // Number of receipt which has the same receipt group (hash) should be 10
        $hash = $response->data->records[0]->hash;
        $numberOfReceipt = LuckyDrawReceipt::excludeDeleted()
                                           ->where('receipt_group', $hash)
                                           ->count();
        $this->assertSame(10, $numberOfReceipt);
    }

    protected function genLuckyDrawNumber($min, $max)
    {
        DB::connection()->getPdo()->beginTransaction();

        // Generate X lucky draw number
        $args = [
            $min,
            $max,
            $this->luckydraw->lucky_draw_id,
            $this->apikey->user_id
        ];

        DB::statement("call generate_lucky_draw_number(?, ?, ?, ?)", $args);
        DB::connection()->getPdo()->commit();
    }

    protected function genReceipts($mall, $user, $number)
    {
        $receipts = [];
        $retailer = Factory::create('Retailer');
        $faker = Faker::create();

        for ($i=0; $i<$number; $i++) {
            $receipt = new stdClass();
            $receipt->mall_id = $mall->merchant_id;
            $receipt->user_id = $user->user_id;
            $receipt->receipt_retailer_id = $retailer->merchant_id;
            $receipt->receipt_number = $faker->randomNumber();
            $receipt->receipt_date = $faker->date() . ' ' . $faker->time();
            $receipt->receipt_payment_type = 'credit_card';
            $receipt->receipt_card_number = $faker->creditCardNumber;
            $receipt->receipt_amount = 250000;
            $receipt->external_receipt_id = $faker->numerify('EXT?????');

            $receipts[] = $receipt;
        }

        return $receipts;
    }
}