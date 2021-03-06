<?php

class MacAddress extends Eloquent {
    /**
     * MacAddress Model
     *
     * @author Yudi <yudi.rahono@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'mac_addresses';

    protected $primaryKey = 'mac_address_id';

    protected $fillable = ['mac_address', 'user_email', 'ip_address'];

    public function user()
    {
        return $this->belongsTo('User', 'user_email', 'user_email')->excludeDeleted();
    }
}
