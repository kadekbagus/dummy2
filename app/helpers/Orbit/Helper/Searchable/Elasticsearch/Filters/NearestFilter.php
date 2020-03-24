<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait NearestFilter
{
    /**
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    protected function nearestSort($item, $itemPos, $ul = null)
    {
        // Get user location ($ul), latitude and longitude.
        // If latitude and longitude doesn't exist in query string, the code will be read cookie to get lat and lon
        if ($ul == null) {
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');

            $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
            if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                $longitude = $userLocationCookieArray[0];
                $latitude = $userLocationCookieArray[1];
            }
        } else {
            $loc = explode('|', $ul);
            $longitude = $loc[0];
            $latitude = $loc[1];
        }

        if (isset($longitude) && isset($latitude))  {
            $geoData = [
                '_geo_distance'=> [
                    $itemPos => [
                        'lon' => $longitude,
                        'lat' => $latitude
                    ],
                    'order'=> 'asc',
                    'unit'=> 'km',
                    'distance_type'=> 'plane'
                ]
            ];
            if (! empty($item)) {
                $geoData['_geo_distance']['nested_path'] = $item;
            }
            $this->sort($geoData);
        }

        // Sort by name after nearest.
        $this->sortByName();
    }
}
