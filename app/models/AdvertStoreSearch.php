<?php

use Orbit\Helper\Elasticsearch\Search;

/**
* Implementation of ES search for Advert Stores...
*/
class AdvertStoreSearch extends Search
{
    function __construct($ESConfig = [])
    {
        parent::__construct($ESConfig);

        $this->setIndex($this->esConfig['indices_prefix'] . $this->esConfig['indices']['advert_stores']['index']);
        $this->setType($this->esConfig['indices']['advert_stores']['type']);
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
}