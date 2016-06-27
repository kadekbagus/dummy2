<?php
/**
 * Basic skeleton for Elasticsearch unit test.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;

class ElasticsearchTestCase extends TestCase
{
    protected $job = NULL;
    protected $esIndex = 'gotomalls_test';
    protected $esIndexType = 'malls';
    protected $es = NULL;

    public function setUp()
    {
        parent::setUp();

        // Mock the Jobs\SyncJob so we got some method which unavailable i.e getJobId()
        // on the abstract Job class
        $stubJob = $this->getMockBuilder('Illuminate\Queue\Jobs\SyncJob')
                        ->disableOriginalConstructor()
                        ->getMock();
        $stubJob->method('delete')->willReturn('job deleted');
        $stubJob->method('bury')->willReturn('job buried');
        $stubJob->method('getJobId')->willReturn(mt_rand(0, 100));

        $this->job = $stubJob;

        // Mock the Elasticsearch\Client
        $this->es = $this->getMockBuilder('Elasticsearch\Client')
                      ->disableOriginalConstructor()
                      ->getMock();
    }
}