<?php namespace Orbit\Helper\Elasticsearch;
/**
 * Simplify error checking when doing a call to Elasticsearch server.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Exception;

class ElasticsearchErrorChecker
{
    /**
     * Throw an exception if there is an error when doing index for
     * a document.
     *
     * @param array $response
     * @return void
     * @throws Exception
     */
    public static function throwExceptionOnDocumentError($response)
    {
        if (isset($response['error'])) {
            throw new Exception('Reason: ' . $response['error']['reason'] . ' - Type: ' . $response['error']['type'], $response['status']);
        }

        if (! isset($response['_shards'])) {
            throw new Exception('Failed to find _shards index on response.');
        }

        $_shards = $response['_shards'];
        if (isset($_shards['successful']) && $_shards['successful'] < 1) {
            throw new Exception('The document indexing seems fail because the successful value is less than 1.', 1);
        }
    }
}
