<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

trait CitiesFilter
{
    /**
     * Filter by cities.
     *
     * @param  [type] $cities [description]
     * @return [type]         [description]
     */
    public function filterByCities($cities = [])
    {
        $citiesQuery['bool']['should'] = [];

        foreach($cities as $city) {
            $citiesQuery['bool']['should'][] = [
                'nested' => [
                    'path' => 'cities',
                    'query' => [
                        'match' => [
                            'cities.city_name' => $city
                        ]
                    ]
                ]
            ];
        }

        $this->must($citiesQuery);
    }
}
