<?php

class ProductRetailer extends Eloquent
{
    /**
     * ProductRetailer Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */

    protected $primaryKey = 'product_retailer_id';

    protected $table = 'product_retailer';

    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'product_id');
    }

    public function retailer()
    {
        return $this->belongsTo('Retailer', 'retailer_id', 'retailer_id');
    }
}
