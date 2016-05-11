<?php

class MerchantGeofence extends Eloquent
{
    /**
     * UserSignin Model
     *
     * @author shelgi <shelgi@dominopos.com>
     */

    protected $table = 'merchant_geofences';

    protected $primaryKey = 'merchant_geofence_id';

	public function mall()
    {
        return $this->belongsTo('Mall', 'merchant_id', 'merchant_id');
    }
}