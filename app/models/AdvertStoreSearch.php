<?php

use Search as OrbitESSearch;

/**
* Implementation of ES search for Stores...
*/
class AdvertStoreSearch extends OrbitESSearch
{
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
                            'advert_location_ids' => !empty($options['mallId']) ? $options['mallId'] : 0
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
	/**
	 * Sort by...
	 * 
	 * @param  array  $sortParams [description]
	 * @return [type]             [description]
	 */
	public function sortBy($sortParams = [])
	{
		$this->sort($sortParams);
	}
}