<?php

class EventRetailer extends Eloquent
{
    /**
     * EventRetailer Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'event_retailer';

    protected $primaryKey = 'event_retailer_id';

    public function event()
    {
        return $this->belongsTo('Event', 'event_id', 'event_id');
    }

    public function retailer()
    {
        return $this->belongsTo('Retailer', 'retailer_id', 'merchant_id');
    }
}
