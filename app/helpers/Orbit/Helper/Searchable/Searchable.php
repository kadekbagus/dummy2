<?php

namespace Orbit\Helper\Searchable;

use App;
use Config;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Base implementation of searchable eloquent model.
 *
 * @todo Support cache? Not sure we need cache because landing page also
 *       caches the result each time (most of the time wrapped in ApiCache)
 *       it gets data from API.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait Searchable
{
    /**
     * The search provider. At the moment we use Elasticsearch, so it should
     * be an instance of Elasticsearch\ClientBuilder. Later, should be wrapped
     * in a SearchProvider class somehow to unify the api.
     *
     * @see Elasticsearch\ClientBuilder
     *
     * @var null
     */
    protected $searchProvider = null;

    protected $query = null;

    /**
     * Get (build?) the search query.
     *
     * @param  ValidateRequest $request a ValidateRequest instance.
     * @return array search query
     */
    abstract public function getSearchQueryBuilder();

    /**
     * Basic search function.
     *
     * @param  array|ValidateRequest|ESQueryBuilder $query [description]
     * @return [type]        [description]
     */
    public function search($query)
    {
        if (empty($this->searchProvider)) {
            $this->searchProvider = App::make(SearchProviderInterface::class);
        }

        if ($query instanceof ValidateRequest) {
            $query = $this->getSearchQueryBuilder($query)->build();
        }

        return $this->searchProvider->search($query);
    }
}
