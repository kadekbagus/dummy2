<?php
/**
 * Unit testing for queue notifier Orbit\Queue\Notifier\LuckyDrawNumberNotifier
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Orbit\Queue\Notifier\LuckyDrawNumberNotifier as QLDNumberNotifier;
use Laracasts\TestDummy\Factory;

class LuckyDrawNumberNotifierQueueTest extends TestCase
{
    /**
     * Hold the instance of CurlWrapper Mockup object.
     *
     * @var object
     */
    protected $curlWrapper = NULL;

    /**
     * Hold the instance of Job Mockup object.
     *
     * @var object
     */
    protected $job = NULL;

    /**
     * Config name prefix.
     *
     * @var string
     */
    protected $configPrefix = 'orbit-notifier.lucky-draw-number.';

    /**
     * Config sample data.
     *
     * @var array
     */
    protected $configSample = NULL;

    /**
     * Sample response data from external system
     *
     * @var Object
     */
    protected $responseSample = NULL;

    public function setUp()
    {
        parent::setUp();

        // Mock the Jobs\SyncJob so we got some method which unavailable i.e getJobId()
        // on the abstract Job class
        $stubJob = $this->getMockBuilder('Illuminate\Queue\Jobs\SyncJob')
                        ->disableOriginalConstructor()
                        ->getMock();
        $stubJob->method('delete')->willReturn('job deleted');
        $stubJob->method('getJobId')->willReturn(mt_rand(0, 100));

        $this->job = $stubJob;

        $stubCurl = $this->getMockBuilder('CurlWrapper')->getMock();
        $this->curlWrapper = $stubCurl;

        Config::set('orbit-notifier.user-agent', 'Orbit API Notifier/1');
        $this->configSample = [
            'url' => 'http://dummy.url/orbit-notify/v1/lucky-draw-number',
            'auth_type' => '',
            'auth_user' => '',
            'auth_password' => '',
            'notify_order' => 0,
            'enabled' => 1, // 1=enabled or 0=disabled

            // How long job will be release back onto the queue in seconds
            'release_time' => 300,

            // How many times we need to try before we delete the job
            'max_try' => 5,

            // Should we send email to notify that the job is failed?
            'email_on_failed' => FALSE,

            // Email address to send on job failed
            'email_addr' => [
                'Admin' => 'backend@dominopos.com'
            ]
        ];

        // Sample response
        // {
        //     "code": 0,
        //     "status": "success",
        //     "message": "Some message",
        //     "data": {
        //         "lucky_draw_id": 1,
        //         "receipt_group": "1234567890abcdef",
        //         "lucky_draw_number_start": 5001,
        //         "lucky_draw_number_end": 5005
        //     }
        // }
        $json = new stdClass();
        $json->code = 0;
        $json->status = 'success';
        $json->message = 'Some message';

        $json->data = new stdClass();
        $json->data->user_id = NULL;
        $json->data->external_user_id = NULL;
        $json->data->user_email = NULL;
        $json->data->user_firstname = NULL;
        $json->data->user_lastname = NULL;
        $json->data->membership_number = NULL;
        $json->data->membership_since = NULL;

        $this->responseSample = $json;
    }

    public function testObjectInstance()
    {
        $queueNotifier = new QLDNumberNotifier();
        $this->assertInstanceOf('Orbit\Queue\Notifier\LuckyDrawNumberNotifier', $queueNotifier);
    }

    public function testFail_EmptyConfigData()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $qNotifier = new QLDNumberNotifier();
        $luckyDraw = Factory::create('LuckyDraw', ['mall_id' => $retailer->merchant_id]);

        $data = [
                'user_id' => $user->user_id,
                'retailer_id' => $retailer->merchant_id,
                'hash' => md5(time()),
                'lucky_draw_id' => $luckyDraw->lucky_draw_id
        ];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('[Job ID: `%s`] There is no lucky-draw-number notify data found for retailer id %s.',
                                $this->job->getJobId(), $retailer->merchant_id);
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_ConfigDataExists_butDisabled()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $qNotifier = new QLDNumberNotifier();
        $luckyDraw = Factory::create('LuckyDraw', ['mall_id' => $retailer->merchant_id]);

        // Set the notifiy data to 'enabled' to 0 (disabled)
        $userLoginConfig = ['enabled' => 0] + $this->configSample;
        Config::set($this->configPrefix . $retailer->merchant_id, $userLoginConfig);

        $data = [
                'user_id' => $user->user_id,
                'retailer_id' => $retailer->merchant_id,
                'hash' => md5(time()),
                'lucky_draw_id' => $luckyDraw->lucky_draw_id
        ];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('[Job ID: `%s`] Notify lucky-draw-number found for retailer id %s but it was disabled.',
                                $this->job->getJobId(), $retailer->merchant_id);
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_Non200OK_Status()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $luckyDraw = Factory::create('LuckyDraw', ['mall_id' => $retailer->merchant_id]);

        // Replace the getTransferInfo() method to return 404
        $this->curlWrapper->method('getTransferInfo')->willReturn(404);

        $qNotifier = new QLDNumberNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

        $data = [
                'user_id' => $user->user_id,
                'retailer_id' => $retailer->merchant_id,
                'hash' => md5(time()),
                'lucky_draw_id' => $luckyDraw->lucky_draw_id
        ];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $this->job->getJobId(),
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                'Unexpected http response code 404, expected 200'
        );
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_NonZero_ReturnCode()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $luckyDraw = Factory::create('LuckyDraw', ['mall_id' => $retailer->merchant_id]);

        // Replace the getTransferInfo() method to return 404
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);

        // Replace the getResponse() method so it return json
        $json = $this->responseSample;
        $json->code = 5;
        $json->status = 'error';
        $this->curlWrapper->method('getResponse')->willReturn(json_encode($json));

        $qNotifier = new QLDNumberNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

                $data = [
                'user_id' => $user->user_id,
                'retailer_id' => $retailer->merchant_id,
                'hash' => md5(time()),
                'lucky_draw_id' => $luckyDraw->lucky_draw_id
        ];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $this->job->getJobId(),
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                'Unexpected response code 5, expected 0 (zero)'
        );
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_ReturnedLuckyDrawId_IsNotSame()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $luckyDraw = Factory::create('LuckyDraw', ['mall_id' => $retailer->merchant_id]);

        // Replace the getTransferInfo() method to return 404
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);

        // Replace the getResponse() method so it return json
        $json = $this->responseSample;
        $json->data->lucky_draw_id = $luckyDraw->lucky_draw_id . '99';
        $this->curlWrapper->method('getResponse')->willReturn(json_encode($json));

        $qNotifier = new QLDNumberNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

                $data = [
                'user_id' => $user->user_id,
                'retailer_id' => $retailer->merchant_id,
                'hash' => md5(time()),
                'lucky_draw_id' => $luckyDraw->lucky_draw_id
        ];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $idNotSame = sprintf('Lucky Draw Id is not same, expected %s got %s', $luckyDraw->lucky_draw_id, $json->data->lucky_draw_id);
        $errorMessage = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $this->job->getJobId(),
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                $idNotSame
        );
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_ReturnedReceiptGroup_IsNotSame()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $luckyDraw = Factory::create('LuckyDraw', ['mall_id' => $retailer->merchant_id]);
        $receipt = Factory::create('LuckyDrawReceipt', [
            'mall_id' => $retailer->merchant_id,
            'user_id' => $user->user_id,
            'receipt_group' => md5(time())
        ]);

        // Replace the getTransferInfo() method to return 404
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);

        // Replace the getResponse() method so it return json
        $json = $this->responseSample;
        $json->data->receipt_group = 'THIS-SHOULD-NOT-EXISTS';
        $this->curlWrapper->method('getResponse')->willReturn(json_encode($json));

        $qNotifier = new QLDNumberNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

                $data = [
                'user_id' => $user->user_id,
                'retailer_id' => $retailer->merchant_id,
                'hash' => md5(time()),
                'lucky_draw_id' => $luckyDraw->lucky_draw_id
        ];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $idNotSame = sprintf('Receipt group is not same, expected %s got %s', $receipt->receipt_group, $json->data->receipt_group);
        $errorMessage = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $this->job->getJobId(),
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                $idNotSame
        );
        $this->assertSame($errorMessage, $response['message']);
    }
}