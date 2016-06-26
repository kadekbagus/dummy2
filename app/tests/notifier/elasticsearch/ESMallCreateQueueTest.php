<?php
/**
 * Unit testing for notifier to send to the Elasticsearch when mall
 * gets created.
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
 *   "created": true
 * }
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Laracasts\TestDummy\Factory;
use Orbit\Queue\Elasticsearch\ESMallCreateQueue;

class ESMallCreateQueueTest extends ElasticsearchTestCase
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
        $object = new ESMallCreateQueue();
        $this->assertInstanceOf('Orbit\Queue\Elasticsearch\ESMallCreateQueue', $object);
    }

    public function test_should_create_mall_and_indexed_to_elasticsearch()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');
        $mall = $geofence->mall;

        $elasticQueue = new ESMallCreateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $this->es->method('index')->willReturn([
            '_index' => $this->esIndex,
            '_type' => $this->esIndexType,
            '_id' => $mall->merchant_id,
            'created' => 1,
            '_shards' => [
                'total' => 2,
                'successful' => 1,
                'failed' => 0
            ]
        ]);

        // Fire the event
        $response = $elasticQueue->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
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

        $elasticQueue = new ESMallCreateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $mockResponse = [
            'error' => [
                'type' => 'mapper_parsing_exception',
                'reason' => 'failed to parse'
            ],
            'status' => 400
        ];
        $this->es->method('index')->willReturn($mockResponse);

        // Fire the event
        $response = $elasticQueue->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
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

        $elasticQueue = new ESMallCreateQueue($this->es);
        $data = ['mall_id' => $mall->merchant_id];

        // Mock the response of ES->index($params)
        $mockResponse = [
            '_index' => $this->esIndex,
            '_type' => $this->esIndexType,
            '_id' => $mall->merchant_id,
            'created' => 1,
            '_shards' => [
                'total' => 2,
                'successful' => 0,
                'failed' => 0
            ]
        ];
        $this->es->method('index')->willReturn($mockResponse);

        // Fire the event
        $response = $elasticQueue->fire($this->job, $data);

        $message = sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
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
