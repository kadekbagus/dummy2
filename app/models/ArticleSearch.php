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
        $this->filter([ 'match' => ['status' => 'active']]);
        $this->filter([ 'range' => ['published_at' => ['lte' => $params['dateTimeEs']]] ]);
    }

    /**
     * Filter by selected stores Categories..
     *
     * @return [type] [description]
     */
    public function filterByCategories($categories = [], $logic = 'must')
    {
        $arrCategories = [];

        foreach($categories as $category) {
            $arrCategories[] = ['match' => ['link_to_categories.category_id' => $category]];
        }

        $this->{$logic}([
            'nested' => [
                'path' => 'link_to_categories',
                'query' => [
                    'bool' => [
                        'should' => $arrCategories,
                        // 'minimum_should_match' => 1,
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
    public function filterByKeyword($keyword = '', $logic = 'should')
    {
        $keyword = $this->escape($keyword);
        $keywordFields = ['title', 'body'];

        $priorityFields = [];
        foreach($keywordFields as $field) {
            $priorityFields[] = $field . $this->esConfig['priority'][$this->index][$field];
        }

        $this->{$logic}([
            'query_string' => [
                'query' => '*' . $keyword . '*',
                'fields' => $priorityFields
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
        $this->filter(['match' => ['country' => $countryName]]);
    }


    /**
     * Filte by Cities...
     *
     * @param  array  $area [description]
     * @return [type]       [description]
     */
    public function filterByCities($cities = [])
    {
        $arrCities = [];

        foreach($cities as $city) {
            $arrCities[] = ['match' => ['city' => $city]];
        }

        $this->filter(['bool' =>['should' => $arrCities]]);
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
    public function filterByLinkedObject($objectType = '', $objectId = '', $logic = 'should')
    {
        $linkPath = '';
        $keyId = '';
        switch ($objectType) {
            case 'mall':
                $linkPath = 'malls';
                $keyId = 'mall_id';
                break;

            case 'brand':
            case 'store':
                $linkPath = 'brands';
                $keyId = 'name.raw';
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

            case 'partner':
                $linkPath = 'partners';
                $keyId = 'partner_id';
                break;

            default:
                // Dont add any query
                break;
        }

        if ($objectType == 'brand' || $objectType == 'store') {
            // get Query name
            $storeName = Tenant::select('name', 'country_id')
                            ->where('merchant_id', $objectId)
                            ->firstOrFail();

            $matchQuery =   [
                                'match' => [
                                    "link_to_{$linkPath}.{$keyId}" => $storeName->name
                                ]
                            ];

        } else {
            $matchQuery =   [
                                'match' => [
                                    "link_to_{$linkPath}.{$keyId}" => $objectId
                                ]
                            ];
        }

        if (! empty($linkPath) && ! empty($keyId)) {
            $this->{$logic}([
                'nested' => [
                    'path' => "link_to_{$linkPath}",
                    'query' => [
                        'bool' => [
                            'should' => [
                                $matchQuery
                            ],
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
