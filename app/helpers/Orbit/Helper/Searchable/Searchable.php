<?php

namespace Orbit\Helper\Searchable;

use App;
use Config;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Base implementation of searchable model.
 *
 * @todo Support cache? Not sure we need cache because landing page also
 *       caches the result each time it gets data from API.
 *       (most of the time wrapped in ApiCache)
 *
 * @todo Support scrolling
 * @todo Support for MySQL SearchProvider.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait Searchable
{
    /**
     * The search provider. At the moment we use Elasticsearch, so it should
     * be an instance of SearchProviderInterface implemented by
     * Searchable\Elasticsearch\SearchProvider.
     *
     * @see Searchable\SearchProviderInterface
     * @see Searchable\Elasticsearch\SearchProvider
     *
     * @var Searchable\SearchProviderInterface
     */
    protected $searchProvider = null;

    /**
     * Flag that indicate that it should only count the records.
     * @var bool
     */
    protected $countOnly = false;

    /**
     * Get (build?) the search query.
     *
     * @param  ValidateRequest $request a ValidateRequest instance.
     * @return array search query
     */
    abstract public function getSearchQueryBuilder($request);

    /**
     * Set searchable to only count instead of the returning the records.
     *
     * @return self
     */
    public function countOnly()
    {
        $this->countOnly = true;

        return $this;
    }

    /**
     * Basic search function.
     *
     * @param  array|ValidateRequest|ESQueryBuilder $query [description]
     * @return array|Collection $result search result from SearchProvider.
     */
    public function search($query)
    {
        // Instantiate SearchProvider if needed.
        if (empty($this->searchProvider)) {
            $this->searchProvider = App::make(SearchProviderInterface::class);
        }

        // If $query is a ValidateRequest instance, then we should build
        // the search query with DataBuilder helper.
        if ($query instanceof ValidateRequest) {
            $query = $this->getSearchQueryBuilder($query)->build();
        }

        // Otherwise, we assume $query is an array that ready to be passed to
        // SearchProvider.

        if ($this->countOnly) {
            return $this->searchProvider->count($query);
        }

        return $this->searchProvider->search($query);
    }
}
