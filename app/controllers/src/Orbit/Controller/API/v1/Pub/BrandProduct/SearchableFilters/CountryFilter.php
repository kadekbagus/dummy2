<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement country filter specific for Brand Product.
 */
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
        // Ignore country filter if set to count result only.
        if ($this->countOnly) {
            return;
        }

        if ($country != '0' && ! empty($country)) {
            $this->must(['match' => ['country' => $country]]);
        }
    }
}
