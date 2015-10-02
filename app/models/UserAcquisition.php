<?php

/**
 * @property string user_id
 * @property string acquirer_id
 * @property string user_acquisition_id
 */
class UserAcquisition extends Eloquent {
    /**
     * UserAcquisition Model relates a user to a specific mall / merchant.
     *
     * This is created when the local box does not have the record for the user and asks
     * the cloud for the user info. The cloud server stores the relation of the user and
     * the mall / merchant so it can send user-specific data to the mall/merchant's box.
     *
     * @author William
     */

    protected $table = 'user_acquisitions';

    protected $primaryKey = 'user_acquisition_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')->excludeDeleted();
    }
}
