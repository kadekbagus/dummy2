<?php
/**
 * Created by IntelliJ IDEA.
 * User: palanglung
 * Date: 16/04/15
 * Time: 11:23
 */

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

    public function user()
    {
        return $this->belongsTo('User', 'user_email', 'user_email');
    }
}
