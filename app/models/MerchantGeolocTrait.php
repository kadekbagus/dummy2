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
        $builder->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', 'merchants.merchant_id');
        $builder->having(DB::raw('distance'), '<=', $distance);

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