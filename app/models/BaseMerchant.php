<?php

class BaseMerchant extends Eloquent
{
    /**
     * Base Merchant Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'base_merchant_id';

    protected $table = 'base_merchants';
}
