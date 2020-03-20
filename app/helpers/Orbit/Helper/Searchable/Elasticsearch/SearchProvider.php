<?php

namespace Orbit\Helper\Searchable\Elasticsearch;

use Config;
use Elasticsearch\ClientBuilder;
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

    public function search($query)
    {
        $result = $this->client->search($query);

        ElasticsearchErrorChecker::throwExceptionOnDocumentError($result);

        return $result['hits'];
    }
}
