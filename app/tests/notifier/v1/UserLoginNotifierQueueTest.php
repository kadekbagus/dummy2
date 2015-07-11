<?php
/**
 * Unit testing for queue notifier Orbit\Queue\Notifier\UserLoginNotifier
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Orbit\Queue\Notifier\UserLoginNotifier as QUserLoginNotifier;
use Laracasts\TestDummy\Factory;

class UserLoginNotifierQueueTest extends TestCase
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
    protected $configPrefix = 'orbit-notifier.user-login.';

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

        $stubJob = $this->getMockBuilder('Illuminate\Queue\Jobs\Job')->getMock();
        $stubJob->method('delete')->willReturn('job deleted');

        $this->job = $stubJob;

        $stubCurl = $this->getMockBuilder('CurlWrapper')->getMock();
        $this->curlWrapper = $stubCurl;

        Config::set('orbit-notifier.user-agent', 'Orbit API Notifier/1');
        $this->configSample = [
            'url' => 'http://dummy.url/orbit-notify/v1/check-member',
            'auth_type' => '',
            'auth_user' => '',
            'auth_password' => '',
            'notify_order' => 0,
            'enabled' => 1, // 1=enabled or 0=disabled
        ];

        // {
        //     "code": 0,
        //     "status": "success",
        //     "message": "Some message",
        //     "data": {
        //         "user_id": 10,
        //         "external_user_id": "C99",
        //         "user_email": "user@example.com",
        //         "user_firstname": "John",
        //         "user_lastname": "Doe",
        //         "membership_number": "98754221",
        //         "membership_since": "2015-02-05 11:22:00"
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
        $queueNotifier = new QUserLoginNotifier();
        $this->assertInstanceOf('Orbit\Queue\Notifier\UserLoginNotifier', $queueNotifier);
    }

    public function testFail_EmptyConfigData()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $qNotifier = new QUserLoginNotifier();

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('There is no user-login notify data found for retailer id %s.', $retailer->merchant_id);
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_ConfigDataExists_butDisabled()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;
        $qNotifier = new QUserLoginNotifier();

        // Set the notifiy data to 'enabled' to 0 (disabled)
        $userLoginConfig = ['enabled' => 0] + $this->configSample;
        Config::set($this->configPrefix . $retailer->merchant_id, $userLoginConfig);

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('Notify user-login found for retailer id %s but it was disabled.', $retailer->merchant_id);
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_Non200OK_Status()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;

        // Replace the getTransferInfo() method to return 404
        $this->curlWrapper->method('getTransferInfo')->willReturn(404);

        $qNotifier = new QUserLoginNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
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

        // Replace the getTransferInfo() method to return 404
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);

        // Replace the getResponse() method so it return json
        $json = $this->responseSample;
        $json->code = 5;
        $json->status = 'error';
        $this->curlWrapper->method('getResponse')->willReturn(json_encode($json));

        $qNotifier = new QUserLoginNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                'Unexpected response code 5, expected 0 (zero)'
        );
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_userIdReturned_NotSame()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;

        // Replace the getTransferInfo() method to return 200
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);
        $this->responseSample->data->user_id = $user->user_id + 1;

        $this->curlWrapper->method('getResponse')->willReturn(json_encode($this->responseSample));

        $qNotifier = new QUserLoginNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                sprintf('User Id is not same, expected %s got %s', $user->user_id, $user->user_id + 1)
        );
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testFail_userEmailReturned_NotSame()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;

        // Replace the getTransferInfo() method to return 200
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);

        $wrongEmail = $user->user_email . '.fail';
        $this->responseSample->data->user_email = $wrongEmail;

        $this->curlWrapper->method('getResponse')->willReturn(json_encode($this->responseSample));

        $qNotifier = new QUserLoginNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('fail', $response['status']);

        $errorMessage = sprintf('Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.',
                                $user->user_id,
                                $retailer->merchant_id,
                                $this->configSample['url'],
                                $errorMessage = sprintf('Email address is not same, expected %s got %s', $user->user_email, $wrongEmail)
        );
        $this->assertSame($errorMessage, $response['message']);
    }

    public function testOK_JobFiredSuccessfully()
    {
        $retailer = Factory::create('Retailer');
        $user = $retailer->user;

        // Replace the getTransferInfo() method to return 200
        $this->curlWrapper->method('getTransferInfo')->willReturn(200);

        $url = $this->configSample['url'];
        $this->responseSample->data->user_id = $user->user_id;
        $this->responseSample->data->external_user_id = 'EXT' . $user->user_id;
        $this->responseSample->data->user_email = $user->user_email;

        // Just making sure it is not the same as the one generated by Faker
        $hash = md5(microtime());
        $this->responseSample->data->user_firstname = 'John ' . $hash;
        $this->responseSample->data->user_lastname = 'Doe ' . $hash;

        $this->responseSample->data->membership_number = 'C123';
        $this->responseSample->data->membership_since = date('Y-m-d H:i:s', strtotime('last week'));

        $this->curlWrapper->method('getResponse')->willReturn(json_encode($this->responseSample));

        $qNotifier = new QUserLoginNotifier($this->curlWrapper);

        Config::set($this->configPrefix . $retailer->merchant_id, $this->configSample);

        $data = ['user_id' => $user->user_id, 'retailer_id' => $retailer->merchant_id];
        $response = $qNotifier->fire($this->job, $data);

        $this->assertSame('ok', $response['status']);
        $message = sprintf('Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Success.',
                            $user->user_id, $retailer->merchant_id, $url);
        $this->assertSame($message, $response['message']);

        // Check the updated user
        $updatedUser = User::find($user->user_id);
        $this->assertSame($this->responseSample->data->external_user_id, (string)$updatedUser->external_user_id);
        $this->assertSame($this->responseSample->data->user_firstname, (string)$updatedUser->user_firstname);
        $this->assertSame($this->responseSample->data->user_lastname, (string)$updatedUser->user_lastname);
        $this->assertSame($this->responseSample->data->membership_number, (string)$updatedUser->membership_number);
        $this->assertSame($this->responseSample->data->membership_since, (string)$updatedUser->membership_since);
    }
}