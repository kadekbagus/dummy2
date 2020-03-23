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
abstract class ESSearchDataBuilder extends ESQueryBuilder
{
    // Compose basic filtering and sorting abilities.
    use ScriptFilter,
        ExcludeFilter,
        RatingFilter,
        NearestFilter,
        SortByName,
        SortByRelevance,
        SortByCreatedDate,
        SortByUpdatedDate,
        SortByRating;

    protected $request;

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
    abstract public function filterByKeyword($keyword = '');

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
     * Basic supported sort queries... can be overridden when needed.
     */
    public function addSortQuery()
    {
        $sortBy = $this->request->sortby ?: 'created_date';
        $sortMode = $this->request->sortmode ?: 'desc';

        switch ($sortBy) {
            case 'name':
                $this->sortByName($sortMode);
                break;

            // case 'nearest':
            //     $this->sortByNearest($this->request->ul);
            //     break;

            // case 'rating':
            //     if (method_exists($this, 'addReviewFollowScript')) {
            //         $ratingScript = $this->addReviewFollowScript();
            //     }

            //     $this->sortByRating($ratingScript);
            //     break;

            case 'created_date':
            default:
                // Default sort by latest.
                $this->sortByCreatedDate();
                break;
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
        $this->setLimit($this->request->skip, $this->request->take);

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

        // Set additional params depends on the object.
        $this->addCustomParam();

        // Add sort query
        $this->addSortQuery();

        // Build search params
        $this->buildSearchParam();

        // Return final ES query
        return $this->searchParam;
    }
}
