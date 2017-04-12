<?php
/**
 * Unit testing for AdditionalActivityQueue queue
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
use Laracasts\TestDummy\Factory;
use Orbit\Queue\Activity\AdditionalActivityQueue;

class AdditionalActivityQueueTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $stubJob = $this->getMockBuilder('Illuminate\Queue\Jobs\SyncJob')
                        ->disableOriginalConstructor()
                        ->getMock();

        $stubJob->expects($this->any())
            ->method('getJobId')
            ->will($this->returnValue(mt_rand(0, 100)));

        $this->job = $stubJob;

        $this->activity = Factory::create('Activity', ['group' => 'mobile-ci']);
    }

    public function test_OK_object_instance()
    {
        $queueObj = new AdditionalActivityQueue();
        $this->assertInstanceOf('Orbit\Queue\Activity\AdditionalActivityQueue', $queueObj);
    }

    public function test_FAIL_save_missing_data()
    {
        $data = null;
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Activity ID %s is not found.', $this->job->getJobId(), '');

        $this->assertSame('fail', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_FAIL_save_missing_referer()
    {
        $data = [
            'activity_id' => $this->activity->activity_id,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = 'Undefined index: referer';

        $this->assertSame('fail', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_FAIL_save_missing_orbit_referer()
    {
        $referer = 'xxx';
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = 'Undefined index: orbit_referer';

        $this->assertSame('fail', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_OK_saveToCampaignPageViews()
    {
        $referer = 'xxx';
        $orbitReferer = 'yyy';
        $this->activity->activity_name = 'view_promotion';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
            'orbit_referer' => $orbitReferer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $campaignPageViews = CampaignPageView::where('activity_id', $this->activity->activity_id)->first();
        $this->assertTrue(is_object($campaignPageViews));
    }

    public function test_OK_saveToCampaignPopUpView()
    {
        $referer = 'xxx';
        $orbitReferer = 'yyy';
        $this->activity->activity_name = 'view_coupon_pop_up';
        $this->activity->activity_name_long = 'View Coupon Pop Up';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
            'orbit_referer' => $orbitReferer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $campaignPopupView = CampaignPopupView::where('activity_id', $this->activity->activity_id)->first();
        $this->assertTrue(is_object($campaignPopupView));
    }

    public function test_OK_saveToCampaignPopUpClick()
    {
        $referer = 'xxx';
        $orbitReferer = 'yyy';
        $this->activity->activity_name = 'click_coupon_pop_up';
        $this->activity->activity_name_long = 'Click Coupon Pop Up';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
            'orbit_referer' => $orbitReferer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $campaignClicks = CampaignClicks::where('activity_id', $this->activity->activity_id)->first();
        $this->assertTrue(is_object($campaignClicks));
    }

    public function test_OK_saveToMerchantPageView()
    {
        $referer = 'xxx';
        $orbitReferer = 'yyy';
        $this->activity->activity_name = 'view_retailer';
        $this->activity->activity_name_long = 'View Tenant Detail';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
            'orbit_referer' => $orbitReferer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $merchantPageView = MerchantPageView::where('activity_id', $this->activity->activity_id)->first();
        $this->assertTrue(is_object($merchantPageView));
    }

    public function test_OK_saveToWidgetClick()
    {
        $referer = 'xxx';
        $orbitReferer = 'yyy';
        $this->activity->activity_name = 'widget_click';
        $this->activity->activity_name_long = 'Widget Click Promotion';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
            'orbit_referer' => $orbitReferer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $widgetClick = WidgetClick::where('activity_id', $this->activity->activity_id)->first();
        $this->assertTrue(is_object($widgetClick));
    }

    public function test_OK_saveToConnectionTime()
    {
        $referer = 'xxx';
        $orbitReferer = 'yyy';
        $this->activity->activity_name = 'login_ok';
        $this->activity->activity_name_long = 'Widget Click Promotion';
        $this->activity->session_id = '53sS10N-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $referer,
            'orbit_referer' => $orbitReferer,
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $connectionTime = ConnectionTime::where('session_id', $this->activity->session_id)->first();
        $this->assertTrue(is_object($connectionTime));
    }
}
