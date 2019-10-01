<?php

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Stores...
*/
class CouponSearch extends CampaignSearch
{
    protected $objectType = 'coupons';
    protected $objectTypeAlias = 'coupon';

    /**
     * Make sure the promotion is active.
     *
     * @return [type] [description]
     */
    public function isActive($params = [])
    {
        parent::isActive($params);
        $this->must([ 'range' => ['available' => ['gt' => 0]] ]);
    }

    /**
     * Filter by user's geo-location...
     *
     * @param  string $location [description]
     * @return [type]           [description]
     */
    public function filterByLocation($location = [])
    {
    }

    protected function setPriorityForLinkToTenant($keyword)
    {
        $priorityCountry = isset($this->esConfig['priority'][$this->objectType]['country']) ?
            $this->esConfig['priority'][$this->objectType]['country'] : '';

        $priorityProvince = isset($this->esConfig['priority'][$this->objectType]['province']) ?
            $this->esConfig['priority'][$this->objectType]['province'] : '';

        $priorityCity = isset($this->esConfig['priority'][$this->objectType]['city']) ?
            $this->esConfig['priority'][$this->objectType]['city'] : '';

        $priorityMallName = isset($this->esConfig['priority'][$this->objectType]['mall_name']) ?
            $this->esConfig['priority'][$this->objectType]['mall_name'] : '';

        $this->should([
            'nested' => [
                'path' => 'link_to_tenant',
                'query' => [
                    'query_string' => [
                        'query' => '*' . $keyword . '*',
                        'fields' => [
                            'link_to_tenant.country' . $priorityCountry,
                            'link_to_tenant.province' . $priorityProvince,
                            'link_to_tenant.city' . $priorityCity,
                            'link_to_tenant.mall_name' . $priorityMallName,
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function filterAdvertCampaign($options = [])
    {
        $this->must([
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                [
                                    'query' => [
                                        'match' => [
                                            'advert_status' => 'active'
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'available' => [
                                            'gt' => 0,
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'advert_start_date' => [
                                            'lte' => $options['dateTimeEs']
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        'advert_end_date' => [
                                            'gte' => $options['dateTimeEs']
                                        ]
                                    ]
                                ],
                                [
                                    'match' => [
                                        'advert_location_ids' => $options['locationId']
                                    ]
                                ],
                                [
                                    'terms' => [
                                        'advert_type' => $options['advertType']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'bool' => [
                            'must_not' => [
                                [
                                    'exists' => [
                                        'field' => 'advert_status'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Filter normal coupons and the advertised ones.
     *
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public function filterWithAdvert($options = [])
    {
        // Get Advert_Store...
        $esAdvertIndex = $this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_coupons']['index'];
        $advertSearch = new AdvertSearch($this->esConfig, 'advert_coupons');

        $advertSearch->setPaginationParams(['from' => 0, 'size' => 100]);

        $advertSearch->filterCoupons($options);

        $this->filterAdvertCampaign($options);

        $advertSearchResult = $advertSearch->getResult();

        if ($advertSearchResult['hits']['total'] > 0) {
            $advertList = $advertSearchResult['hits']['hits'];
            $excludeId = array();
            $withPreferred = array();

            foreach ($advertList as $adverts) {
                $advertId = $adverts['_id'];
                $newsId = $adverts['_source']['promotion_id'];
                if(! in_array($newsId, $excludeId)) {
                    $excludeId[] = $newsId;
                } else {
                    $excludeId[] = $advertId;
                }

                // if featured list_type check preferred too
                if ($options['list_type'] === 'featured') {
                    if ($adverts['_source']['advert_type'] === 'preferred_list_regular' || $adverts['_source']['advert_type'] === 'preferred_list_large') {
                        if (empty($withPreferred[$newsId]) || $withPreferred[$newsId] != 'preferred_list_large') {
                            $withPreferred[$newsId] = 'preferred_list_regular';
                            if ($adverts['_source']['advert_type'] === 'preferred_list_large') {
                                $withPreferred[$newsId] = 'preferred_list_large';
                            }
                        }
                    }
                }
            }

            $this->exclude($excludeId);

            $this->sortBy($options['advertSorting']);

            $this->setIndex($this->getIndex() . ',' . $esAdvertIndex);
        }
    }

}
