<?php
/**
 * Unit testing for notifier to send to the Elasticsearch when activity
 * gets updated.
 *
 * Example response when document created:
 * {
 *   "_index": "activities",
 *   "_type": "basic",
 *   "_id": "abc123",
 *   "_version": 1,
 *   "_shards": {
 *     "total": 2,
 *     "successful": 1,
 *     "failed": 0
 *   },
 *   "created": false
 * }
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use Laracasts\TestDummy\Factory;
use Orbit\Queue\Elasticsearch\ESActivityUpdateQueue;

class ESActivityUpdateQueueTest extends ElasticsearchTestCase
{
    public function setUp()
    {
        parent::setUp();

        Config::set('orbit.elasticsearch', [
            'hosts' => [
                'http://localhost:9200'
            ],

            // List of indices that we have in Elasticsearch
            'indices' => [
                'malldata' => [
                    'index' => $this->esIndex,
                    'type'  => $this->esIndexTypeActivity
                ]
            ]
        ]);

        Config::set('orbit.activity.force.save', TRUE);
        Config::set('orbit.elasticsearch.indices.activities.index', $this->esIndex);
        Config::set('orbit.elasticsearch.indices.activities.type', $this->esIndexTypeActivity);
    }

    public function test_should_return_object_instance()
    {
        $object = new ESActivityUpdateQueue();
        $this->assertInstanceOf('Orbit\Queue\Elasticsearch\ESActivityUpdateQueue', $object);
    }

    public function tearDown()
    {
        $this->useTruncate = false;

        parent::tearDown();
    } 

    public function test_should_update_mall_and_indexed_to_elasticsearch_when_not_found_id()
    {
        // Create activity
        $activity = Factory::create('Activity', ['group' => 'mobile-ci']);
        $activityId = $activity->activity_id;

        $elasticQueueUpdate = new ESActivityUpdateQueue($this->es);
        $data = ['activity_id' => $activityId];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexTypeActivity,
            '_id'     => $activityId,
            'created' => 1,
            '_shards' => [
                'total'      => 2,
                'successful' => 1,
                'failed'     => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueUpdate->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
            $this->job->getJobId(), $this->esIndex, $this->esIndexTypeActivity, $activityId
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_update_activities_and_indexed_to_elasticsearch_when_found_id()
    {
        // Create activity
        $activity = Factory::create('Activity', ['group' => 'mobile-ci']);
        $activityId = $activity->activity_id;

        $elasticQueueUpdate = new ESActivityUpdateQueue($this->es);
        $data = ['activity_id' => $activityId];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexTypeActivity,
            '_id'     => $activityId,
            'created' => 1,
            '_shards' => [
                'total'      => 2,
                'successful' => 1,
                'failed'     => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueUpdate->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
            $this->job->getJobId(), $this->esIndex, $this->esIndexTypeActivity, $activityId
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        // update mall name when exist elasticsearch index
        $activity->activity_name = 'just for testing';
        $activity->ip_address = '127.0.0.1';
        $activity->save();

        $elasticQueueUpdate = new ESActivityUpdateQueue($this->es);
        $data = ['activity_id' => $activityId];

        // Mock the response of ES->index($params)
        $this->es->method('update')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexTypeActivity,
            '_id'     => $activityId,
            'created' => 0,
            '_shards' => [
                'total'      => 2,
                'successful' => 1,
                'failed'     => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueUpdate->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
            $this->job->getJobId(), $this->esIndex, $this->esIndexTypeActivity, $activityId
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

}
