<?php

use CampaignSearch;

use Orbit\Helper\Util\FollowStatusChecker;

/**
* Implementation of ES search for Promotions...
*/
class PromotionSearch extends CampaignSearch
{
    protected $objectType = 'promotions';
    protected $objectTypeAlias = 'promotion';

    /**
     * Apply the same advert filtering to main query.
     *
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public function filterAdvertPromotions($options = [])
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
     * Filter advert and main collections.
     *
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public function filterWithAdvert($options = [])
    {
        // Get Advert_Store...
        $esAdvertIndex = $this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_promotions']['index'];
        $advertSearch = new AdvertSearch($this->esConfig, 'advert_promotions');

        $advertSearch->setPaginationParams(['from' => 0, 'size' => 100]);

        $advertSearch->filterPromotions($options);

        // Apply the same filter to main query.
        $this->filterAdvertPromotions($options);

        $advertSearchResult = $advertSearch->getResult();

        if ($advertSearchResult['hits']['total'] > 0) {
            $advertList = $advertSearchResult['hits']['hits'];
            $excludeId = array();
            $withPreferred = array();

            foreach ($advertList as $adverts) {
                $advertId = $adverts['_id'];
                $newsId = $adverts['_source']['news_id'];
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
