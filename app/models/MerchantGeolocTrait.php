<?php
/**
 * Trait related for geolocation and the Merchants or Malls
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
trait MerchantGeolocTrait
{
    /**
     * Scope used to get list of malls whether a coordinate is inside a
     * fence of the object or not.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param double $latitude
     * @param double $longitude
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInsideArea($builder, $latitude, $longitude)
    {
        $prefix = DB::getTablePrefix();
        $builder->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id');
        $builder->where(function($q) use ($prefix, $latitude, $longitude) {
            $q->whereRaw("MBRCONTAINS({$prefix}merchant_geofences.area, POINT(?, ?))", [$latitude, $longitude]);
        });

        return $builder;
    }

    /**
     * Scope used to get list of malls inside polygon area.
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param string $area
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInsideMapArea($builder, $area)
    {
        $prefix = DB::getTablePrefix();
        $area = preg_replace('/[^0-9\s,\-\.]/', '',  $area);
        $builder->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id');

        // check if area or polygon crosses the International Date Line
        $square = explode(', ', $area);
        $point = array();
        foreach($square as $sq) {
            $allPoint = explode(' ', $sq);
            $point[] = $allPoint;
        }

        $swLat = $point[0][0];
        $swLon = $point[0][1];

        $neLat = $point[1][0];
        $swLon = $point[1][1];

        $neLat = $point[2][0];
        $neLon = $point[2][1];

        $swLat = $point[3][0];
        $neLon = $point[3][1];

        $swLat = $point[4][0];
        $swLon = $point[4][1];

        if ($swLon > $neLon) {
            // if area or polygon crosses the International Date Line
            $area1 = $swLat . ' ' . $swLon . ',' . $neLat . ' ' . $swLon . ',' . $neLat . ' 180,' . $swLat . ' 180,' . $swLat . ' ' . $swLon;
            $area2 = $swLat . ' -180,' . $neLat . ' -180,' . $neLat . ' ' . $neLon . ',' . $swLat . ' ' . $neLon . ',' . $swLat . ' -180';
            $builder->where(function($q) use ($prefix, $area1, $area2) {
                $q->whereRaw("MBRCONTAINS(GeomFromText('POLYGON(({$area1}))'), POINT(X({$prefix}merchant_geofences.position), Y({$prefix}merchant_geofences.position)))")
                  ->orWhereRaw("MBRCONTAINS(GeomFromText('POLYGON(({$area2}))'), POINT(X({$prefix}merchant_geofences.position), Y({$prefix}merchant_geofences.position)))");
            });

        } else {
            $area1 = $swLat . ' ' . $swLon . ',' . $neLat . ' ' . $swLon . ',' . $neLat . ' ' . $neLon . ',' . $swLat . ' ' . $neLon . ',' . $swLat . ' ' . $swLon;
            $builder->where(function($q) use ($prefix, $area1) {
                $q->whereRaw("MBRCONTAINS(GeomFromText('POLYGON(({$area1}))'), POINT(X({$prefix}merchant_geofences.position), Y({$prefix}merchant_geofences.position)))");
            });
        }

        return $builder;
    }

    /**
     * Scope used to get list of malls within certain radius. We are using
     * haversine formula taken from:
     * http://www.movable-type.co.uk/scripts/latlong.html and
     * https://developers.google.com/maps/articles/phpsqlsearch_v3
     *
     * a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2)
     * c = 2 ⋅ atan2( √a, √(1−a) )
     * d = R ⋅ c
     *
     * φ is latitude, λ is longitude, R is earth’s radius (mean radius = 6,371km);
     * note that angles need to be in radians to pass to trig functions!
     *
     * note : for searching, distance will be change to '-1' and filter for distance is disable
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @param double $latitude
     * @param double $longitude
     * @param double $distance
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNearBy($builder, $latitude, $longitude, $distance)
    {
        $prefix = DB::getTablePrefix();
        $R = 6371;
        $latitude = (double)$latitude;
        $longitude = (double)$longitude;
        $distance = (double)$distance;

        $builder->addSelect(
            DB::raw("{$R} * acos( cos( radians({$latitude}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$longitude}) ) + sin( radians({$latitude}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"
        ));

        if ((int) $distance !== -1) {
            $builder->having(DB::raw('distance'), '<=', $distance);
        }
        
        return $builder;
    }

    /**
     * Scope to add latitude and longitude column to the query builder.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncludeLatLong($builder)
    {
        $prefix = DB::getTablePrefix();
        return $builder->addSelect(DB::raw(
            "X({$prefix}merchant_geofences.position) as latitude,
             Y({$prefix}merchant_geofences.position) as longitude"
        ));
    }
}