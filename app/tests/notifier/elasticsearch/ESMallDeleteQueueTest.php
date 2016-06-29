<?php
/**
 * Unit testing for notifier to send to the Elasticsearch when mall
 * gets deleted.
 *
 * Example response when document created:
 * {
 *   "found": true,
 *   "_index": "malls",
 *   "_type": "mall",
 *   "_id": "abcs23",
 *   "_version": 2,
 *   "_shards": {
 *     "total": 2,
 *     "successful": 1,
 *     "failed": 0
 *   }
 * }
 *
 * @author Irianto <irianto@dominopos.com>
 */
use Laracasts\TestDummy\Factory;
use Orbit\Queue\Elasticsearch\ESMallDeleteQueue;

class ESMallDeleteQueueTest extends ElasticsearchTestCase
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
        $object = new ESMallDeleteQueue();
        $this->assertInstanceOf('Orbit\Queue\Elasticsearch\ESMallDeleteQueue', $object);
    }

    public function test_should_delete_mall_and_indexed_to_elasticsearch()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');
        $mall = $geofence->mall;

        // delete mall
        $mall->status = 'deleted';
        $mall->save();

        $elasticQueueDelete = new ESMallDeleteQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('delete')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
            'found'   => 1,
            '_shards' => [
                'total'      => 2,
                'successful' => 1,
                'failed'     => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueDelete->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_delete_mall_and_indexed_to_elasticsearch_even_mall_does_not_have_geofence()
    {
        // Create mall in antartica
        $mall = Factory::create('Mall');

        // delete mall
        $mall->status = 'deleted';
        $mall->save();

        $elasticQueueDelete = new ESMallDeleteQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('delete')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
            'found'   => 1,
            '_shards' => [
                'total'      => 2,
                'successful' => 1,
                'failed'     => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueDelete->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_delete_mall_and_indexed_to_elasticsearch_even_mall_does_not_have_area()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence', ['area' => NULL]);
        $mall = $geofence->mall;

        // Delete mall name
        $mall->status = 'deleted';
        $mall->save();

        $elasticQueueDelete = new ESMallDeleteQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('delete')->willReturn([
            '_index' => $this->esIndex,
            '_type' => $this->esIndexType,
            '_id' => $mall->merchant_id,
            'found' => 1,
            '_shards' => [
                'total' => 2,
                'successful' => 1,
                'failed' => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueDelete->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
            $this->job->getJobId(), $this->esIndex, $this->esIndexType, $mall->merchant_id
        );

        $this->assertSame('ok', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_delete_mall_and_indexed_to_elasticsearch_even_mall_does_not_have_position()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence', ['position' => NULL]);
        $mall = $geofence->mall;

        // delete mall
        $mall->status = 'deleted';
        $mall->save();

        $elasticQueueDelete = new ESMallDeleteQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('delete')->willReturn([
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
            'found'   => 1,
            '_shards' => [
                'total'      => 2,
                'successful' => 1,
                'failed'     => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueueDelete->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
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

        // delete mall
        $mall->status = 'deleted';
        $mall->save();

        $elasticQueueDelete = new ESMallDeleteQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $mockResponse = [
            'error'  => [
                'type'   => 'mapper_parsing_exception',
                'reason' => 'failed to parse'
            ],
            'status' => 400
        ];
        $this->es->method('delete')->willReturn($mockResponse);

        // Fire the event
        $response = $elasticQueueDelete->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
            $this->job->getJobId(),
            $this->esIndex,
            $this->esIndexType,
            $mockResponse['status'],
            'Reason: ' . $mockResponse['error']['reason'] . ' - Type: ' . $mockResponse['error']['type']
        );

        $this->assertSame('fail', $response['status']);
        $this->assertSame($message, $response['message']);
    }

    public function test_should_not_indexed_to_elasticsearch_because_successful_is_zero()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');
        $mall = $geofence->mall;

        // delete mall
        $mall->status = 'deleted';
        $mall->save();

        $elasticQueueDelete = new ESMallDeleteQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $mockResponse = [
            '_index'  => $this->esIndex,
            '_type'   => $this->esIndexType,
            '_id'     => $mall->merchant_id,
            'found'   => 1,
            '_shards' => [
                'total'      => 2,
                'successful' => 0,
                'failed'     => 0
            ]
        ];
        $this->es->method('delete')->willReturn($mockResponse);

        // Fire the event
        $response = $elasticQueueDelete->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
            $this->job->getJobId(),
            $this->esIndex,
            $this->esIndexType,
            1,
            'The document indexing seems fail because the successful value is less than 1.'
        );
        $this->assertSame('fail', $response['status']);
        $this->assertSame($message, $response['message']);
    }
}
