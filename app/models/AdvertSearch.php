<?php

use Orbit\Helper\Elasticsearch\Search;

/**
* Collection of filter for Adverts. This includes: 
*  - Advert Stores
*  - Advert Promotion
*  - Advert News
*  - Advert Coupon
*
* @author Budi Raharja <budi@dominopos.com>
*/
class AdvertSearch extends Search
{
    function __construct($ESConfig = [], $advertGroup = '')
    {
        parent::__construct($ESConfig);

        $this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices'][$advertGroup]['index']);
        $this->setType($this->esConfig['indices'][$advertGroup]['type']);
    }

    /**
     * Base filter of Advert Stores search.
     *
     * @todo add sort to this query.
     * @param  array  $options [description]
     * @return [type]          [description]
     */
	public function filterStores($options = [])
	{
		$this->must([
            'query' => [
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
            ]
        ]);
	}

    /**
     * Filter advert_promotions index.
     * 
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function filterPromotions($params = [])
    {
        $this->must(['match' => ['advert_status' => 'active']]);

        $this->must(['range' => ['advert_start_date' => ['lte' => $params['dateTimeEs']]]]);

        $this->must(['range' => ['advert_end_date' => ['gte' => $params['dateTimeEs']]]]);

        $this->must(['match' => ['advert_location_ids' => $params['locationId']]]);

        $this->must(['terms' => ['advert_type' => $params['advertType']]]);

        $this->should([
            'bool' => [
                'must_not' => [
                    'exists' => [
                        'field' => 'advert_status'
                    ],
                ]
            ]
        ]);
    }


    /**
     * Filter advert_news ...
     * 
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function filterNews($params = [])
    {
        $this->must(['match' => ['advert_status' => 'active']]);

        $this->must(['range' => ['advert_start_date' => ['lte' => $params['dateTimeEs']]]]);

        $this->must(['range' => ['advert_end_date' => ['gte' => $params['dateTimeEs']]]]);

        $this->must(['match' => ['advert_location_ids' => $params['locationId']]]);

        $this->must(['terms' => ['advert_type' => $params['advertType']]]);

        $this->should([
            'bool' => [
                'must_not' => [
                    'exists' => [
                        'field' => 'advert_status'
                    ],
                ]
            ]
        ]);
    }

    /**
     * Filter advert_coupons...
     * 
     * @param  array  $params [description]
     * @return [type]         [description]
     */
    public function filterCoupons($params = [])
    {
        $this->must(['match' => ['advert_status' => 'active']]);

        $this->must(['range' => ['advert_start_date' => ['lte' => $params['dateTimeEs']]]]);

        $this->must(['range' => ['advert_end_date' => ['gte' => $params['dateTimeEs']]]]);

        $this->must(['match' => ['advert_location_ids' => $params['locationId']]]);

        $this->must(['terms' => ['advert_type' => $params['advertType']]]);

        $this->should([
            'bool' => [
                'must_not' => [
                    'exists' => [
                        'field' => 'advert_status'
                    ],
                ]
            ]
        ]);
    }
}