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
}