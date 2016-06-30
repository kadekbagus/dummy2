<?php
/**
 * Unit testing for notifier to send to the Elasticsearch when mall
 * gets updated.
 *
 * Example response when document created:
 * {
 *   "_index": "malls",
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
 * @author Irianto <irianto@dominopos.com>
 */
use Laracasts\TestDummy\Factory;
use Orbit\Queue\Elasticsearch\ESMallUpdateQueue;

class ESMallUpdateQueueTest extends ElasticsearchTestCase
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
                    'type'  => $this->esIndexType
                ]
            ]
        ]);
    }

    public function test_should_return_object_instance()
    {
        $object = new ESMallUpdateQueue();
        $this->assertInstanceOf('Orbit\Queue\Elasticsearch\ESMallUpdateQueue', $object);
    }

    public function test_should_update_mall_and_indexed_to_elasticsearch_when_not_found_id()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');
        $mall = $geofence->mall;

        // update mall name
        $mall->name = 'irianto';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_update_mall_and_indexed_to_elasticsearch_when_found_id()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');
        $mall = $geofence->mall;

        // update mall name when doesnt exist elasticsearch index
        $mall->name = 'irianto';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        // update mall name when exist elasticsearch index
        $mall->name = 'antok';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('update')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_update_mall_and_indexed_to_elasticsearch_even_mall_does_not_have_geofence()
    {
        // Create mall in antartica
        $mall = Factory::create('Mall');

        // update mall name when doesnt exist elasticsearch index
        $mall->name = 'irianto';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        // update mall name
        $mall->name = 'sudirman';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('update')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_update_mall_and_indexed_to_elasticsearch_even_mall_does_not_have_area()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence', ['area' => NULL]);
        $mall = $geofence->mall;

        // update mall name when doesnt exist elasticsearch index
        $mall->name = 'irianto';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        // Update mall name
        $mall->name = 'budiana';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('update')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_update_mall_and_indexed_to_elasticsearch_even_mall_does_not_have_position()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence', ['position' => NULL]);
        $mall = $geofence->mall;

        // update mall name when doesnt exist elasticsearch index
        $mall->name = 'irianto';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);

        // update mall name
        $mall->name = 'sutiana';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('update')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
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
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_not_indexed_to_elasticsearch_because_json_error()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');
        $mall = $geofence->mall;

        // update mall name when doesnt exist elasticsearch index
        $mall->name = 'irianto';
        $mall->save();

        $elasticQueueUpdate = new ESMallUpdateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $mockResponse = [
            'error'  => [
                'type'   => 'mapper_parsing_exception',
                'reason' => 'failed to parse'
            ],
            'status' => 0
        ];
        $this->es->method('index')->willReturn($mockResponse);

        // Fire the event
        $response = $elasticQueueUpdate->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
            $this->job->getJobId(),
            $this->esIndex,
            $this->esIndexType,
            $mockResponse['status'],
            'Reason: ' . $mockResponse['error']['reason'] . ' - Type: ' . $mockResponse['error']['type']
        );

        $this->assertSame('fail', $response['status']);
        $this->assertSame($message, $response['message']);
    }
}
