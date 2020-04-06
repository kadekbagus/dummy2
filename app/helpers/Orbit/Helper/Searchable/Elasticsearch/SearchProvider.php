<?php

namespace Orbit\Helper\Searchable\Elasticsearch;

use Config;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Exception;
use Orbit\Helper\Elasticsearch\ESErrorChecker;
use Orbit\Helper\Elasticsearch\ESException;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Searchable\SearchProviderInterface;

/**
 * Elasticsearch search provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SearchProvider implements SearchProviderInterface
{
    protected $client;

    function __construct()
    {
        $config = Config::get('orbit.elasticsearch');
        $this->client = new ClientBuilder;
        $this->client = $this->client->create()->setHosts($config['hosts'])
            ->build();
    }

    /**
     * Implement ES document count functionality.
     *
     * @param  array $query search/count query.
     * @return int $result result count
     */
    public function count($query)
    {
        try {
            $result = $this->client->count($query);
        } catch (Exception $e) {
            throw new ESException($e->getMessage(), $e->getCode());
        }

        return $result['count'];
    }

    /**
     * Implement the search to ES Server.
     *
     * @throws ESException
     * @param  array $query the search query
     * @return array $result search result
     */
    public function search($query)
    {
        try {
            $result = $this->client->search($query);
        } catch (Exception $e) {
            throw new ESException($e->getMessage(), $e->getCode());
        }

        return $result;
    }

    /**
     * Implement scroll support for.
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scroll($query)
    {
        try {
            $result = $this->client->scroll($query);
        } catch (Exception $e) {
            throw new ESException($e->getMessage(), $e->getCode());
        }

        return $result;
    }
}
