<?php

namespace Orbit\Helper\Searchable;

use App;
use Cache;
use Config;
use Orbit\Helper\Loggable\Loggable;
use Orbit\Helper\Request\ValidateRequest;
use Orbit\Helper\Util\SimpleCache;

/**
 * Base implementation of searchable model.
 *
 * @todo Support for MySQL SearchProvider.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait Searchable
{
    use Loggable;

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
     * Cache provider.
     * @var SimpleCache ?
     */
    protected $cache = null;

    /**
     * Get (build?) the search query.
     *
     * @param  ValidateRequest $request a ValidateRequest instance.
     * @return array search query
     */
    abstract public function getSearchQueryBuilder($request);

    /**
     * Init searchable feature.
     *
     * @todo  Should be called when instantiating Model.
     */
    protected function initSearchable()
    {
        // Make sure the $searchableCache is set.
        if (! isset($this->searchableCache)
            || (isset($this->searchableCache) && empty($this->searchableCache))
        ) {
            throw new Exception(
                'searchableCache property should be set '
                . ' when using Searchable feature'
            );
        }

        // Init Cache
        if (empty($this->cache)) {
            $cacheConfig = Config::get('orbit.cache.context');
            $this->cache = SimpleCache::create(
                $cacheConfig,
                $this->getSearchableCacheKey()
            );
        }

        // Init SearchProvider if needed.
        $this->searchProvider = App::make(SearchProviderInterface::class);

        $this->shouldLog = Config::get('orbit.searchable.log', false);
    }

    /**
     * Count search result only, don't return the records.
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function countResult($query)
    {
        $this->countOnly = true;

        return $this->search($query);
    }

    public function getSearchableCacheKey()
    {
        return $this->countOnly
            ? $this->searchableCache . '-count'
            : $this->searchableCache;
    }

    /**
     * Basic search function.
     *
     * @param  array|ValidateRequest $query [description]
     * @return array|Collection $result search result from SearchProvider.
     */
    public function search($query, $cacheKey = '')
    {
        $this->initSearchable();

        // If $query is a ValidateRequest instance, then build the search query
        // with DataBuilder helper.
        // Otherwise, we assume $query is an array that is ready to be passed to
        // SearchProvider.
        if ($query instanceof ValidateRequest) {
            $query = $this->getSearchQueryBuilder($query)->build();

            $cacheKey = $query->getCacheKey();
            $query = $query->getQuery();
        }
        else {
            // If not instance of Validate request, just create a cacheKey
            // based on search query params.
            $cacheKey = SimpleCache::transformDataToHash($query);
        }

        // Get cached or a fresh search result.
        return $this->getCachedOrFreshResult($cacheKey, $query);
    }

    /**
     * Get result from Cache or do a fresh search.
     *
     * @param  [type] $cacheKey [description]
     * @param  [type] $query    [description]
     * @return [type]           [description]
     */
    protected function getCachedOrFreshResult($cacheKey, $query)
    {
        $this->log('Getting cached search result...');

        return $this->cache->get($cacheKey, function() use ($query, $cacheKey)
            {
                $this->log('No result from cache!');

                $result = $this->freshSearch($query);

                $this->cache->put($cacheKey, $result);

                return $result;
            });
    }

    /**
     * Run a fresh search from server.
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    protected function freshSearch($query)
    {
        $this->log('Performing a fresh search...');

        if ($this->countOnly) {
            return $this->searchProvider->count($query);
        }

        return $this->searchProvider->search($query);
    }
}
