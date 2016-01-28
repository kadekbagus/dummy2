<?php
/**
 * Model for table `connection_times`.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class ConnectionTime extends Eloquent
{
    protected $primaryKey = 'connection_time_id';
    protected $table = 'connection_times';
    public $timestamps = FALSE;

    /**
     * Belongs to User
     */
    public function User()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Belongs to User
     */
    public function location()
    {
        return $this->belongsTo('Mall', 'location_id', 'merchant_id');
    }
}