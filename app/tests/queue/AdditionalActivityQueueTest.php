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
        $this->referer = 'https://www.facebook.com';
        $this->currentUrl = 'https://www.gotomalls.com';
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

    public function test_OK_saveToCampaignPageViews()
    {
        $this->activity->activity_name = 'view_promotion';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl
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
        $this->activity->activity_name = 'view_coupon_pop_up';
        $this->activity->activity_name_long = 'View Coupon Pop Up';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl
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
        $this->activity->activity_name = 'click_coupon_pop_up';
        $this->activity->activity_name_long = 'Click Coupon Pop Up';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl
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
        $this->activity->activity_name = 'view_retailer';
        $this->activity->activity_name_long = 'View Tenant Detail';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl
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
        $this->activity->activity_name = 'widget_click';
        $this->activity->activity_name_long = 'Widget Click Promotion';
        $this->activity->object_id = 'Th30BjECt-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl
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
        $this->activity->activity_name = 'login_ok';
        $this->activity->activity_name_long = 'Widget Click Promotion';
        $this->activity->session_id = '53sS10N-Id';
        $this->activity->save();
        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl,
            'datetime' => date('Y-m-d H:i:s')
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

    public function test_OK_saveExtendedData()
    {
        $this->activity->activity_name = 'view_news_main_page';
        $this->activity->activity_name_long = 'View News Main Page';
        $this->activity->activity_name_long = 'View News Main Page';
        $this->activity->session_id = '53sS10N-Id';
        $this->activity->save();
        $query = [
            'country' => 'Indonesia',
            'cities' => ['Bali', 'Surabaya'],
            'keyword' => 'Shoes',
            'category_id' => ['C4t3G0Ry_Id'],
            'partner_id' => 'P4rtN3R_Id',
        ];

        $queryString = http_build_query($query);

        $this->currentUrl = 'http://www.gotomalls.com/app/v1/news-list?' . $queryString;

        $data = [
            'activity_id' => $this->activity->activity_id,
            'referer' => $this->referer,
            'current_url' => $this->currentUrl,
            'datetime' => date('Y-m-d H:i:s')
        ];
        $queueObj = new AdditionalActivityQueue();
        $response = $queueObj->fire($this->job, $data);
        $message = sprintf('[Job ID: `%s`] Additional Activity Queue; Status: OK; Activity ID: %s; Activity Name: %s',
                    $this->job->getJobId(),
                    $this->activity->activity_id,
                    $this->activity->activity_name_long);

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        $extendedActivity = ExtendedActivity::where('activity_id', $this->activity->activity_id)->first();
        $this->assertTrue(is_object($extendedActivity));
    }
}
