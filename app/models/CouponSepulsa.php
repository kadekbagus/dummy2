<?php

class CouponSepulsa extends Eloquent
{
    /**
     * CouponSepulsa Model
     *
     * @author Firmansyah <frimansyah@dominopos.com>
     */

    protected $table = 'coupon_sepulsa';

    protected $primaryKey = 'coupon_sepulsa_id';

    public function coupon()
    {
        return $this->hasOne('Coupon', 'promotion_id', 'promotion_id');
    }
}
