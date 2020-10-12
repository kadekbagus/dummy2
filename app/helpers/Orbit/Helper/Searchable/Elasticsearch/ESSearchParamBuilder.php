<?php

namespace Orbit\Helper\Searchable\Elasticsearch;

use Orbit\Helper\Elasticsearch\ESQueryBuilder;
use Orbit\Helper\Searchable\Elasticsearch\Filters\ExcludeFilter;
use Orbit\Helper\Searchable\Elasticsearch\Filters\MallFilter;
use Orbit\Helper\Searchable\Elasticsearch\Filters\NearestFilter;
use Orbit\Helper\Searchable\Elasticsearch\Filters\RatingFilter;
use Orbit\Helper\Searchable\Elasticsearch\Filters\ScriptFilter;
use Orbit\Helper\Searchable\Elasticsearch\Filters\SortByCreatedDate;
use Orbit\Helper\Searchable\Elasticsearch\Filters\SortByName;
use Orbit\Helper\Searchable\Elasticsearch\Filters\SortByRating;
use Orbit\Helper\Searchable\Elasticsearch\Filters\SortByRelevance;
use Orbit\Helper\Searchable\Elasticsearch\Filters\SortByUpdatedDate;

/**
 * Base Search Query Builder.
 *
 * @todo Create a campaign-specific filters/sortings which can be used
 *       by child classes to compose their searchable abilities.
 *       (e.g. filter by partner)
 *
 * @todo Filter by parther.
 *
 * @author Budi <budi@gotomalls.com>
 */
abstract class ESSearchParamBuilder extends ESQueryBuilder
{
    // Compose basic filtering and sorting abilities.
    // Can be overriden by child classes if needed.
    use ScriptFilter,
        ExcludeFilter,
        RatingFilter,
        NearestFilter,
        SortByName,
        SortByRelevance,
        SortByCreatedDate,
        SortByUpdatedDate,
        SortByRating;

    /**
     * List of cached request params, which will be used to generate
     * the cache key.
     * @var array
     */
    protected $cachedRequestParams = [];

    public function __construct($request)
    {
        parent::__construct();
        $this->request = $request;
    }

    /**
     * Make sure child builder implement the filter by keyword.
     *
     * @param  string $keyword the search keyword
     */
    abstract public function filterByKeyword($keyword = '', $logic = 'must');

    /**
     * Filter by partner.
     *
     * @param  string $partnerId [description]
     * @return [type]            [description]
     */
    // abstract public function filterByPartner($partnerId = '');

    /**
     * Sort by Nearest..
     *
     * @param string $ul user location.
     */
    // abstract public function sortByNearest($ul = null);

    /**
     * Filter by country.
     *
     * @param string $country country name
     */
    abstract public function filterByCountry($country);

    /**
     * Filter by cities.
     *
     * @param  array  $cities array of city name
     */
    abstract public function filterByCities($cities = []);

    /**
     * Add object specific filter/sort.
     */
    abstract protected function addCustomParam();

    /**
     * Build the cache keys based on request params.
     *
     * @override
     */
    protected function buildCacheKey()
    {
        foreach($this->cachedRequestParams as $param) {
            $this->request->has($param, function($value) use ($param) {
                $this->cacheKeys[$param] = $value;
            });
        }
    }

    /**
     * Get list of sorting params.
     *
     * @return [type] [description]
     */
    protected function getSortingParams()
    {
        return [
            $this->request->sortby ?: 'created_date' =>
                $this->request->sortmode ?: 'desc'
        ];
    }

    protected function getSkipValue()
    {
        return $this->request->skip ?: 0;
    }

    protected function getTakeValue()
    {
        return $this->request->take ?: 20;
    }

    /**
     * Basic supported sort queries... can be overridden when needed.
     */
    public function addSortQuery()
    {
        // Ignore sorting if request only to count the result.
        // (For example, called by MenuCounterAPI)
        if ($this->countOnly) {
            return;
        }

        $sortingParams = $this->getSortingParams();

        foreach($sortingParams as $sortBy => $sortMode) {
            switch ($sortBy) {
                case 'name':
                    $this->sortByName($sortMode);
                    break;

                case 'relevance':
                    $this->sortByRelevance();
                    break;

                // Disable sortby nearest and rating at the moment.
                // case 'nearest':
                //     $this->sortByNearest($this->request->ul);
                //     break;

                // case 'rating':
                //     $ratingScript = $this->addReviewFollowScript();
                //     $this->sortByRating($ratingScript);
                //     break;

                case 'created_date':
                default:
                    // Default sort by latest.
                    $this->sortByCreatedDate();
                    break;
            }
        }
    }

    /**
     * Build search param/query.
     *
     * @return  array query params.
     */
    public function build()
    {
        // Set result limit
        $this->setLimit($this->getSkipValue(), $this->getTakeValue());

        // Filter by country
        $this->request->has('country', function($country) {
            $this->filterByCountry($country);
        });

        // Filter by cities
        $this->request->has('cities', function($cities) {
            $this->filterByCities($cities);
        });

        // Filter by keyword
        $this->request->has('keyword', function($keyword) {
            $this->filterByKeyword($keyword);
        });

        // Somehow menu counter receive 'keywords' instead of 'keyword' ????
        $this->request->has('keywords', function($keyword) {
            $this->filterByKeyword($keyword);
        });

        // If request should use scrolling, then set specific params.
        if (method_exists($this->request, 'useScrolling')
            && $this->request->useScrolling()
        ) {
            $this->setParams([
                'search_type' => 'scan',
                'scroll' => $this->request->getScrollDuration(),
            ]);

            $this->removeParamItem('body.aggs');
        }

        // Set additional params depends on the object.
        $this->addCustomParam();

        // Add sort query
        $this->addSortQuery();

        // Build search params
        $this->buildSearchParam();

        // Return final ES query
        return $this;
    }
}
