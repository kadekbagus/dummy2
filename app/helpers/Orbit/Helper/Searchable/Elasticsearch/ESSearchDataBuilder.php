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
use Orbit\Helper\Searchable\Helper\Cacheable;
use Orbit\Helper\Searchable\Helper\InteractsWithCache;

/**
 * Base Search Query Builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
abstract class ESSearchDataBuilder extends ESQueryBuilder
{
    // use InteractsWithCache;

    // Inject basic filters and sorting scripts.
    // Can be overridden by child classes.
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
     * @param  string $keyword [description]
     * @return [type]          [description]
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
     * @return [type] [description]
     */
    abstract public function sortByNearest($ul = null);

    public function build()
    {
        $this->setLimit($this->request->skip, $this->request->take);
    }
}
