<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait CountryFilter
{
    /**
     * Filter by country.
     *
     * @param  [type] $country [description]
     * @return [type]          [description]
     */
    public function filterByCountry($country)
    {
        if ($country !== '0' && ! empty($country)) {
            $this->must(['match' => ['country' => $country]]);
        }
    }
}
