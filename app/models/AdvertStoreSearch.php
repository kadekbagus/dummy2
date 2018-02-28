<?php

use Orbit\Helper\Elasticsearch\Search;

/**
* Implementation of ES search for Advert Stores...
*/
class AdvertStoreSearch extends Search
{
    function __construct($ESConfig = [], $advertGroup = '')
    {
        parent::__construct($ESConfig);

        $this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices'][$advertGroup]['index']);
        $this->setType($this->esConfig['indices'][$advertGroup]['type']);
    }

    /**
     * Base filter of Advert Stores search.
     * Basically it will filter by:
     *     - start and end time by the given dateTime options.
     *     - Mall ID (if set)
     *     - advert type
     * 
     * @param  array  $options [description]
     * @return [type]          [description]
     */
	public function filterBase($options = [])
	{
		$this->must([
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
        ]);
	}

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