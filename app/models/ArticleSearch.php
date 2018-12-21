<?php

use Orbit\Helper\Elasticsearch\Search;
use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Article.
*
* @author Budi <budi@dominopos.com>
*/
class ArticleSearch extends Search
{
    protected $index = 'articles';

    protected $searchLinkedObject = true;

    function __construct($ESConfig = [])
    {
        parent::__construct($ESConfig);

        $this->setDefaultSearchParam();

        $this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices'][$this->index]['index']);
        $this->setType($this->esConfig['indices'][$this->index]['type']);
    }

    /**
     * Make sure the promotion is active.
     *
     * @return [type] [description]
     */
    public function isActive($params = [])
    {
        $this->must([ 'match' => ['status' => 'active']]);
    }

    /**
     * Filter by selected stores Categories..
     *
     * @return [type] [description]
     */
    public function filterByCategories($categories = [])
    {
        $arrCategories = [];

        foreach($categories as $category) {
            $arrCategories[] = ['match' => ['link_to_categories.category_id' => $category]];
        }

        $this->should([
            'nested' => [
                'path' => 'link_to_categories',
                'query' => [
                    'bool' => [
                        'should' => $arrCategories
                    ]
                ]
            ],
        ]);
    }

    /**
     * Implement filter by keyword...
     *
     * @param  string $keyword [description]
     * @return [type]          [description]
     */
    public function filterByKeyword($keyword = '')
    {
        $keywordFields = ['title', 'body'];

        $priorityFields = [];
        foreach($keywordFields as $field) {
            $priorityFields[] = $field . $this->esConfig['priority'][$this->index][$field];
        }

        $this->should([
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'query' => '*' . $keyword . '*',
                            'fields' => $priorityFields
                        ]
                    ],
                ]
            ]
        ]);
    }

    /**
     * Filte by Country and Cities...
     *
     * @param  array  $area [description]
     * @return [type]       [description]
     */
    public function filterByCountry($countryName = '')
    {
        $this->must(['match' => ['country' => $countryName]]);
    }

    /**
     * Filter by linked object.
     *
     * @todo  add boost/priority to make sure that the result will
     *        always be sorted by linked object (then by keyword/categories)
     * @param  string $objectType [description]
     * @param  string $objectId   [description]
     * @return [type]             [description]
     */
    public function filterByLinkedObject($objectType = '', $objectId = '')
    {
        $linkPath = '';
        $keyId = '';
        switch ($objectType) {
            case 'mall':
                $linkPath = 'malls';
                $keyId = 'mall_id';
                break;
            case 'brand':
                $linkPath = 'brands';
                $keyId = 'brand_id';
                break;
            case 'coupon':
                $linkPath = 'coupons';
                $keyId = 'coupon_id';
                break;
            case 'event':
                $linkPath = 'events';
                $keyId = 'event_id';
                break;
            case 'promotion':
                $linkPath = 'promotions';
                $keyId = 'promotion_id';
                break;

            default:
                // Dont add any query
                $this->searchLinkedObject = false;
                break;
        }

        if (! empty($linkPath) && ! empty($keyId)) {
            $this->should([
                'nested' => [
                    'path' => "link_to_{$linkPath}",
                    'query' => [
                        'bool' => [
                            'should' => [
                                [
                                    'match' => [
                                        "link_to_{$linkPath}.{$keyId}" => $objectId
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
            ]);
        }
    }

    public function filterExclude($excludedItems = [])
    {
        if (! empty($excludedItems)) {
            $excludedItems = ! is_array($excludedItems) ? [$excludedItems] : $excludedItems;

            $this->mustNot([
                'terms' => [
                    '_id' => $excludedItems
                ]
            ]);
        }
    }

    /**
     * Sort store by created date.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByPublishingDate($order = 'desc')
    {
        $this->sort([
            'published_at' => [
                'order' => $order
            ]
        ]);
    }

    /**
     * Sort by relevance..
     *
     * @return [type] [description]
     */
    public function sortByRelevance()
    {
        $this->sort(['_score' => ['order' => 'desc']]);
    }
}
